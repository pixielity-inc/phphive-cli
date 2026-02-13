<?php

declare(strict_types=1);

namespace PhpHive\Cli\Console\Commands\Make;

use Exception;
use Override;
use PhpHive\Cli\Contracts\AppTypeInterface;
use PhpHive\Cli\Enums\AppType;
use PhpHive\Cli\Enums\DatabaseType;
use PhpHive\Cli\Factories\AppTypeFactory;
use PhpHive\Cli\Services\NameSuggestionService;
use PhpHive\Cli\Support\Filesystem;
use Pixielity\StubGenerator\Exceptions\StubNotFoundException;
use Pixielity\StubGenerator\Facades\Stub;

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
 * hive create:app my-app
 *
 * # Specify app type directly
 * hive create:app my-app --type=laravel
 * hive create:app shop --type=magento
 * hive create:app api --type=symfony
 *
 * # Using aliases
 * hive make:app dashboard --type=laravel
 * hive new:app service --type=skeleton
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
final class CreateAppCommand extends BaseMakeCommand
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
            ->addOption(
                'description',
                'd',
                InputOption::VALUE_REQUIRED,
                'Application description',
            )
            // Magento-specific options
            ->addOption(
                'magento-edition',
                null,
                InputOption::VALUE_REQUIRED,
                'Magento edition (community, enterprise)',
            )
            ->addOption(
                'magento-version',
                null,
                InputOption::VALUE_REQUIRED,
                'Magento version (2.4.5, 2.4.6, 2.4.7)',
            )
            ->addOption(
                'magento-public-key',
                null,
                InputOption::VALUE_REQUIRED,
                'Magento public key (username) from marketplace.magento.com',
            )
            ->addOption(
                'magento-private-key',
                null,
                InputOption::VALUE_REQUIRED,
                'Magento private key (password) from marketplace.magento.com',
            )
            ->addOption(
                'db-host',
                null,
                InputOption::VALUE_REQUIRED,
                'Database host',
            )
            ->addOption(
                'db-port',
                null,
                InputOption::VALUE_REQUIRED,
                'Database port',
            )
            ->addOption(
                'db-name',
                null,
                InputOption::VALUE_REQUIRED,
                'Database name',
            )
            ->addOption(
                'db-user',
                null,
                InputOption::VALUE_REQUIRED,
                'Database username',
            )
            ->addOption(
                'db-password',
                null,
                InputOption::VALUE_REQUIRED,
                'Database password',
            )
            ->addOption(
                'admin-firstname',
                null,
                InputOption::VALUE_REQUIRED,
                'Admin first name',
            )
            ->addOption(
                'admin-lastname',
                null,
                InputOption::VALUE_REQUIRED,
                'Admin last name',
            )
            ->addOption(
                'admin-email',
                null,
                InputOption::VALUE_REQUIRED,
                'Admin email address',
            )
            ->addOption(
                'admin-user',
                null,
                InputOption::VALUE_REQUIRED,
                'Admin username',
            )
            ->addOption(
                'admin-password',
                null,
                InputOption::VALUE_REQUIRED,
                'Admin password',
            )
            ->addOption(
                'base-url',
                null,
                InputOption::VALUE_REQUIRED,
                'Store base URL',
            )
            ->addOption(
                'language',
                null,
                InputOption::VALUE_REQUIRED,
                'Default language (en_US, en_GB, fr_FR, de_DE, es_ES)',
            )
            ->addOption(
                'currency',
                null,
                InputOption::VALUE_REQUIRED,
                'Default currency (USD, EUR, GBP)',
            )
            ->addOption(
                'timezone',
                null,
                InputOption::VALUE_REQUIRED,
                'Default timezone',
            )
            ->addOption(
                'sample-data',
                null,
                InputOption::VALUE_NONE,
                'Install sample data',
            )
            ->addOption(
                'elasticsearch',
                null,
                InputOption::VALUE_NONE,
                'Use Elasticsearch for search',
            )
            ->addOption(
                'elasticsearch-host',
                null,
                InputOption::VALUE_REQUIRED,
                'Elasticsearch host',
            )
            ->addOption(
                'elasticsearch-port',
                null,
                InputOption::VALUE_REQUIRED,
                'Elasticsearch port',
            )
            ->addOption(
                'redis',
                null,
                InputOption::VALUE_NONE,
                'Use Redis for caching',
            )
            ->addOption(
                'redis-host',
                null,
                InputOption::VALUE_REQUIRED,
                'Redis host',
            )
            ->addOption(
                'redis-port',
                null,
                InputOption::VALUE_REQUIRED,
                'Redis port',
            )
            ->addOption(
                'use-redis',
                null,
                InputOption::VALUE_NONE,
                'Enable Redis for caching and sessions',
            )
            ->addOption(
                'use-elasticsearch',
                null,
                InputOption::VALUE_NONE,
                'Enable Elasticsearch for search',
            )
            ->addOption(
                'use-meilisearch',
                null,
                InputOption::VALUE_NONE,
                'Enable Meilisearch for search',
            )
            ->addOption(
                'use-minio',
                null,
                InputOption::VALUE_NONE,
                'Enable MinIO for object storage',
            )
            ->addOption(
                'use-docker',
                null,
                InputOption::VALUE_NONE,
                'Use Docker for database (if available)',
            )
            ->addOption(
                'no-docker',
                null,
                InputOption::VALUE_NONE,
                'Do not use Docker for database',
            )
            ->addOption(
                'quiet',
                'q',
                InputOption::VALUE_NONE,
                'Minimal output (errors only, no spinners) - for CI/CD',
            )
            ->addOption(
                'json',
                'j',
                InputOption::VALUE_NONE,
                'Output result as JSON - for programmatic usage',
            )
            ->setHelp(
                <<<'HELP'
                The <info>create:app</info> command scaffolds a new PHP application.

                <comment>Examples:</comment>
                  <info>hive create:app admin</info>
                  <info>hive create:app shop --type=magento</info>
                  <info>hive create:app api --type=symfony</info>

                <comment>Magento with flags:</comment>
                  <info>hive create:app shop --type=magento \
                    --description="E-commerce store" \
                    --magento-version=2.4.7 \
                    --magento-public-key=YOUR_PUBLIC_KEY \
                    --magento-private-key=YOUR_PRIVATE_KEY \
                    --db-name=shop_db \
                    --db-user=shop_user \
                    --db-password=secret \
                    --admin-firstname=Admin \
                    --admin-lastname=User \
                    --admin-email=admin@example.com \
                    --admin-user=admin \
                    --admin-password=Admin123! \
                    --base-url=http://localhost/ \
                    --language=en_US \
                    --currency=USD \
                    --timezone=America/New_York</info>

                Available app types: laravel, symfony, magento, skeleton
                HELP
            );
    }

    /**
     * Execute the create app command.
     *
     * This method orchestrates the entire application scaffolding process:
     * 1. Runs preflight checks to validate environment
     * 2. Validates application name with smart suggestions
     * 3. Prompts for app type selection (if not provided)
     * 4. Collects configuration through app type prompts
     * 5. Creates the application directory
     * 6. Runs the app type's installation command
     * 7. Copies and processes stub template files
     * 8. Runs post-installation commands
     * 9. Displays next steps to the user
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
        $isQuiet = $input->getOption('quiet') === true;
        $isJson = $input->getOption('json') === true;
        $isVerbose = $input->getOption('verbose') === true;

        // Track app path for cleanup on failure
        $appPath = null;
        $appCreated = false;

        try {
            // Display intro banner (skip in quiet/json mode)
            // Step 1: Run preflight checks
            if (! $isQuiet && ! $isJson) {
                $this->intro('Application Creation');
                $this->info('Running environment checks...');
            }
            $preflightResult = $this->runPreflightChecks($isQuiet, $isJson);

            if ($preflightResult->failed()) {
                $this->displayPreflightErrors($preflightResult, $isQuiet, $isJson);

                return Command::FAILURE;
            }

            if (! $isQuiet && ! $isJson) {
                $this->line('');
            }

            // Step 2: Get and validate application name with smart suggestions
            $name = $this->getValidatedAppName($input, $isQuiet, $isJson);

            // Step 3: APP TYPE SELECTION
            $typeOption = $input->getOption('type');
            if ($typeOption !== null && $typeOption !== '') {
                $appTypeId = $typeOption;

                // Validate the provided app type
                if (! $this->appTypeFactory()->isValid($appTypeId)) {
                    $errorMsg = "Invalid app type: {$appTypeId}";
                    if ($isJson) {
                        $this->outputJson([
                            'success' => false,
                            'error' => $errorMsg,
                            'available_types' => $this->appTypeFactory()->getIdentifiers(),
                        ]);
                    } else {
                        $this->error($errorMsg);
                        $this->line('Available types: ' . implode(', ', $this->appTypeFactory()->getIdentifiers()));
                    }

                    return Command::FAILURE;
                }
            } else {
                // Prompt user to select app type
                $appTypeId = $this->select(
                    label: 'Select application type',
                    options: AppTypeFactory::choices()
                );
            }

            // Create the app type instance
            $appType = $this->appTypeFactory()->create($appTypeId);
            // Step 4: CONFIGURATION COLLECTION
            if (! $isQuiet && ! $isJson) {
                $this->comment("Selected: {$appType->getName()}");
                $this->line('');
                $this->comment('Configuration:');
            }

            // Set name in input so app types don't prompt for it
            // (they should read from input argument if available)
            $input->setArgument('name', $name);

            // Set description in input if provided via option
            $descriptionOption = $input->getOption('description');
            if ($descriptionOption !== null && $descriptionOption !== '') {
                $input->setOption('description', $descriptionOption);
            }

            $config = $appType->collectConfiguration($input, $output);

            // Ensure name is set from command argument (override any prompts)
            $config[AppTypeInterface::CONFIG_NAME] = $name;

            // Set description from option if provided, otherwise use collected value or default
            if ($descriptionOption !== null && $descriptionOption !== '') {
                $config[AppTypeInterface::CONFIG_DESCRIPTION] = $descriptionOption;
            } elseif (! isset($config[AppTypeInterface::CONFIG_DESCRIPTION]) || $config[AppTypeInterface::CONFIG_DESCRIPTION] === '') {
                $config[AppTypeInterface::CONFIG_DESCRIPTION] = "A {$appType->getName()} application";
            }

            // Step 5: Execute application creation with progress feedback
            $root = $this->getMonorepoRoot();
            $appPath = "{$root}/apps/{$name}";
            $appsDir = "{$root}/apps";
            $filesystem = $this->filesystem();

            // Mark that app directory will be created
            $appCreated = true;

            $steps = [
                'Installing application framework' => fn (): bool => $this->runInstallCommand($appType, $config, $appsDir, $isVerbose),
                'Setting up infrastructure' => fn (): bool => $this->setupInfrastructure($appType, $appPath, $name, $isVerbose),
                'Processing configuration files' => fn () => $this->processStubs($appType, $config, $appPath, $filesystem),
                'Running additional setup tasks' => fn (): bool => $this->runPostInstallCommands($appType, $config, $appPath, $isVerbose),
            ];

            if (! $isQuiet && ! $isJson) {
                $this->line('');
            }

            $startTime = microtime(true);
            foreach ($steps as $message => $step) {
                $stepStartTime = microtime(true);

                if ($isQuiet || $isJson) {
                    // No spinner in quiet/json mode
                    $result = $step();
                } else {
                    $result = $this->spin($step, "{$message}...");
                }

                if ($result === false) {
                    if ($isJson) {
                        $this->outputJson([
                            'success' => false,
                            'error' => "Failed: {$message}",
                            'app_name' => $name,
                            'app_type' => $appType->getName(),
                        ]);
                    }

                    return Command::FAILURE;
                }

                $stepDuration = microtime(true) - $stepStartTime;

                if (! $isQuiet && ! $isJson) {
                    $this->comment("✓ {$message} complete");
                } elseif ($isVerbose && ! $isJson) {
                    $this->comment(sprintf('✓ %s complete (%.2fs)', $message, $stepDuration));
                }
            }
            $totalDuration = microtime(true) - $startTime;

            // Step 6: Display success summary
            $this->displaySuccessMessage(
                'application',
                $name,
                $appPath,
                $totalDuration,
                [
                    "cd apps/{$name}",
                    'Review the generated files',
                    "hive dev --workspace={$name}",
                ],
                $isQuiet,
                $isJson,
                $isVerbose
            );

            return Command::SUCCESS;
        } catch (Exception $exception) {
            // Cleanup on failure or cancellation
            if ($appCreated && $appPath !== null) {
                if (! $isQuiet && ! $isJson) {
                    $this->line('');
                    $this->warning('Cleaning up failed application...');
                }

                $this->cleanupFailedWorkspace($appPath, $isQuiet, $isJson);
            }

            // Display error message
            if ($isJson) {
                $this->outputJson([
                    'success' => false,
                    'error' => $exception->getMessage(),
                ]);
            } else {
                $this->error('Application creation failed: ' . $exception->getMessage());
            }

            return Command::FAILURE;
        }
    }

    /**
     * Get and validate application name with smart suggestions.
     *
     * @return string Validated application name
     */
    private function getValidatedAppName(InputInterface $input, bool $isQuiet, bool $isJson): string
    {
        $name = $input->getArgument('name');

        // Validate the name format first (inline validation)
        if (! is_string($name) || $name === '') {
            $errorMsg = 'Application name is required';
            if ($isJson) {
                $this->outputJson([
                    'success' => false,
                    'error' => $errorMsg,
                ]);
            } else {
                $this->error($errorMsg);
            }
            exit(Command::FAILURE);
        }

        if (preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $name) !== 1) {
            $errorMsg = 'Application name must be lowercase alphanumeric with hyphens (e.g., my-app)';
            if ($isJson) {
                $this->outputJson([
                    'success' => false,
                    'error' => $errorMsg,
                ]);
            } else {
                $this->error($errorMsg);
            }
            exit(Command::FAILURE);
        }

        $root = $this->getMonorepoRoot();
        $appPath = "{$root}/apps/{$name}";

        // Check if name is available
        if (! $this->checkDirectoryExists($name, $appPath, 'application', $isQuiet, $isJson)) {
            return $name;
        }

        // Name is taken, offer suggestions
        if (! $isQuiet && ! $isJson) {
            $this->line('');
        }

        $nameSuggestionService = NameSuggestionService::make();
        $suggestions = $nameSuggestionService->suggest(
            $name,
            'app',
            fn (?string $suggestedName): bool => is_string($suggestedName) && $suggestedName !== '' && preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $suggestedName) === 1 && ! $this->filesystem()->isDirectory("{$root}/apps/{$suggestedName}")
        );

        if ($suggestions === []) {
            $errorMsg = 'Could not generate alternative names. Please choose a different name.';
            if ($isJson) {
                $this->outputJson([
                    'success' => false,
                    'error' => $errorMsg,
                ]);
            } else {
                $this->error($errorMsg);
            }
            exit(Command::FAILURE);
        }

        // Get the best suggestion
        $bestSuggestion = $nameSuggestionService->getBestSuggestion($suggestions);

        // Display suggestions with recommendation
        if (! $isQuiet && ! $isJson) {
            $this->comment('Suggested names:');
            $index = 1;
            foreach ($suggestions as $suggestion) {
                $marker = $suggestion === $bestSuggestion ? ' (recommended)' : '';
                $this->line("  {$index}. {$suggestion}{$marker}");
                $index++;
            }

            $this->line('');
        }

        // Let user select or enter custom name with best suggestion pre-filled
        $choice = $this->suggest(
            label: 'Choose an available name',
            options: $suggestions,
            placeholder: $bestSuggestion ?? 'Enter a custom name',
            default: $bestSuggestion ?? '',
            required: true
        );

        // Validate the chosen name format (inline validation)
        if (! is_string($choice) || $choice === '' || preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $choice) !== 1) {
            $errorMsg = 'Invalid application name format';
            if ($isJson) {
                $this->outputJson([
                    'success' => false,
                    'error' => $errorMsg,
                ]);
            } else {
                $this->error($errorMsg);
            }
            exit(Command::FAILURE);
        }

        // Validate the chosen name availability
        $chosenPath = "{$root}/apps/{$choice}";
        if ($this->filesystem()->isDirectory($chosenPath)) {
            $errorMsg = "Application '{$choice}' also exists. Please try again with a different name.";
            if ($isJson) {
                $this->outputJson([
                    'success' => false,
                    'error' => $errorMsg,
                ]);
            } else {
                $this->error($errorMsg);
            }
            exit(Command::FAILURE);
        }

        if (! $isQuiet && ! $isJson) {
            $this->info("✓ Application name '{$choice}' is available");
        }

        return $choice;
    }

    /**
     * Run installation command.
     *
     * @param  AppTypeInterface     $appType   App type instance
     * @param  array<string, mixed> $config    Configuration
     * @param  string               $appsDir   Apps directory path (parent of app)
     * @param  bool                 $isVerbose Show verbose output
     * @return bool                 True on success
     */
    private function runInstallCommand(AppTypeInterface $appType, array $config, string $appsDir, bool $isVerbose): bool
    {
        $installCommand = $appType->getInstallCommand($config);
        if ($installCommand === '') {
            return true;
        }

        if ($isVerbose) {
            $this->comment("  Executing: {$installCommand}");
        }

        // Run composer create-project from apps directory
        // This allows it to create the app subdirectory
        $exitCode = $this->executeCommand($installCommand, $appsDir);
        if ($exitCode !== 0) {
            $this->error('Installation command failed');

            return false;
        }

        return true;
    }

    /**
     * Setup infrastructure (database, Redis, etc.) after app is created.
     *
     * @param  AppTypeInterface $appType   App type instance
     * @param  string           $appPath   Application path
     * @param  string           $appName   Application name
     * @param  bool             $isVerbose Show verbose output
     * @return bool             True on success
     */
    private function setupInfrastructure(AppTypeInterface $appType, string $appPath, string $appName, bool $isVerbose): bool
    {
        // Call the app type's infrastructure setup method
        // This will prompt for and configure database, Redis, queues, etc.
        try {
            $infraOptions = match ($appType->getName()) {
                AppType::SYMFONY->getName() => [
                    'needsDatabase' => true,
                    'databases' => [DatabaseType::MYSQL, DatabaseType::POSTGRESQL, DatabaseType::SQLITE],
                    'needsCache' => true,
                    'needsQueue' => true,
                    'needsSearch' => false,
                    'needsStorage' => false,
                ],
                AppType::LARAVEL->getName() => [
                    'needsDatabase' => true,
                    'databases' => [DatabaseType::MYSQL, DatabaseType::POSTGRESQL, DatabaseType::SQLITE],
                    'needsCache' => true,
                    'needsQueue' => true,
                    'needsSearch' => false,
                    'needsStorage' => false,
                ],
                AppType::MAGENTO->getName() => [
                    'needsDatabase' => true,
                    'databases' => [DatabaseType::MYSQL, DatabaseType::MARIADB],
                    'needsCache' => true,
                    'needsQueue' => true,
                    'needsSearch' => true,
                    'needsStorage' => false,
                ],
                default => [],
            };

            if ($infraOptions !== []) {
                $appType->setupInfrastructure($appName, $appPath, $infraOptions);
            }

            return true;
        } catch (Exception $exception) {
            if ($isVerbose) {
                $this->error("Infrastructure setup failed: {$exception->getMessage()}");
            }

            return false;
        }
    }

    /**
     * Run post-installation commands.
     *
     * @param  AppTypeInterface     $appType   App type instance
     * @param  array<string, mixed> $config    Configuration
     * @param  string               $appPath   Application path
     * @param  bool                 $isVerbose Show verbose output
     * @return bool                 True on success (warnings don't fail)
     */
    private function runPostInstallCommands(AppTypeInterface $appType, array $config, string $appPath, bool $isVerbose): bool
    {
        $postCommands = $appType->getPostInstallCommands($config);
        if ($postCommands === []) {
            return true;
        }

        foreach ($postCommands as $postCommand) {
            if ($isVerbose) {
                $this->comment("  Executing: {$postCommand}");
            }

            $exitCode = $this->executeCommand($postCommand, $appPath);
            if ($exitCode !== 0) {
                // Log warning but continue
                $this->warning("Command had issues: {$postCommand}");
            }
        }

        return true;
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
        if (! $this->filesystem()->isDirectory($stubPath)) {
            $this->warning("Stub directory not found: {$stubPath}");

            return;
        }

        // Get stub variables for replacement
        $variables = $appType->getStubVariables($config);

        // Get all stub files recursively
        $stubFiles = $this->getStubFiles($stubPath);

        // Set base path for Stub facade
        Stub::setBasePath($stubPath);

        // Process each stub file
        foreach ($stubFiles as $stubFile) {
            // Get relative path from stub directory
            $relativePath = str_replace($stubPath . '/', '', $stubFile);

            // Check if this is an append stub
            $isAppendStub = str_ends_with($relativePath, '.append.stub');

            // Remove .stub or .append.stub extension for target file
            $targetPath = $isAppendStub ? str_replace('.append.stub', '', $relativePath) : str_replace('.stub', '', $relativePath);

            try {
                // Handle append vs replace
                if ($isAppendStub) {
                    $targetFile = "{$appPath}/{$targetPath}";

                    // Create directory if it doesn't exist
                    $targetDir = dirname($targetFile);
                    if (! $this->filesystem()->isDirectory($targetDir)) {
                        $filesystem->makeDirectory($targetDir, 0755, true);
                    }

                    if ($filesystem->exists($targetFile)) {
                        // Append to existing file
                        $existingContent = $filesystem->read($targetFile);
                        $newContent = Stub::create($relativePath, $variables)->render();
                        $filesystem->write($targetFile, $existingContent . $newContent);
                    } else {
                        // File doesn't exist, create it with stub content
                        Stub::create($relativePath, $variables)->saveTo($appPath, $targetPath);
                    }
                } else {
                    // Create parent directory if it doesn't exist
                    $targetFile = "{$appPath}/{$targetPath}";
                    $targetDir = dirname($targetFile);
                    if (! $this->filesystem()->isDirectory($targetDir)) {
                        $filesystem->makeDirectory($targetDir, 0755, true);
                    }

                    // Write/overwrite the file using Stub facade
                    Stub::create($relativePath, $variables)->saveTo($appPath, $targetPath);
                }
            } catch (StubNotFoundException $e) {
                $this->error("Stub file not found: {$relativePath}");
                $this->error($e->getMessage());
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
            if ($this->filesystem()->isDirectory($path)) {
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
