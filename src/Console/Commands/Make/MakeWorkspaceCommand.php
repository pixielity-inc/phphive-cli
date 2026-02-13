<?php

declare(strict_types=1);

namespace PhpHive\Cli\Console\Commands\Make;

use Exception;

use function exec;
use function is_array;
use function json_decode;
use function json_encode;

use const JSON_PRETTY_PRINT;
use const JSON_UNESCAPED_SLASHES;

use Override;
use PhpHive\Cli\Services\NameSuggestionService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

/**
 * Make Workspace Command.
 *
 * This command creates a new monorepo workspace by cloning the official
 * PhpHive template repository from GitHub. The template includes:
 * - Pre-configured Turborepo setup
 * - Sample application and package
 * - Complete monorepo structure
 * - All necessary configuration files
 *
 * The workspace creation process:
 * 1. Prompts for workspace name (or uses provided argument)
 * 2. Clones the template repository from GitHub
 * 3. Updates configuration with the new workspace name
 * 4. Initializes a fresh git repository
 * 5. Provides next steps for getting started
 *
 * Created structure:
 * ```
 * workspace-name/
 * ├── apps/                    # Application workspaces
 * │   └── sample-app/         # Sample skeleton app
 * ├── packages/                # Package workspaces
 * │   └── sample-package/     # Sample package
 * ├── bin/                     # Executable scripts
 * │   └── hive                # Hive CLI wrapper
 * ├── turbo.json               # Turborepo configuration
 * ├── pnpm-workspace.yaml      # pnpm workspace configuration
 * ├── package.json             # Root package.json
 * ├── composer.json            # Root composer.json
 * ├── .gitignore               # Git ignore patterns
 * └── README.md                # Project documentation
 * ```
 *
 * Features:
 * - Clones from official template repository
 * - Interactive prompts for workspace name
 * - Non-interactive mode support (--no-interaction)
 * - Automatic configuration updates
 * - Fresh git repository initialization
 * - Validation of workspace name
 * - Directory existence checks
 * - Beautiful CLI output with Laravel Prompts
 *
 * Common options inherited from BaseCommand:
 * - --no-interaction, -n: Run in non-interactive mode
 *
 * Example usage:
 * ```bash
 * # Interactive mode (prompts for name)
 * hive make:workspace
 *
 * # With workspace name
 * hive make:workspace my-project
 *
 * # Non-interactive mode
 * hive make:workspace my-project --no-interaction
 * ```
 *
 * After running this command:
 * 1. cd into the new workspace directory
 * 2. Run `pnpm install` to install Node dependencies
 * 3. Run `composer install` to install PHP dependencies
 * 4. Start developing!
 *
 * @see BaseCommand For inherited functionality and common options
 * @see CreateAppCommand For creating apps within workspace
 * @see CreatePackageCommand For creating packages within workspace
 */
#[AsCommand(
    name: 'make:workspace',
    description: 'Create a new workspace from template',
    aliases: ['init', 'new'],
)]
final class MakeWorkspaceCommand extends BaseMakeCommand
{
    /**
     * Template repository URL.
     */
    private const string TEMPLATE_URL = 'https://github.com/pixielity-inc/hive-template.git';

    /**
     * Configure the command options and arguments.
     *
     * Inherits common options from BaseCommand and defines workspace-specific
     * argument for the workspace name.
     */
    #[Override]
    protected function configure(): void
    {
        parent::configure();

        $this
            ->addArgument(
                'name',
                InputArgument::OPTIONAL,
                'Workspace name (e.g., my-project)',
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
                The <info>make:workspace</info> command creates a new workspace from the official template.

                <comment>Examples:</comment>
                  <info>hive make:workspace</info>                    Interactive mode
                  <info>hive make:workspace my-project</info>         With name
                  <info>hive make:workspace my-project -n</info>      Non-interactive

                The template includes:
                  - Sample app and package
                  - Turborepo configuration
                  - Complete monorepo structure
                  - All necessary config files

                After creation, run:
                  <info>cd workspace-name</info>
                  <info>pnpm install</info>
                  <info>composer install</info>
                HELP,
            );
    }

    /**
     * Execute the make:workspace command.
     *
     * This method orchestrates the entire workspace creation process:
     * 1. Runs preflight checks to validate environment
     * 2. Gets workspace name with smart suggestions if taken
     * 3. Validates the name
     * 4. Clones the template repository
     * 5. Updates configuration with new name
     * 6. Initializes fresh git repository
     * 7. Displays success message with next steps
     *
     * @param  InputInterface  $input   Command input (arguments and options)
     * @param  OutputInterface $_output Command output (for displaying messages)
     * @return int             Exit code (0 for success, 1 for failure)
     */
    protected function execute(InputInterface $input, OutputInterface $_output): int
    {
        $isQuiet = $input->getOption('quiet') === true;
        $isJson = $input->getOption('json') === true;
        $isVerbose = $input->getOption('verbose') === true;

        // Track workspace path for cleanup on failure
        $workspacePath = null;
        $workspaceCreated = false;

        // Register signal handlers and subscribe to cleanup events
        $this->registerSignalHandlers();
        $this->bindEvent('signal.interrupt', function () use (&$workspacePath, &$workspaceCreated, $isQuiet, $isJson): void {
            if (! $isQuiet && ! $isJson) {
                $this->line('');
                $this->warning('Operation cancelled by user.');
            }

            if ($workspaceCreated && $workspacePath !== null) {
                if (! $isQuiet && ! $isJson) {
                    $this->warning('Cleaning up...');
                }
                $this->cleanupFailedWorkspace($workspacePath, $isQuiet, $isJson);
            }
        });
        $this->bindEvent('signal.terminate', function () use (&$workspacePath, &$workspaceCreated, $isQuiet, $isJson): void {
            if (! $isQuiet && ! $isJson) {
                $this->line('');
                $this->warning('Operation terminated.');
            }

            if ($workspaceCreated && $workspacePath !== null) {
                if (! $isQuiet && ! $isJson) {
                    $this->warning('Cleaning up...');
                }
                $this->cleanupFailedWorkspace($workspacePath, $isQuiet, $isJson);
            }
        });

        try {
            // Display intro banner (skip in quiet/json mode)
            // Step 1: Run preflight checks
            if (! $isQuiet && ! $isJson) {
                $this->intro('Create New Workspace');
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

            // Step 2: Get and validate workspace name
            $name = $this->getValidatedWorkspaceName($input, $isQuiet, $isJson);

            // Track workspace path for cleanup
            $workspacePath = getcwd() . "/{$name}";
            $workspaceCreated = true;

            // Step 3: Execute workspace creation with progress feedback
            $steps = [
                'Cloning workspace template' => fn (): int => $this->cloneTemplate($name, $isVerbose),
                'Configuring workspace' => fn (): bool => $this->updateWorkspaceConfig($name),
            ];

            if (! $isQuiet && ! $isJson) {
                $this->line('');
                $this->info("Creating workspace: {$name}");
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

                if ($result !== 0 && $result !== true) {
                    if ($isJson) {
                        $this->outputJson([
                            'success' => false,
                            'error' => "Failed: {$message}",
                            'workspace_name' => $name,
                        ]);
                    } else {
                        $this->error("Failed: {$message}");
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

            // Step 4: Display success summary
            $this->displaySuccessMessage(
                'workspace',
                $name,
                getcwd() . "/{$name}",
                $totalDuration,
                [
                    "cd {$name}",
                    'pnpm install',
                    'composer install',
                    'Start building!',
                ],
                $isQuiet,
                $isJson,
                $isVerbose
            );

            return Command::SUCCESS;
        } catch (Exception $exception) {
            // Cleanup on failure or cancellation
            if ($workspaceCreated && $workspacePath !== null) {
                if (! $isQuiet && ! $isJson) {
                    $this->line('');
                    $this->warning('Cleaning up failed workspace...');
                }

                $this->cleanupFailedWorkspace($workspacePath, $isQuiet, $isJson);
            }

            // Display error message
            if ($isJson) {
                $this->outputJson([
                    'success' => false,
                    'error' => $exception->getMessage(),
                ]);
            } else {
                $this->error('Workspace creation failed: ' . $exception->getMessage());
            }

            return Command::FAILURE;
        }
    }

    /**
     * Get and validate workspace name with smart suggestions.
     *
     * @return string Validated workspace name
     */
    private function getValidatedWorkspaceName(InputInterface $input, bool $isQuiet, bool $isJson): string
    {
        // Get workspace name from argument or prompt
        $name = $this->argument('name');

        if (($name === null || $name === '') && ! $input->isInteractive()) {
            // Non-interactive mode without name - error
            $errorMsg = 'Workspace name is required in non-interactive mode';
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

        if ($name === null || $name === '') {
            // Interactive mode - prompt for name
            $name = $this->text(
                label: 'What is the workspace name?',
                placeholder: 'my-project',
                required: true,
                validate: fn ($value) => (is_string($value) && $value !== '' && preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $value) === 1) ? null : 'Workspace name must be lowercase alphanumeric with hyphens (e.g., my-project)',
            );
        }

        // Validate workspace name (inline validation)
        if (! is_string($name) || $name === '' || preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $name) !== 1) {
            $errorMsg = 'Workspace name must be lowercase alphanumeric with hyphens (e.g., my-project)';
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

        // Check if directory already exists
        if (! $this->checkDirectoryExists($name, $name, 'workspace', $isQuiet, $isJson)) {
            return $name;
        }

        // Name is taken, offer suggestions
        if (! $isQuiet && ! $isJson) {
            $this->line('');
        }

        $nameSuggestionService = NameSuggestionService::make();
        $suggestions = $nameSuggestionService->suggest(
            $name,
            'workspace',
            fn (?string $suggestedName): bool => is_string($suggestedName) && $suggestedName !== '' && ! $this->filesystem()->isDirectory($suggestedName)
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

        // Validate the chosen name
        if ($this->filesystem()->isDirectory($choice)) {
            $errorMsg = "Directory '{$choice}' also exists. Please try again with a different name.";
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
            $this->info("✓ Workspace name '{$choice}' is available");
        }

        return $choice;
    }

    /**
     * Clone the template repository from GitHub.
     *
     * Clones the official PhpHive template repository and removes the .git
     * directory to allow for a fresh git initialization.
     *
     * @param  string $name      Workspace name (directory to clone into)
     * @param  bool   $isVerbose Show verbose output
     * @return int    Exit code (0 for success, non-zero for failure)
     */
    private function cloneTemplate(string $name, bool $isVerbose): int
    {
        // Clone the template repository
        $cloneCommand = 'git clone ' . self::TEMPLATE_URL . " {$name}";

        if ($isVerbose) {
            $this->comment("  Executing: {$cloneCommand}");
        }

        $process = Process::fromShellCommandline(
            $cloneCommand,
            null,
            null,
            null,
            300 // 5 minute timeout
        );

        if ($isVerbose) {
            // Show output in verbose mode
            $process->run(function ($type, $buffer): void {
                echo $buffer;
            });
        } else {
            $process->run();
        }

        // Remove .git directory to start fresh
        if ($process->isSuccessful()) {
            $rmCommand = "rm -rf {$name}/.git";
            if ($isVerbose) {
                $this->comment("  Executing: {$rmCommand}");
            }
            $process = Process::fromShellCommandline($rmCommand);
            $process->run();
        }

        return $process->getExitCode() ?? 1;
    }

    /**
     * Update workspace configuration with the new name.
     *
     * Updates package.json and composer.json with the new workspace name,
     * then initializes a fresh git repository.
     *
     * @param  string $name Workspace name
     * @return bool   True on success
     */
    private function updateWorkspaceConfig(string $name): bool
    {
        try {
            $filesystem = $this->filesystem();

            // Update package.json
            $packageJsonPath = "{$name}/package.json";
            if ($filesystem->exists($packageJsonPath)) {
                $content = $filesystem->read($packageJsonPath);
                $packageJson = json_decode($content, true);
                if (is_array($packageJson)) {
                    $packageJson['name'] = $name;
                    $filesystem->write(
                        $packageJsonPath,
                        json_encode($packageJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
                    );
                }
            }

            // Update composer.json
            $composerJsonPath = "{$name}/composer.json";
            if ($filesystem->exists($composerJsonPath)) {
                $content = $filesystem->read($composerJsonPath);
                $composerJson = json_decode($content, true);
                if (is_array($composerJson)) {
                    // Update name to vendor/package format
                    $composerJson['name'] = "phphive/{$name}";
                    $filesystem->write(
                        $composerJsonPath,
                        json_encode($composerJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
                    );
                }
            }

            // Initialize new git repository
            exec("cd {$name} && git init && git add . && git commit -m 'Initial commit from hive-template' 2>&1");

            return true;
        } catch (Exception) {
            return false;
        }
    }
}
