<?php

declare(strict_types=1);

namespace PhpHive\Cli\Console\Commands\Make;

use Exception;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Override;
use PhpHive\Cli\Contracts\PackageTypeInterface;
use PhpHive\Cli\Enums\PackageType;
use PhpHive\Cli\Services\NameSuggestionService;
use PhpHive\Cli\Support\Filesystem;
use Pixielity\StubGenerator\Exceptions\StubNotFoundException;
use Pixielity\StubGenerator\Facades\Stub;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Create Package Command.
 *
 * This command scaffolds a reusable PHP library package within the monorepo's
 * packages/ directory. Packages are shared libraries that can be used by
 * multiple applications within the monorepo, promoting code reuse and
 * maintaining a clean separation of concerns.
 *
 * The scaffolding process:
 * 1. Validates the package name doesn't already exist
 * 2. Creates the directory structure (src, tests)
 * 3. Generates composer.json with library configuration
 * 4. Generates package.json with Turbo task definitions
 * 5. Creates PHPUnit configuration for testing
 * 6. Generates README.md with usage instructions
 * 7. Adds .gitkeep files to preserve empty directories
 *
 * Directory structure created:
 * packages/{name}/
 * ├── src/              # Package source code (PSR-4)
 * ├── tests/            # Test files
 * │   └── Unit/         # Unit tests
 * ├── composer.json     # PHP dependencies and autoloading
 * ├── package.json      # Turbo tasks and npm scripts
 * ├── phpunit.xml       # PHPUnit configuration
 * └── README.md         # Documentation
 *
 * Generated composer.json includes:
 * - PHP 8.2+ requirement
 * - Library type designation
 * - PHPUnit, PHPStan, and Pint dev dependencies
 * - PSR-4 autoloading configuration
 * - Proper namespace based on package name
 *
 * Generated package.json includes Turbo tasks:
 * - test: Run PHPUnit tests
 * - test:unit: Run unit tests only
 * - lint: Check code style with Pint
 * - format: Fix code style with Pint
 * - typecheck: Run PHPStan static analysis
 * - clean: Remove cache files
 *
 * PHPUnit configuration includes:
 * - Bootstrap with Composer autoloader
 * - Color output enabled
 * - Fail on risky tests and warnings
 * - Code coverage configuration
 * - Test suite definitions
 *
 * Naming conventions:
 * - Package names use kebab-case (e.g., logger, http-client)
 * - Namespaces use PascalCase (e.g., PhpHive\Logger)
 * - Composer names use phphive/{name} format
 * - NPM names use @phphive/{name} format
 *
 * Package vs Application:
 * - Packages are libraries (type: library)
 * - Applications are projects (type: project)
 * - Packages don't have public/ or config/ directories
 * - Packages focus on reusable functionality
 * - Packages can be required by multiple apps
 *
 * Features:
 * - Automatic namespace generation from package name
 * - Pre-configured with monorepo best practices
 * - Ready for Turbo task execution
 * - Complete testing infrastructure
 * - Configured for code quality tools
 * - PSR-4 autoloading ready
 *
 * Example usage:
 * ```bash
 * # Create a logging package
 * hive create:package logger
 *
 * # Create an HTTP client package
 * hive create:package http-client
 *
 * # Create a validation package
 * hive create:package validator
 *
 * # Using aliases
 * hive make:package database
 * hive new:package cache
 * ```
 *
 * After creation workflow:
 * ```bash
 * # Navigate to the new package
 * cd packages/logger
 *
 * # Install dependencies
 * composer install
 *
 * # Start coding in src/
 * # Add your classes and interfaces
 *
 * # Run tests
 * composer test
 *
 * # Use in an application
 * # Add to app's composer.json:
 * # "require": { "phphive/logger": "*" }
 * ```
 *
 * @see BaseCommand For inherited functionality
 * @see CreateAppCommand For creating applications
 * @see InteractsWithMonorepo For workspace discovery
 * @see Filesystem For file operations
 */
#[AsCommand(
    name: 'make:package',
    description: 'Create a new package',
    aliases: ['create:package', 'new:package'],
)]
final class CreatePackageCommand extends BaseMakeCommand
{
    /**
     * Configure the command options and arguments.
     *
     * Defines the command signature with required arguments, options, and help text.
     */
    #[Override]
    protected function configure(): void
    {
        parent::configure();

        $this
            ->addArgument(
                'name',
                InputArgument::REQUIRED,
                'Package name (e.g., logger, http-client)',
            )
            ->addOption(
                'type',
                't',
                InputOption::VALUE_REQUIRED,
                'Package type (laravel, symfony, magento, skeleton)',
            )
            ->addOption(
                'description',
                'd',
                InputOption::VALUE_REQUIRED,
                'Package description',
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
            );
    }

    /**
     * Execute the create package command.
     *
     * This method orchestrates the entire package scaffolding process:
     * 1. Runs preflight checks to validate environment
     * 2. Extracts and validates the package name with smart suggestions
     * 3. Prompts for package type if not provided
     * 4. Creates the complete directory structure using stubs
     * 5. Processes stub templates with variables
     * 6. Installs dependencies with progress feedback
     * 7. Displays next steps to the user
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

        // Track package path for cleanup on failure
        $packagePath = null;
        $packageCreated = false;

        // Register signal handlers and subscribe to cleanup events
        $this->setupCleanupHandlers($packagePath, $packageCreated, $isQuiet, $isJson);

        try {
            // Step 1: Display intro and run preflight checks
            $this->displayIntro($isQuiet, $isJson);
            if (! $this->checkEnvironment($isQuiet, $isJson)) {
                return Command::FAILURE;
            }

            // Step 2: Get and validate package name
            $name = $this->getValidatedPackageName($input, $isQuiet, $isJson);

            // Step 3: Select and validate package type
            $packageType = $this->selectPackageType($input, $isQuiet, $isJson);
            if (! $packageType instanceof PackageTypeInterface) {
                return Command::FAILURE;
            }

            // Step 4: Execute package creation
            $root = $this->getMonorepoRoot();
            $packagePath = "{$root}/packages/{$name}";
            $packageCreated = true;

            $totalDuration = $this->executeCreationSteps($input, $name, $packageType, $packagePath, $isQuiet, $isJson, $isVerbose);

            // Step 5: Display success summary
            $this->displaySuccessMessage(
                'package',
                $name,
                $packagePath,
                $totalDuration,
                [
                    "cd packages/{$name}",
                    'Start coding in src/',
                    'Run tests with: composer test',
                ],
                $isQuiet,
                $isJson,
                $isVerbose
            );

            return Command::SUCCESS;
        } catch (Exception $exception) {
            return $this->handleFailure($exception, $packagePath, $packageCreated, $isQuiet, $isJson);
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
     * 2. Check if package directory was created
     * 3. Call cleanupFailedWorkspace() to remove the package directory
     *
     * The handlers use closure variable references (&$packagePath, &$packageCreated)
     * to access the current state of the creation process, allowing them to determine
     * if cleanup is necessary.
     *
     * @param string|null &$packagePath    Reference to package path (updated during creation)
     * @param bool        &$packageCreated Reference to creation flag (set to true when package dir is created)
     * @param bool        $isQuiet         Suppress output messages
     * @param bool        $isJson          Output in JSON format
     */
    private function setupCleanupHandlers(?string &$packagePath, bool &$packageCreated, bool $isQuiet, bool $isJson): void
    {
        // Register global signal handlers (SIGINT, SIGTERM)
        $this->registerSignalHandlers();

        // Subscribe to SIGINT event (Ctrl+C)
        $this->bindEvent('signal.interrupt', function () use (&$packagePath, &$packageCreated, $isQuiet, $isJson): void {
            // Display cancellation message
            if (! $isQuiet && ! $isJson) {
                $this->line('');
                $this->warning('Operation cancelled by user.');
            }

            // Cleanup if package directory was created
            if ($packageCreated && $packagePath !== null) {
                if (! $isQuiet && ! $isJson) {
                    $this->warning('Cleaning up...');
                }
                $this->cleanupFailedWorkspace($packagePath, $isQuiet, $isJson);
            }
        });

        // Subscribe to SIGTERM event (process termination)
        $this->bindEvent('signal.terminate', function () use (&$packagePath, &$packageCreated, $isQuiet, $isJson): void {
            // Display termination message
            if (! $isQuiet && ! $isJson) {
                $this->line('');
                $this->warning('Operation terminated.');
            }

            // Cleanup if package directory was created
            if ($packageCreated && $packagePath !== null) {
                if (! $isQuiet && ! $isJson) {
                    $this->warning('Cleaning up...');
                }
                $this->cleanupFailedWorkspace($packagePath, $isQuiet, $isJson);
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
            $this->intro('Package Creation');
            $this->info('Running environment checks...');
        }
    }

    /**
     * Check environment with preflight checks.
     *
     * Validates that the development environment meets all requirements for
     * creating a package. This includes checking for:
     * - Required tools (PHP, Composer, Git)
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
     * Select and validate package type.
     *
     * Determines which package type to create (Laravel, Symfony, Magento, Skeleton).
     * The package type can be provided via the --type option or selected interactively.
     *
     * Process:
     * 1. Check if --type option was provided
     * 2. If not provided, prompt user to select from available types
     * 3. Validate and create package type instance
     * 4. Display selected package type name
     *
     * Package types:
     * - Laravel: Package for Laravel applications with service providers
     * - Symfony: Bundle for Symfony applications with dependency injection
     * - Magento: Module for Magento with XML configuration
     * - Skeleton: Generic PHP library with minimal dependencies
     *
     * If validation fails (invalid type or factory error), error messages are
     * displayed and null is returned to halt the creation process.
     *
     * @param  InputInterface            $input   Command input (for reading --type option)
     * @param  bool                      $isQuiet Suppress output messages
     * @param  bool                      $isJson  Output in JSON format
     * @return PackageTypeInterface|null Package type instance, or null if validation failed
     */
    private function selectPackageType(InputInterface $input, bool $isQuiet, bool $isJson): ?PackageTypeInterface
    {
        $type = $input->getOption('type');
        $packageTypeFactory = $this->packageTypeFactory();

        // Check if type was provided via option
        if ($type === null) {
            // Prompt user to select package type interactively
            $type = $this->select(
                label: 'Select package type',
                options: $packageTypeFactory->getTypeOptions(),
                default: PackageType::SKELETON->value
            );
        }

        // Validate and create package type instance
        try {
            $packageType = $packageTypeFactory->create($type);
        } catch (InvalidArgumentException $invalidArgumentException) {
            // Display error message
            if ($isJson) {
                $this->outputJson([
                    'success' => false,
                    'error' => $invalidArgumentException->getMessage(),
                ]);
            } else {
                $this->error($invalidArgumentException->getMessage());
            }

            return null;
        }

        // Display selected package type
        if (! $isQuiet && ! $isJson) {
            $this->line('');
            $this->comment("Selected: {$packageType->getDisplayName()}");
            $this->line('');
        }

        return $packageType;
    }

    /**
     * Execute creation steps with progress feedback.
     *
     * Orchestrates the main package creation workflow by executing a series
     * of steps in sequence. Each step is a closure that performs a specific task
     * and returns a boolean indicating success/failure.
     *
     * Creation steps:
     * 1. Checking name availability
     *    - Verifies package name doesn't already exist
     *    - Prevents accidental overwrites
     *
     * 2. Creating package structure
     *    - Creates the package directory
     *    - Sets up directory permissions
     *
     * 3. Generating configuration files
     *    - Copies stub template files (composer.json, package.json, phpunit.xml, etc.)
     *    - Replaces placeholders with actual values (name, namespace, description)
     *    - Generates package-specific configuration
     *
     * 4. Installing dependencies (separate step)
     *    - Runs composer install
     *    - Installs PHPUnit, PHPStan, Pint
     *    - Sets up autoloading
     *
     * Progress feedback:
     * - In normal mode: Shows spinner with step message
     * - In quiet/json mode: Executes silently
     * - In verbose mode: Shows step duration
     *
     * @param  InputInterface       $input       Command input (for reading options)
     * @param  string               $name        Package name
     * @param  PackageTypeInterface $packageType Package type instance
     * @param  string               $packagePath Full path to package directory
     * @param  bool                 $isQuiet     Suppress output messages
     * @param  bool                 $isJson      Output in JSON format
     * @param  bool                 $isVerbose   Show detailed output
     * @return float                Total duration in seconds
     */
    private function executeCreationSteps(
        InputInterface $input,
        string $name,
        PackageTypeInterface $packageType,
        string $packagePath,
        bool $isQuiet,
        bool $isJson,
        bool $isVerbose
    ): float {
        // Get package type for error messages
        $type = $input->getOption('type') ?? PackageType::SKELETON->value;

        // Define creation steps as closures
        $steps = [
            'Checking name availability' => fn (): bool => $this->checkNameAvailability($name, $packagePath),
            'Creating package structure' => fn (): bool => $this->createPackageStructure($packagePath),
            'Generating configuration files' => fn (): bool => $this->generateConfigFiles($input, $name, $type, $packagePath, $packageType, $isVerbose),
        ];

        // Track total duration
        $startTime = microtime(true);

        // Execute each step in sequence
        foreach ($steps as $message => $step) {
            $this->executeStep($step, $message, $name, $type, $isQuiet, $isJson, $isVerbose);
        }

        // Install dependencies (separate step with special handling)
        if (! $isQuiet && ! $isJson) {
            $this->line('');
        }

        $this->installPackageDependencies($packageType, $packagePath, $isQuiet, $isJson, $isVerbose);

        return microtime(true) - $startTime;
    }

    /**
     * Execute a single creation step.
     *
     * Runs a single step in the creation workflow and provides progress feedback.
     * The step is executed as a closure that returns a boolean indicating success.
     *
     * Execution flow:
     * 1. Record start time for duration tracking
     * 2. Execute step (with or without spinner based on mode)
     * 3. Check result - throw exception if step failed
     * 4. Calculate step duration
     * 5. Display completion message with optional duration
     *
     * Progress feedback modes:
     * - Normal mode: Shows spinner during execution, completion message after
     * - Quiet/JSON mode: Executes silently, no output
     * - Verbose mode: Shows completion message with duration
     *
     * Error handling:
     * - If step returns false, throws Exception with step message
     * - Exception is caught by execute() method's try-catch block
     * - Triggers cleanup and displays error message
     *
     * @param callable $step      Step closure to execute
     * @param string   $message   Step description for display
     * @param string   $name      Package name (for error messages)
     * @param string   $type      Package type (for error messages)
     * @param bool     $isQuiet   Suppress output messages
     * @param bool     $isJson    Output in JSON format
     * @param bool     $isVerbose Show detailed output
     *
     * @throws Exception If step fails (returns false)
     */
    private function executeStep(
        callable $step,
        string $message,
        string $name,
        string $type,
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

        // Check if step failed
        if ($result === false) {
            // Output error in JSON format if requested
            if ($isJson) {
                $this->outputJson([
                    'success' => false,
                    'error' => "Failed: {$message}",
                    'package_name' => $name,
                    'package_type' => $type,
                ]);
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
     * Install package dependencies.
     *
     * Installs Composer dependencies for the package, including development
     * dependencies like PHPUnit, PHPStan, and Pint. This step is separate
     * from the main creation steps because it has special error handling
     * (warnings instead of failures).
     *
     * Installation process:
     * 1. Run composer install in package directory
     * 2. Install PHPUnit for testing
     * 3. Install PHPStan for static analysis
     * 4. Install Pint for code formatting
     * 5. Set up PSR-4 autoloading
     *
     * Error handling:
     * - If installation succeeds: Display success message
     * - If installation fails: Display warning (not error)
     * - Failure doesn't halt package creation
     * - User can manually run composer install later
     *
     * Progress feedback:
     * - Normal mode: Shows spinner with "Installing dependencies..." message
     * - Quiet/JSON mode: Executes silently
     * - Verbose mode: Shows installation duration
     *
     * @param PackageTypeInterface $packageType Package type instance
     * @param string               $packagePath Full path to package directory
     * @param bool                 $isQuiet     Suppress output messages
     * @param bool                 $isJson      Output in JSON format
     * @param bool                 $isVerbose   Show detailed output
     */
    private function installPackageDependencies(
        PackageTypeInterface $packageType,
        string $packagePath,
        bool $isQuiet,
        bool $isJson,
        bool $isVerbose
    ): void {
        // Track installation duration
        $installStartTime = microtime(true);

        // Execute installation (with or without spinner)
        if ($isQuiet || $isJson) {
            // Silent execution
            $installResult = $this->installDependencies($packageType, $packagePath);
        } else {
            // Show spinner during installation
            $installResult = $this->spin(
                fn (): bool => $this->installDependencies($packageType, $packagePath),
                'Installing dependencies...'
            );
        }

        // Calculate installation duration
        $installDuration = microtime(true) - $installStartTime;

        // Display result message
        if ($installResult) {
            // Installation succeeded
            if (! $isQuiet && ! $isJson) {
                $this->comment('✓ Dependencies installed successfully');
            } elseif ($isVerbose && ! $isJson) {
                // Show duration in verbose mode
                $this->comment(sprintf('✓ Dependencies installed successfully (%.2fs)', $installDuration));
            }
        } elseif (! $isQuiet && ! $isJson) {
            // Installation failed - show warning (not error)
            $this->warning('⚠ Dependency installation had issues (you may need to run composer install manually)');
        }
    }

    /**
     * Handle command failure.
     *
     * Handles exceptions that occur during package creation by performing
     * cleanup and displaying appropriate error messages.
     *
     * Cleanup process:
     * 1. Check if package directory was created
     * 2. Display cleanup message (unless in quiet/json mode)
     * 3. Call cleanupFailedWorkspace() to delete the package directory
     *
     * Error message display:
     * - JSON mode: Outputs structured error with success=false
     * - Normal mode: Displays error message with exception details
     *
     * This method is called from the execute() method's catch block when any
     * exception occurs during the creation process, including:
     * - Step failures (from executeStep throwing Exception)
     * - User cancellation (Ctrl+C via signal handlers)
     * - Unexpected errors (file system, validation, etc.)
     *
     * @param  Exception   $exception      The exception that caused the failure
     * @param  string|null $packagePath    Path to package directory (null if not created yet)
     * @param  bool        $packageCreated Whether package directory was created
     * @param  bool        $isQuiet        Suppress output messages
     * @param  bool        $isJson         Output in JSON format
     * @return int         Command::FAILURE exit code
     */
    private function handleFailure(
        Exception $exception,
        ?string $packagePath,
        bool $packageCreated,
        bool $isQuiet,
        bool $isJson
    ): int {
        // Cleanup on failure or cancellation
        if ($packageCreated && $packagePath !== null) {
            // Display cleanup message
            if (! $isQuiet && ! $isJson) {
                $this->line('');
                $this->warning('Cleaning up failed package...');
            }

            // Remove package directory
            $this->cleanupFailedWorkspace($packagePath, $isQuiet, $isJson);
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
            $this->error('Package creation failed: ' . $exception->getMessage());
        }

        return Command::FAILURE;
    }

    /**
     * Get and validate package name with smart suggestions.
     *
     * Obtains and validates the package name from command argument with
     * smart suggestions if the name is already taken.
     *
     * Validation rules:
     * - Must be lowercase alphanumeric with hyphens
     * - Pattern: ^[a-z0-9]+(-[a-z0-9]+)*$
     * - Examples: logger, http-client, database-adapter
     * - Invalid: Logger, http_client, -logger, logger-
     *
     * Name availability check:
     * - Checks if directory already exists in packages/ directory
     * - If taken, generates smart suggestions using NameSuggestionService
     * - Suggestions include: suffixes (-v2, -new), prefixes (my-, new-), variations
     *
     * Smart suggestions:
     * - Generated based on original name
     * - Filtered to ensure valid format (lowercase alphanumeric with hyphens)
     * - Filtered to ensure availability (directory doesn't exist)
     * - Best suggestion is highlighted as "recommended"
     * - User can select from suggestions or enter custom name
     *
     * Error handling:
     * - Exits with Command::FAILURE if name is invalid or unavailable
     * - Displays error in JSON format if --json flag is set
     * - Displays human-readable error otherwise
     *
     * @param  InputInterface $input   Command input (for reading name argument)
     * @param  bool           $isQuiet Suppress output messages
     * @param  bool           $isJson  Output in JSON format
     * @return string         Validated package name
     */
    private function getValidatedPackageName(InputInterface $input, bool $isQuiet, bool $isJson): string
    {
        // Get package name from command argument
        $name = $input->getArgument('name');

        // Validate that name was provided
        if (! is_string($name) || $name === '') {
            $errorMsg = 'Package name is required';
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

        // Validate name format (lowercase alphanumeric with hyphens)
        if (preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $name) !== 1) {
            $errorMsg = 'Package name must be lowercase alphanumeric with hyphens (e.g., my-package)';
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

        // Build package path
        $root = $this->getMonorepoRoot();
        $packagePath = "{$root}/packages/{$name}";

        // Check if name is available
        if (! $this->checkDirectoryExists($name, $packagePath, 'package', $isQuiet, $isJson)) {
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
            'package',
            // Filter suggestions: valid format and available
            fn (?string $suggestedName): bool => is_string($suggestedName) && $suggestedName !== '' && preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $suggestedName) === 1 && ! $this->filesystem()->isDirectory("{$root}/packages/{$suggestedName}")
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

        // Validate the chosen name format
        if (! is_string($choice) || $choice === '' || preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $choice) !== 1) {
            $errorMsg = 'Invalid package name format';
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
        $chosenPath = "{$root}/packages/{$choice}";
        if ($this->filesystem()->isDirectory($chosenPath)) {
            $errorMsg = "Package '{$choice}' also exists. Please try again with a different name.";
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
            $this->info("✓ Package name '{$choice}' is available");
        }

        return $choice;
    }

    /**
     * Check if package name is available.
     *
     * Verifies that a package with the given name doesn't already exist
     * in the packages/ directory. This prevents accidental overwrites.
     *
     * @param  string $name        Package name to check
     * @param  string $packagePath Full path to package directory
     * @return bool   True if available (directory doesn't exist), false if taken
     */
    private function checkNameAvailability(string $name, string $packagePath): bool
    {
        // Check if directory already exists
        if ($this->filesystem()->isDirectory($packagePath)) {
            $this->error("Package '{$name}' already exists");

            return false;
        }

        return true;
    }

    /**
     * Create package directory structure.
     *
     * Creates the root package directory with proper permissions.
     * Subdirectories (src/, tests/) are created later by stub processing.
     *
     * Directory permissions:
     * - 0755: Owner can read/write/execute, group and others can read/execute
     * - Recursive: Creates parent directories if needed
     *
     * @param  string $packagePath Full path to package directory
     * @return bool   True on success, false on failure
     */
    private function createPackageStructure(string $packagePath): bool
    {
        try {
            // Create package directory with proper permissions
            $this->filesystem()->makeDirectory($packagePath, 0755, true);

            return true;
        } catch (Exception $exception) {
            $this->error("Failed to create package directory: {$exception->getMessage()}");

            return false;
        }
    }

    /**
     * Generate configuration files from stubs.
     *
     * Copies and processes stub template files to generate package configuration.
     * Stubs are template files with placeholders that get replaced with actual values.
     *
     * Generated files:
     * - composer.json: PHP dependencies, autoloading, package metadata
     * - package.json: Turbo tasks, npm scripts
     * - phpunit.xml: PHPUnit configuration for testing
     * - README.md: Package documentation
     * - src/.gitkeep: Preserves empty src directory
     * - tests/Unit/.gitkeep: Preserves empty tests directory
     *
     * Stub processing:
     * 1. Get stub path for selected package type
     * 2. Verify stub directory exists
     * 3. Prepare variables for placeholder replacement:
     *    - {{NAME}}: Package name (e.g., logger)
     *    - {{NAMESPACE}}: PSR-4 namespace (e.g., PhpHive\Logger)
     *    - {{DESCRIPTION}}: Package description
     *    - {{COMPOSER_NAME}}: Composer package name (e.g., phphive/logger)
     * 4. Copy and process all stub files
     * 5. Apply file naming rules (e.g., rename placeholders in filenames)
     *
     * Verbose mode:
     * - Shows "Processing stub files..." message
     * - Displays each file being created
     *
     * @param  InputInterface       $input       Command input (for reading --description option)
     * @param  string               $name        Package name
     * @param  string               $type        Package type (for error messages)
     * @param  string               $packagePath Full path to package directory
     * @param  PackageTypeInterface $packageType Package type instance
     * @param  bool                 $isVerbose   Show verbose output
     * @return bool                 True on success, false on failure
     */
    private function generateConfigFiles(InputInterface $input, string $name, string $type, string $packagePath, PackageTypeInterface $packageType, bool $isVerbose): bool
    {
        try {
            // Get stub path for the selected package type
            $stubsBasePath = dirname(__DIR__, 4) . '/stubs';
            $stubPath = $packageType->getStubPath($stubsBasePath);

            // Verify stub directory exists
            if (! $this->filesystem()->isDirectory($stubPath)) {
                $this->error("Stub directory not found for package type '{$type}' at: {$stubPath}");

                return false;
            }

            // Prepare stub variables using package type
            // Gets description from --description option or uses default
            $description = $input->getOption('description') ?? "A {$type} package";
            $variables = $packageType->prepareVariables($name, $description);

            // Display processing message in verbose mode
            if ($isVerbose) {
                $this->comment('  Processing stub files...');
            }

            // Copy and process all stub files with package type naming rules
            $this->copyStubFiles($stubPath, $packagePath, $variables, $this->filesystem(), $packageType->getFileNamingRules(), $isVerbose);

            return true;
        } catch (Exception $exception) {
            $this->error("Failed to generate configuration files: {$exception->getMessage()}");

            return false;
        }
    }

    /**
     * Install package dependencies.
     *
     * Runs composer install in the package directory to install all dependencies
     * defined in composer.json. This includes:
     * - PHPUnit for testing
     * - PHPStan for static analysis
     * - Pint for code formatting
     * - Any other dev dependencies
     *
     * The installation is delegated to the package type's postCreate() method,
     * which handles the actual composer install command execution.
     *
     * Error handling:
     * - Returns false if installation fails
     * - Doesn't throw exceptions (errors are logged but don't fail the command)
     * - Allows package creation to complete even if dependencies fail to install
     * - User can manually run composer install later if needed
     *
     * @param  PackageTypeInterface $packageType Package type instance
     * @param  string               $packagePath Full path to package directory
     * @return bool                 True on success, false on failure
     */
    private function installDependencies(PackageTypeInterface $packageType, string $packagePath): bool
    {
        try {
            // Delegate to package type's postCreate method
            $packageType->postCreate($packagePath);

            return true;
        } catch (Exception) {
            // Log error but don't fail the command
            // User can manually run composer install later
            return false;
        }
    }

    /**
     * Copy stub files to package directory with variable replacement.
     *
     * Recursively copies all stub template files from the stub directory to the
     * package directory, replacing placeholders with actual values and applying
     * file naming rules.
     *
     * Stub processing:
     * 1. Set base path for Stub facade
     * 2. Iterate through all files and directories recursively
     * 3. For directories: Create in destination with proper permissions
     * 4. For files:
     *    a. Remove .stub extension if present
     *    b. Apply naming rules (rename files based on variables)
     *    c. Escape backslashes for JSON files (namespace values)
     *    d. Process template with Stub facade (replace placeholders)
     *    e. Save to destination
     *
     * File naming rules:
     * - Pattern matching: Compares normalized paths (no leading slashes)
     * - Variable replacement: Converts {{UPPERCASE}} placeholders
     * - Example: "src/{{NAMESPACE}}.php" → "src/PhpHive/Logger.php"
     *
     * JSON file handling:
     * - Escapes backslashes in namespace values
     * - Single backslash → Double backslash (for JSON encoding)
     * - Example: "PhpHive\Logger" → "PhpHive\\Logger"
     *
     * Verbose mode:
     * - Displays each file being created
     * - Shows relative path from package root
     *
     * Error handling:
     * - Catches StubNotFoundException for missing stub files
     * - Logs error and continues with next file
     * - Doesn't halt the entire process for single file failures
     *
     * @param string                $stubPath    Source stub directory path
     * @param string                $packagePath Destination package directory path
     * @param array<string, string> $variables   Variables for template replacement (name, namespace, description, etc.)
     * @param Filesystem            $filesystem  Filesystem service instance
     * @param array<string, string> $namingRules File naming rules for special files (pattern => replacement)
     * @param bool                  $isVerbose   Show verbose output (display each file being created)
     */
    private function copyStubFiles(string $stubPath, string $packagePath, array $variables, Filesystem $filesystem, array $namingRules = [], bool $isVerbose = false): void
    {
        // Set base path for Stub facade
        Stub::setBasePath($stubPath);

        // Create recursive iterator for all files and directories
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($stubPath, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        // Process each item (file or directory)
        foreach ($iterator as $item) {
            // Get relative path from stub directory
            $relativePath = Str::substr($item->getPathname(), Str::length($stubPath) + 1);
            $destinationPath = $packagePath . '/' . $relativePath;

            if ($item->isDir()) {
                // Create directory with proper permissions
                $filesystem->makeDirectory($destinationPath, 0755, true);
            } else {
                // Process file: remove .stub extension
                $targetPath = $relativePath;
                if (Str::endsWith($targetPath, '.stub')) {
                    $targetPath = Str::substr($targetPath, 0, -5);
                }

                // Apply naming rules from package type
                foreach ($namingRules as $pattern => $replacement) {
                    // Normalize paths for comparison (remove leading slashes)
                    $normalizedPattern = Str::ltrim($pattern, '/');
                    $normalizedTarget = Str::ltrim($targetPath, '/');

                    // Check if this file matches the naming rule pattern
                    if ($normalizedTarget === $normalizedPattern) {
                        // Convert variable keys to {{UPPERCASE}} format for replacement
                        $replacementVars = [];
                        foreach ($variables as $key => $value) {
                            $replacementVars['{{' . Str::upper($key) . '}}'] = $value;
                        }

                        // Replace pattern with actual values from variables
                        $replacedPattern = Str::replace(array_keys($replacementVars), array_values($replacementVars), $replacement);
                        // Remove leading slash from replacement for consistency
                        $targetPath = Str::ltrim($replacedPattern, '/');

                        break;
                    }
                }

                // For JSON files, escape backslashes in namespace values
                $isJsonFile = Str::endsWith($targetPath, '.json');
                $variablesToUse = $variables;

                if ($isJsonFile && isset($variables[PackageTypeInterface::NAMESPACE])) {
                    // Escape single backslashes to double backslashes for JSON
                    // But don't double-escape already escaped backslashes
                    $namespace = $variables[PackageTypeInterface::NAMESPACE];
                    $variablesToUse[PackageTypeInterface::NAMESPACE] = Str::replace('\\', '\\\\', $namespace);
                }

                // Display file being created in verbose mode
                if ($isVerbose) {
                    $this->comment("    Creating: {$targetPath}");
                }

                try {
                    // Use Stub facade to process and save the file
                    Stub::create($relativePath, $variablesToUse)->saveTo($packagePath, $targetPath);
                } catch (StubNotFoundException $e) {
                    // If stub not found, log error and continue
                    $this->error("Stub file not found: {$relativePath}");
                    $this->error($e->getMessage());
                }
            }
        }
    }
}
