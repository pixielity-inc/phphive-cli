<?php

declare(strict_types=1);

namespace PhpHive\Cli\Console\Commands\Make;

use function is_dir;
use function Laravel\Prompts\select;

use Override;
use PhpHive\Cli\Console\Commands\BaseCommand;
use PhpHive\Cli\Contracts\AppTypeInterface;
use PhpHive\Cli\Factories\AppTypeFactory;
use PhpHive\Cli\Support\Filesystem;

use function str_replace;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

/**
 * Create App Command.
 *
 * This command scaffolds a complete PHP application structure within the monorepo's
 * apps/ directory using the app type system. It supports multiple application types
 * (Laravel, Symfony, Magento, Skeleton) with their specific configurations and
 * scaffolding requirements.
 *
 * The scaffolding process:
 * 1. Prompt user to select application type (if not provided)
 * 2. Collect configuration through interactive prompts
 * 3. Validate the application name doesn't already exist
 * 4. Create the application directory
 * 5. Run the app type's installation command (e.g., composer create-project)
 * 6. Copy and process stub template files
 * 7. Run post-installation commands (migrations, compilation, etc.)
 * 8. Display next steps to the user
 *
 * Supported app types:
 * - Laravel: Full-stack PHP framework with Breeze/Jetstream, Sanctum, Octane
 * - Symfony: High-performance framework with Maker, Security, Doctrine
 * - Magento: Enterprise e-commerce with Elasticsearch, Redis, sample data
 * - Skeleton: Minimal PHP application with Composer and optional tools
 *
 * Example usage:
 * ```bash
 * # Interactive mode - prompts for app type
 * ./cli/bin/hive create:app my-app
 *
 * # Specify app type directly
 * ./cli/bin/hive create:app my-app --type=laravel
 * ./cli/bin/hive create:app shop --type=magento
 * ./cli/bin/hive create:app api --type=symfony
 *
 * # Using aliases
 * ./cli/bin/hive make:app dashboard --type=laravel
 * ./cli/bin/hive new:app service --type=skeleton
 * ```
 *
 * After creation workflow:
 * ```bash
 * # Navigate to the new app
 * cd apps/my-app
 *
 * # Dependencies are already installed by the scaffolding process
 *
 * # Start development server (varies by app type)
 * hive dev --workspace=my-app
 * ```
 *
 * @see BaseCommand For inherited functionality
 * @see AppTypeFactory For app type management
 * @see AppTypeInterface For app type contract
 * @see Filesystem For file operations
 */
#[AsCommand(
    name: 'make:app',
    description: 'Create a new application',
    aliases: ['create:app', 'new:app'],
)]
final class CreateAppCommand extends BaseCommand
{
    /**
     * Configure the command options and arguments.
     *
     * Defines the command signature with required arguments, options, and help text.
     * The name argument is required and should be a valid directory name.
     * The type option allows specifying the app type directly.
     *
     * Common options (workspace, force, no-cache, no-interaction) are inherited from BaseCommand.
     */
    #[Override]
    protected function configure(): void
    {
        parent::configure();

        $this
            ->addArgument(
                'name',
                InputArgument::REQUIRED,
                'Application name (e.g., admin, api, shop)',
            )
            ->addOption(
                'type',
                't',
                InputOption::VALUE_REQUIRED,
                'Application type (laravel, symfony, magento, skeleton)',
            )
            ->setHelp(
                <<<'HELP'
                The <info>create:app</info> command scaffolds a new PHP application.

                <comment>Examples:</comment>
                  <info>hive create:app admin</info>
                  <info>hive create:app shop --type=magento</info>
                  <info>hive create:app api --type=symfony</info>

                Available app types: laravel, symfony, magento, skeleton
                HELP
            );
    }

    /**
     * Execute the create app command.
     *
     * This method orchestrates the entire application scaffolding process:
     * 1. Prompts for app type selection (if not provided)
     * 2. Collects configuration through app type prompts
     * 3. Validates the application name doesn't already exist
     * 4. Creates the application directory
     * 5. Runs the app type's installation command
     * 6. Copies and processes stub template files
     * 7. Runs post-installation commands
     * 8. Displays next steps to the user
     *
     * The command will fail if an application with the same name already
     * exists in the apps/ directory to prevent accidental overwrites.
     *
     * @param  InputInterface  $input  Command input (arguments and options)
     * @param  OutputInterface $output Command output (for displaying messages)
     * @return int             Exit code (0 for success, 1 for failure)
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Extract the application name from command arguments
        $name = $input->getArgument('name');

        // Display intro banner
        $this->intro("Creating application: {$name}");

        // =====================================================================
        // APP TYPE SELECTION
        // =====================================================================

        // Get app type from option or prompt user
        $typeOption = $input->getOption('type');
        if ($typeOption !== null && $typeOption !== '') {
            $appTypeId = $typeOption;

            // Validate the provided app type
            if (! AppTypeFactory::isValid($appTypeId)) {
                $this->error("Invalid app type: {$appTypeId}");
                $this->line('Available types: ' . implode(', ', AppTypeFactory::getIdentifiers()));

                return Command::FAILURE;
            }
        } else {
            // Prompt user to select app type
            $appTypeId = select(
                label: 'Select application type',
                options: AppTypeFactory::getChoices()
            );
        }

        // Create the app type instance
        $appType = AppTypeFactory::create($appTypeId);
        $this->info("Selected: {$appType->getName()}");

        // =====================================================================
        // CONFIGURATION COLLECTION
        // =====================================================================

        // Collect configuration through app type prompts
        $this->line('');
        $this->comment('Configuration:');
        $config = $appType->collectConfiguration($input, $output);

        // Override name with command argument
        $config['name'] = $name;

        // =====================================================================
        // VALIDATION
        // =====================================================================

        // Determine the full path for the new application
        $root = $this->getMonorepoRoot();
        $appPath = "{$root}/apps/{$name}";

        // Check if app already exists to prevent overwriting
        if (is_dir($appPath)) {
            $this->error("Application '{$name}' already exists");

            return Command::FAILURE;
        }

        // =====================================================================
        // DIRECTORY CREATION
        // =====================================================================

        $this->line('');
        $this->info('Creating application directory...');
        $filesystem = Filesystem::make();
        $filesystem->makeDirectory($appPath, 0755, true);

        // =====================================================================
        // INSTALLATION COMMAND
        // =====================================================================

        // Run the app type's installation command (e.g., composer create-project)
        $installCommand = $appType->getInstallCommand($config);
        if ($installCommand !== '') {
            $this->info('Running installation command...');
            $this->line("  → {$installCommand}");

            // Execute the installation command in the app directory
            $exitCode = $this->executeCommand($installCommand, $appPath);
            if ($exitCode !== 0) {
                $this->error('Installation command failed');

                return Command::FAILURE;
            }
        }

        // =====================================================================
        // STUB PROCESSING
        // =====================================================================

        // Copy and process stub template files
        $this->info('Processing stub templates...');
        $this->processStubs($appType, $config, $appPath, $filesystem);

        // =====================================================================
        // POST-INSTALL COMMANDS
        // =====================================================================

        // Run post-installation commands
        $postCommands = $appType->getPostInstallCommands($config);
        if ($postCommands !== []) {
            $this->info('Running post-installation commands...');
            foreach ($postCommands as $postCommand) {
                $this->line("  → {$postCommand}");
                $exitCode = $this->executeCommand($postCommand, $appPath);
                if ($exitCode !== 0) {
                    $this->warning("Command failed: {$postCommand}");
                    // Continue with other commands
                }
            }
        }

        // =====================================================================
        // SUCCESS MESSAGE
        // =====================================================================

        $this->line('');
        $this->outro("✓ Application '{$name}' created successfully!");
        $this->line('');
        $this->comment('Next steps:');
        $this->line("  1. cd apps/{$name}");
        $this->line('  2. Review the generated files');
        $this->line("  3. hive dev --workspace={$name}");

        return Command::SUCCESS;
    }

    /**
     * Execute a shell command in a specific directory.
     *
     * Runs a shell command in the specified working directory and returns
     * the exit code. Output is displayed in real-time to the console.
     *
     * @param  string $command The shell command to execute
     * @param  string $cwd     The working directory for the command
     * @return int    The command exit code (0 for success)
     */
    private function executeCommand(string $command, string $cwd): int
    {
        // Use Symfony Process to execute command in specific directory
        $process = Process::fromShellCommandline($command, $cwd, null, null, null);

        // Run the process and display output in real-time
        $process->run(function ($type, $buffer): void {
            echo $buffer;
        });

        return $process->getExitCode() ?? 1;
    }

    /**
     * Process stub template files.
     *
     * Copies stub template files from the app type's stub directory to the
     * application directory, replacing placeholders with actual values.
     *
     * @param AppTypeInterface     $appType    The app type instance
     * @param array<string, mixed> $config     Configuration array
     * @param string               $appPath    Application directory path
     * @param Filesystem           $filesystem Filesystem helper instance
     */
    private function processStubs(AppTypeInterface $appType, array $config, string $appPath, Filesystem $filesystem): void
    {
        // Get the stub directory path for this app type
        $stubPath = $appType->getStubPath();

        // Check if stub directory exists
        if (! is_dir($stubPath)) {
            $this->warning("Stub directory not found: {$stubPath}");

            return;
        }

        // Get stub variables for replacement
        $variables = $appType->getStubVariables($config);

        // Get all stub files recursively
        $stubFiles = $this->getStubFiles($stubPath);

        // Process each stub file
        foreach ($stubFiles as $stubFile) {
            // Get relative path from stub directory
            $relativePath = str_replace($stubPath . '/', '', $stubFile);

            // Check if this is an append stub
            $isAppendStub = str_ends_with($relativePath, '.append.stub');

            // Remove .stub or .append.stub extension for target file
            $targetPath = $isAppendStub ? str_replace('.append.stub', '', $relativePath) : str_replace('.stub', '', $relativePath);

            // Read stub content
            $content = file_get_contents($stubFile);
            if ($content === false) {
                $this->warning("Failed to read stub file: {$stubFile}");

                continue;
            }

            // Replace placeholders with actual values
            $processedContent = str_replace(
                array_keys($variables),
                array_values($variables),
                $content
            );

            // Write to target location
            $targetFile = "{$appPath}/{$targetPath}";

            // Create directory if it doesn't exist
            $targetDir = dirname($targetFile);
            if (! is_dir($targetDir)) {
                $filesystem->makeDirectory($targetDir, 0755, true);
            }

            // Handle append vs replace
            if ($isAppendStub && file_exists($targetFile)) {
                // Append to existing file
                $existingContent = file_get_contents($targetFile);
                if ($existingContent !== false) {
                    $filesystem->write($targetFile, $existingContent . $processedContent);
                }
            } else {
                // Write/overwrite the file
                $filesystem->write($targetFile, $processedContent);
            }
        }
    }

    /**
     * Get all stub files recursively.
     *
     * Scans the stub directory and returns an array of all .stub files.
     *
     * @param  string        $directory The directory to scan
     * @return array<string> Array of stub file paths
     */
    private function getStubFiles(string $directory): array
    {
        $files = [];

        // Get all items in the directory
        $items = scandir($directory);
        if ($items === false) {
            return [];
        }

        foreach ($items as $item) {
            // Skip . and ..
            if ($item === '.') {
                continue;
            }
            if ($item === '..') {
                continue;
            }
            $path = "{$directory}/{$item}";

            // If it's a directory, recurse into it
            if (is_dir($path)) {
                $files = [...$files, ...$this->getStubFiles($path)];
            }
            // If it's a .stub file, add it to the list
            elseif (str_ends_with($item, '.stub')) {
                $files[] = $path;
            }
        }

        return $files;
    }
}
