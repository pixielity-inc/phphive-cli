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
        $this->setupCleanupHandlers($workspacePath, $workspaceCreated, $isQuiet, $isJson);

        try {
            // Step 1: Display intro and run preflight checks
            $this->displayIntro($isQuiet, $isJson);
            if (! $this->checkEnvironment($isQuiet, $isJson)) {
                return Command::FAILURE;
            }

            // Step 2: Get and validate workspace name
            $name = $this->getValidatedWorkspaceName($input, $isQuiet, $isJson);

            // Step 3: Execute workspace creation
            $workspacePath = getcwd() . "/{$name}";
            $workspaceCreated = true;

            $totalDuration = $this->executeCreationSteps($name, $isQuiet, $isJson, $isVerbose);

            // Step 4: Display success summary
            $this->displaySuccessMessage(
                'workspace',
                $name,
                $workspacePath,
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
            return $this->handleFailure($exception, $workspacePath, $workspaceCreated, $isQuiet, $isJson);
        }
    }

    /**
     * Setup cleanup handlers for signal interruption (Ctrl+C, SIGTERM).
     *
     * This method registers signal handlers using the Emitter pattern to ensure
     * proper cleanup when the user cancels the operation or the process is terminated.
     * It subscribes to two events:
     * - signal.interrupt: Triggered when user presses Ctrl+C (SIGINT)
     * - signal.terminate: Triggered when process receives SIGTERM
     *
     * Both handlers perform the same cleanup logic:
     * 1. Display cancellation/termination message (unless in quiet/json mode)
     * 2. Check if workspace directory was created
     * 3. Call cleanupFailedWorkspace() to remove the workspace directory
     *
     * The handlers use closure variable references (&$workspacePath, &$workspaceCreated)
     * to access the current state of the creation process, allowing them to determine
     * if cleanup is necessary.
     *
     * @param string|null &$workspacePath    Reference to workspace path (updated during creation)
     * @param bool        &$workspaceCreated Reference to creation flag (set to true when workspace dir is created)
     * @param bool        $isQuiet           Suppress output messages
     * @param bool        $isJson            Output in JSON format
     */
    private function setupCleanupHandlers(?string &$workspacePath, bool &$workspaceCreated, bool $isQuiet, bool $isJson): void
    {
        // Register global signal handlers (SIGINT, SIGTERM)
        $this->registerSignalHandlers();

        // Subscribe to SIGINT event (Ctrl+C)
        $this->bindEvent('signal.interrupt', function () use (&$workspacePath, &$workspaceCreated, $isQuiet, $isJson): void {
            // Display cancellation message
            if (! $isQuiet && ! $isJson) {
                $this->line('');
                $this->warning('Operation cancelled by user.');
            }

            // Cleanup if workspace directory was created
            if ($workspaceCreated && $workspacePath !== null) {
                if (! $isQuiet && ! $isJson) {
                    $this->warning('Cleaning up...');
                }
                $this->cleanupFailedWorkspace($workspacePath, $isQuiet, $isJson);
            }
        });

        // Subscribe to SIGTERM event (process termination)
        $this->bindEvent('signal.terminate', function () use (&$workspacePath, &$workspaceCreated, $isQuiet, $isJson): void {
            // Display termination message
            if (! $isQuiet && ! $isJson) {
                $this->line('');
                $this->warning('Operation terminated.');
            }

            // Cleanup if workspace directory was created
            if ($workspaceCreated && $workspacePath !== null) {
                if (! $isQuiet && ! $isJson) {
                    $this->warning('Cleaning up...');
                }
                $this->cleanupFailedWorkspace($workspacePath, $isQuiet, $isJson);
            }
        });
    }

    /**
     * Display intro banner and environment check message.
     *
     * Shows a welcoming banner with the command title and indicates that
     * environment checks are being performed. This provides visual feedback
     * to the user that the command has started and is validating the system.
     *
     * Output is suppressed in quiet mode (for CI/CD) and JSON mode (for
     * programmatic usage).
     *
     * @param bool $isQuiet Suppress all output
     * @param bool $isJson  Output in JSON format
     */
    private function displayIntro(bool $isQuiet, bool $isJson): void
    {
        // Skip intro in quiet/json mode
        if (! $isQuiet && ! $isJson) {
            $this->intro('Create New Workspace');
            $this->info('Running environment checks...');
        }
    }

    /**
     * Check environment with preflight checks.
     *
     * Validates that the development environment meets all requirements for
     * creating a workspace. This includes checking for:
     * - Required tools (PHP, Composer, Git, Node.js, pnpm)
     * - Correct versions of dependencies
     * - Proper system configuration
     * - Available disk space
     *
     * If any checks fail, error messages are displayed and the method returns
     * false to halt the creation process.
     *
     * @param  bool $isQuiet Suppress output messages
     * @param  bool $isJson  Output in JSON format
     * @return bool True if all checks passed, false otherwise
     */
    private function checkEnvironment(bool $isQuiet, bool $isJson): bool
    {
        // Run all preflight checks
        $preflightResult = $this->runPreflightChecks($isQuiet, $isJson);

        // Display errors if any checks failed
        if ($preflightResult->failed()) {
            $this->displayPreflightErrors($preflightResult, $isQuiet, $isJson);

            return false;
        }

        // Add spacing after checks (visual separation)
        if (! $isQuiet && ! $isJson) {
            $this->line('');
        }

        return true;
    }

    /**
     * Execute creation steps with progress feedback.
     *
     * Orchestrates the workspace creation workflow by executing a series
     * of steps in sequence. Each step is a closure that performs a specific task
     * and returns a value indicating success/failure.
     *
     * Creation steps:
     * 1. Cloning workspace template
     *    - Clones the official PhpHive template repository from GitHub
     *    - URL: https://github.com/pixielity-inc/hive-template.git
     *    - Includes sample app, sample package, and complete monorepo structure
     *    - Removes .git directory to allow fresh git initialization
     *
     * 2. Configuring workspace
     *    - Updates package.json with workspace name
     *    - Updates composer.json with workspace name (phphive/{name})
     *    - Initializes new git repository
     *    - Creates initial commit
     *
     * Progress feedback:
     * - In normal mode: Shows spinner with step message
     * - In quiet/json mode: Executes silently
     * - In verbose mode: Shows step duration
     *
     * @param  string $name      Workspace name
     * @param  bool   $isQuiet   Suppress output messages
     * @param  bool   $isJson    Output in JSON format
     * @param  bool   $isVerbose Show detailed output
     * @return float  Total duration in seconds
     */
    private function executeCreationSteps(
        string $name,
        bool $isQuiet,
        bool $isJson,
        bool $isVerbose
    ): float {
        // Define creation steps as closures
        $steps = [
            'Cloning workspace template' => fn (): int => $this->cloneTemplate($name, $isVerbose),
            'Configuring workspace' => fn (): bool => $this->updateWorkspaceConfig($name),
        ];

        // Display workspace name being created
        if (! $isQuiet && ! $isJson) {
            $this->info("Creating workspace: {$name}");
            $this->line('');
        }

        // Track total duration
        $startTime = microtime(true);

        // Execute each step in sequence
        foreach ($steps as $message => $step) {
            $this->executeStep($step, $message, $name, $isQuiet, $isJson, $isVerbose);
        }

        return microtime(true) - $startTime;
    }

    /**
     * Execute a single creation step.
     *
     * Runs a single step in the creation workflow and provides progress feedback.
     * The step is executed as a closure that returns a value indicating success.
     *
     * Execution flow:
     * 1. Record start time for duration tracking
     * 2. Execute step (with or without spinner based on mode)
     * 3. Check result - throw exception if step failed
     * 4. Calculate step duration
     * 5. Display completion message with optional duration
     *
     * Success criteria:
     * - Step returns 0 (success exit code) or true (boolean success)
     * - Any other value is considered a failure
     *
     * Progress feedback modes:
     * - Normal mode: Shows spinner during execution, completion message after
     * - Quiet/JSON mode: Executes silently, no output
     * - Verbose mode: Shows completion message with duration
     *
     * Error handling:
     * - If step fails, throws Exception with step message
     * - Exception is caught by execute() method's try-catch block
     * - Triggers cleanup and displays error message
     *
     * @param callable $step      Step closure to execute
     * @param string   $message   Step description for display
     * @param string   $name      Workspace name (for error messages)
     * @param bool     $isQuiet   Suppress output messages
     * @param bool     $isJson    Output in JSON format
     * @param bool     $isVerbose Show detailed output
     *
     * @throws Exception If step fails (returns non-zero/non-true value)
     */
    private function executeStep(
        callable $step,
        string $message,
        string $name,
        bool $isQuiet,
        bool $isJson,
        bool $isVerbose
    ): void {
        // Track step duration
        $stepStartTime = microtime(true);

        // Execute step (with or without spinner)
        if ($isQuiet || $isJson) {
            // Silent execution for CI/CD and programmatic usage
            $result = $step();
        } else {
            // Show spinner during execution
            $result = $this->spin($step, "{$message}...");
        }

        // Check if step failed (not 0 and not true)
        if ($result !== 0 && $result !== true) {
            // Output error in JSON format if requested
            if ($isJson) {
                $this->outputJson([
                    'success' => false,
                    'error' => "Failed: {$message}",
                    'workspace_name' => $name,
                ]);
            } else {
                $this->error("Failed: {$message}");
            }

            // Throw exception to trigger cleanup
            throw new Exception("Failed: {$message}");
        }

        // Calculate step duration
        $stepDuration = microtime(true) - $stepStartTime;

        // Display completion message
        if (! $isQuiet && ! $isJson) {
            $this->comment("✓ {$message} complete");
        } elseif ($isVerbose && ! $isJson) {
            // Show duration in verbose mode
            $this->comment(sprintf('✓ %s complete (%.2fs)', $message, $stepDuration));
        }
    }

    /**
     * Handle command failure.
     *
     * Handles exceptions that occur during workspace creation by performing
     * cleanup and displaying appropriate error messages.
     *
     * Cleanup process:
     * 1. Check if workspace directory was created
     * 2. Display cleanup message (unless in quiet/json mode)
     * 3. Call cleanupFailedWorkspace() to delete the workspace directory
     *
     * Error message display:
     * - JSON mode: Outputs structured error with success=false
     * - Normal mode: Displays error message with exception details
     *
     * This method is called from the execute() method's catch block when any
     * exception occurs during the creation process, including:
     * - Step failures (from executeStep throwing Exception)
     * - User cancellation (Ctrl+C via signal handlers)
     * - Unexpected errors (git clone failure, file system errors, etc.)
     *
     * Common failure scenarios:
     * - Git clone fails (network issues, invalid repository)
     * - Directory already exists
     * - Insufficient permissions
     * - Disk space issues
     *
     * @param  Exception   $exception        The exception that caused the failure
     * @param  string|null $workspacePath    Path to workspace directory (null if not created yet)
     * @param  bool        $workspaceCreated Whether workspace directory was created
     * @param  bool        $isQuiet          Suppress output messages
     * @param  bool        $isJson           Output in JSON format
     * @return int         Command::FAILURE exit code
     */
    private function handleFailure(
        Exception $exception,
        ?string $workspacePath,
        bool $workspaceCreated,
        bool $isQuiet,
        bool $isJson
    ): int {
        // Cleanup on failure or cancellation
        if ($workspaceCreated && $workspacePath !== null) {
            // Display cleanup message
            if (! $isQuiet && ! $isJson) {
                $this->line('');
                $this->warning('Cleaning up failed workspace...');
            }

            // Remove workspace directory
            $this->cleanupFailedWorkspace($workspacePath, $isQuiet, $isJson);
        }

        // Display error message
        if ($isJson) {
            // Structured error output for programmatic usage
            $this->outputJson([
                'success' => false,
                'error' => $exception->getMessage(),
            ]);
        } else {
            // Human-readable error message
            $this->error('Workspace creation failed: ' . $exception->getMessage());
        }

        return Command::FAILURE;
    }

    /**
     * Get and validate workspace name with smart suggestions.
     *
     * Obtains and validates the workspace name through multiple strategies:
     * 1. From command argument (if provided)
     * 2. From interactive prompt (if in interactive mode)
     * 3. From smart suggestions (if name is already taken)
     *
     * Validation rules:
     * - Must be lowercase alphanumeric with hyphens
     * - Pattern: ^[a-z0-9]+(-[a-z0-9]+)*$
     * - Examples: my-project, awesome-app, project-2024
     * - Invalid: MyProject, my_project, project., -project
     *
     * Name availability check:
     * - Checks if directory already exists in current working directory
     * - If taken, generates smart suggestions using NameSuggestionService
     * - Suggestions include: suffixes (-v2, -new), prefixes (my-, new-), variations
     *
     * Interactive mode:
     * - Prompts user for name if not provided
     * - Shows inline validation errors
     * - Displays suggestions if name is taken
     * - Allows custom name entry
     *
     * Non-interactive mode:
     * - Requires name as argument
     * - Exits with error if name not provided or invalid
     * - No prompts or suggestions
     *
     * @param  InputInterface $input   Command input (for reading argument and checking interactive mode)
     * @param  bool           $isQuiet Suppress output messages
     * @param  bool           $isJson  Output in JSON format
     * @return string         Validated workspace name
     */
    private function getValidatedWorkspaceName(InputInterface $input, bool $isQuiet, bool $isJson): string
    {
        // Get workspace name from argument or prompt
        $name = $this->argument('name');

        // Check if running in non-interactive mode without name
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

        // Prompt for name if not provided (interactive mode only)
        if ($name === null || $name === '') {
            // Interactive mode - prompt for name with inline validation
            $name = $this->text(
                label: 'What is the workspace name?',
                placeholder: 'my-project',
                required: true,
                validate: fn ($value) => (is_string($value) && $value !== '' && preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $value) === 1) ? null : 'Workspace name must be lowercase alphanumeric with hyphens (e.g., my-project)',
            );
        }

        // Validate workspace name format (inline validation)
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
            // Name is available
            return $name;
        }

        // Name is taken, offer suggestions
        if (! $isQuiet && ! $isJson) {
            $this->line('');
        }

        // Generate smart suggestions using NameSuggestionService
        $nameSuggestionService = NameSuggestionService::make();
        $suggestions = $nameSuggestionService->suggest(
            $name,
            'workspace',
            fn (?string $suggestedName): bool => is_string($suggestedName) && $suggestedName !== '' && ! $this->filesystem()->isDirectory($suggestedName)
        );

        // Check if suggestions were generated
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

        // Get the best suggestion (highest score)
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

        // Validate the chosen name availability
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

        // Display success message
        if (! $isQuiet && ! $isJson) {
            $this->info("✓ Workspace name '{$choice}' is available");
        }

        return $choice;
    }

    /**
     * Clone the template repository from GitHub.
     *
     * Clones the official PhpHive template repository from GitHub and removes
     * the .git directory to allow for a fresh git initialization.
     *
     * Template repository:
     * - URL: https://github.com/pixielity-inc/hive-template.git
     * - Contains: Sample app, sample package, complete monorepo structure
     * - Includes: Turborepo config, pnpm workspace, composer setup
     *
     * Clone process:
     * 1. Execute git clone command with workspace name as target directory
     * 2. Wait for clone to complete (5 minute timeout)
     * 3. Remove .git directory to allow fresh git initialization
     * 4. Return exit code (0 for success, non-zero for failure)
     *
     * Verbose mode:
     * - Shows git clone command being executed
     * - Displays git output in real-time
     * - Shows rm command for .git directory removal
     *
     * Common failure scenarios:
     * - Network issues (no internet connection)
     * - Invalid repository URL
     * - Insufficient disk space
     * - Permission issues
     * - Timeout (clone takes longer than 5 minutes)
     *
     * @param  string $name      Workspace name (directory to clone into)
     * @param  bool   $isVerbose Show verbose output (commands and git output)
     * @return int    Exit code (0 for success, non-zero for failure)
     */
    private function cloneTemplate(string $name, bool $isVerbose): int
    {
        // Build git clone command
        $cloneCommand = 'git clone ' . self::TEMPLATE_URL . " {$name}";

        // Display command in verbose mode
        if ($isVerbose) {
            $this->comment("  Executing: {$cloneCommand}");
        }

        // Create process with 5 minute timeout
        $process = Process::fromShellCommandline(
            $cloneCommand,
            null,
            null,
            null,
            300 // 5 minute timeout
        );

        // Execute clone command
        if ($isVerbose) {
            // Show output in verbose mode (real-time git progress)
            $process->run(function ($type, $buffer): void {
                echo $buffer;
            });
        } else {
            // Silent execution
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
     * Updates configuration files with the new workspace name and initializes
     * a fresh git repository with an initial commit.
     *
     * Configuration updates:
     * 1. package.json
     *    - Updates "name" field to workspace name
     *    - Preserves all other fields (scripts, workspaces, etc.)
     *
     * 2. composer.json
     *    - Updates "name" field to "phphive/{workspace-name}"
     *    - Preserves all other fields (autoload, repositories, etc.)
     *
     * 3. Git initialization
     *    - Runs: git init
     *    - Runs: git add .
     *    - Runs: git commit -m "Initial commit from hive-template"
     *
     * File handling:
     * - Reads JSON files with json_decode()
     * - Updates specific fields
     * - Writes back with pretty printing (JSON_PRETTY_PRINT)
     * - Preserves formatting with JSON_UNESCAPED_SLASHES
     * - Adds newline at end of file
     *
     * Error handling:
     * - Returns false if any step fails
     * - Catches all exceptions
     * - Doesn't display error messages (handled by caller)
     *
     * @param  string $name Workspace name
     * @return bool   True on success, false on failure
     */
    private function updateWorkspaceConfig(string $name): bool
    {
        try {
            $filesystem = $this->filesystem();

            // Update package.json with workspace name
            $packageJsonPath = "{$name}/package.json";
            if ($filesystem->exists($packageJsonPath)) {
                // Read existing package.json
                $content = $filesystem->read($packageJsonPath);
                $packageJson = json_decode($content, true);

                // Update name field
                if (is_array($packageJson)) {
                    $packageJson['name'] = $name;

                    // Write back with pretty printing
                    $filesystem->write(
                        $packageJsonPath,
                        json_encode($packageJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
                    );
                }
            }

            // Update composer.json with vendor/package format
            $composerJsonPath = "{$name}/composer.json";
            if ($filesystem->exists($composerJsonPath)) {
                // Read existing composer.json
                $content = $filesystem->read($composerJsonPath);
                $composerJson = json_decode($content, true);

                // Update name field to vendor/package format
                if (is_array($composerJson)) {
                    $composerJson['name'] = "phphive/{$name}";

                    // Write back with pretty printing
                    $filesystem->write(
                        $composerJsonPath,
                        json_encode($composerJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
                    );
                }
            }

            // Initialize new git repository with initial commit
            // Commands: git init && git add . && git commit -m "Initial commit from hive-template"
            exec("cd {$name} && git init && git add . && git commit -m 'Initial commit from hive-template' 2>&1");

            return true;
        } catch (Exception) {
            // Return false on any error (caller will handle error display)
            return false;
        }
    }
}
