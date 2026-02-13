<?php

declare(strict_types=1);

namespace PhpHive\Cli\Console\Commands\Make;

use Exception;
use Illuminate\Support\Str;
use Override;
use PhpHive\Cli\Contracts\AppTypeInterface;
use PhpHive\Cli\Enums\AppType;
use PhpHive\Cli\Enums\DatabaseType;
use PhpHive\Cli\Factories\AppTypeFactory;
use PhpHive\Cli\Services\NameSuggestionService;
use PhpHive\Cli\Support\Filesystem;
use Pixielity\StubGenerator\Exceptions\StubNotFoundException;
use Pixielity\StubGenerator\Facades\Stub;
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

        // Register signal handlers and subscribe to cleanup events
        $this->setupCleanupHandlers($appPath, $appCreated, $isQuiet, $isJson);

        try {
            // Step 1: Display intro and run preflight checks
            $this->displayIntro($isQuiet, $isJson);
            if (! $this->checkEnvironment($isQuiet, $isJson)) {
                return Command::FAILURE;
            }

            // Step 2: Get and validate application name
            $name = $this->getValidatedAppName($input, $isQuiet, $isJson);

            // Step 3: Select and validate app type
            $appType = $this->selectAppType($input, $isJson);
            if (! $appType instanceof AppTypeInterface) {
                return Command::FAILURE;
            }

            // Step 4: Collect configuration
            $config = $this->collectAppConfiguration($input, $output, $name, $appType, $isQuiet, $isJson);

            // Step 5: Execute application creation
            $root = $this->getMonorepoRoot();
            $appPath = "{$root}/apps/{$name}";
            $appCreated = true;

            $totalDuration = $this->executeCreationSteps($appType, $config, $name, $appPath, $isQuiet, $isJson, $isVerbose);

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
            return $this->handleFailure($exception, $appPath, $appCreated, $isQuiet, $isJson);
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
     * 2. Check if app directory was created
     * 3. Call cleanupFailedWorkspace() to remove Docker containers and directories
     *
     * The handlers use closure variable references (&$appPath, &$appCreated) to access
     * the current state of the creation process, allowing them to determine if cleanup
     * is necessary.
     *
     * @param string|null &$appPath    Reference to app path (updated during creation)
     * @param bool        &$appCreated Reference to creation flag (set to true when app dir is created)
     * @param bool        $isQuiet     Suppress output messages
     * @param bool        $isJson      Output in JSON format
     */
    private function setupCleanupHandlers(?string &$appPath, bool &$appCreated, bool $isQuiet, bool $isJson): void
    {
        // Register global signal handlers (SIGINT, SIGTERM)
        $this->registerSignalHandlers();

        // Subscribe to SIGINT event (Ctrl+C)
        $this->bindEvent('signal.interrupt', function () use (&$appPath, &$appCreated, $isQuiet, $isJson): void {
            // Display cancellation message
            if (! $isQuiet && ! $isJson) {
                $this->line('');
                $this->warning('Operation cancelled by user.');
            }

            // Cleanup if app directory was created
            if ($appCreated && $appPath !== null) {
                if (! $isQuiet && ! $isJson) {
                    $this->warning('Cleaning up...');
                }
                $this->cleanupFailedWorkspace($appPath, $isQuiet, $isJson);
            }
        });

        // Subscribe to SIGTERM event (process termination)
        $this->bindEvent('signal.terminate', function () use (&$appPath, &$appCreated, $isQuiet, $isJson): void {
            // Display termination message
            if (! $isQuiet && ! $isJson) {
                $this->line('');
                $this->warning('Operation terminated.');
            }

            // Cleanup if app directory was created
            if ($appCreated && $appPath !== null) {
                if (! $isQuiet && ! $isJson) {
                    $this->warning('Cleaning up...');
                }
                $this->cleanupFailedWorkspace($appPath, $isQuiet, $isJson);
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
            $this->intro('Application Creation');
            $this->info('Running environment checks...');
        }
    }

    /**
     * Check environment with preflight checks.
     *
     * Validates that the development environment meets all requirements for
     * creating an application. This includes checking for:
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
     * Select and validate app type.
     *
     * Determines which application type to create (Laravel, Symfony, Magento, Skeleton).
     * The app type can be provided via the --type option or selected interactively.
     *
     * Process:
     * 1. Check if --type option was provided
     * 2. If provided, validate it against available types
     * 3. If not provided, prompt user to select from available types
     * 4. Create and return the app type instance
     *
     * If validation fails (invalid type provided), error messages are displayed
     * and null is returned to halt the creation process.
     *
     * @param  InputInterface        $input  Command input (for reading --type option)
     * @param  bool                  $isJson Output in JSON format
     * @return AppTypeInterface|null App type instance, or null if validation failed
     */
    private function selectAppType(InputInterface $input, bool $isJson): ?AppTypeInterface
    {
        $typeOption = $input->getOption('type');

        // Check if type was provided via option
        if ($typeOption !== null && $typeOption !== '') {
            // Validate the provided app type
            if (! $this->appTypeFactory()->isValid($typeOption)) {
                $errorMsg = "Invalid app type: {$typeOption}";
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

                return null;
            }
            $appTypeId = $typeOption;
        } else {
            // Prompt user to select app type interactively
            $appTypeId = $this->select(
                label: 'Select application type',
                options: AppTypeFactory::choices()
            );
        }

        // Create and return the app type instance
        return $this->appTypeFactory()->create($appTypeId);
    }

    /**
     * Collect application configuration.
     *
     * Gathers all configuration needed to create the application by prompting
     * the user for app-type-specific settings. Each app type (Laravel, Symfony,
     * Magento, Skeleton) has its own configuration requirements.
     *
     * Process:
     * 1. Display selected app type name
     * 2. Set app name in input (so app type doesn't prompt for it again)
     * 3. Set description in input if provided via --description option
     * 4. Call app type's collectConfiguration() method to gather settings
     * 5. Ensure name and description are set in final config
     *
     * Configuration examples:
     * - Laravel: PHP version, starter kit (Breeze/Jetstream), Sanctum, Octane
     * - Symfony: PHP version, project type (webapp/api), Maker, Security, Doctrine
     * - Magento: Edition, version, marketplace credentials, admin settings
     * - Skeleton: PHP version, quality tools (PHPUnit, PHPStan, Pint)
     *
     * @param  InputInterface       $input   Command input (for reading options)
     * @param  OutputInterface      $output  Command output (for displaying prompts)
     * @param  string               $name    Application name
     * @param  AppTypeInterface     $appType App type instance
     * @param  bool                 $isQuiet Suppress output messages
     * @param  bool                 $isJson  Output in JSON format
     * @return array<string, mixed> Configuration array with all settings
     */
    private function collectAppConfiguration(
        InputInterface $input,
        OutputInterface $output,
        string $name,
        AppTypeInterface $appType,
        bool $isQuiet,
        bool $isJson
    ): array {
        // Display selected app type
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

        // Collect app-type-specific configuration
        $config = $appType->collectConfiguration($input, $output);

        // Ensure name is set from command argument (override any prompts)
        $config[AppTypeInterface::CONFIG_NAME] = $name;

        // Set description from option if provided, otherwise use collected value or default
        if ($descriptionOption !== null && $descriptionOption !== '') {
            $config[AppTypeInterface::CONFIG_DESCRIPTION] = $descriptionOption;
        } elseif (! isset($config[AppTypeInterface::CONFIG_DESCRIPTION]) || $config[AppTypeInterface::CONFIG_DESCRIPTION] === '') {
            $config[AppTypeInterface::CONFIG_DESCRIPTION] = "A {$appType->getName()} application";
        }

        return $config;
    }

    /**
     * Execute creation steps with progress feedback.
     *
     * Orchestrates the main application creation workflow by executing a series
     * of steps in sequence. Each step is a closure that performs a specific task
     * and returns a boolean indicating success/failure.
     *
     * Creation steps:
     * 1. Installing application framework
     *    - Runs composer create-project or equivalent command
     *    - Creates the base application structure
     *    - Installs framework dependencies
     *
     * 2. Setting up infrastructure
     *    - Configures database (MySQL, PostgreSQL, SQLite, MariaDB)
     *    - Sets up caching (Redis, Memcached)
     *    - Configures queues (Redis, Database, Sync)
     *    - Sets up search engines (Elasticsearch, Meilisearch)
     *    - Configures storage (MinIO, S3)
     *    - Creates Docker containers if needed
     *
     * 3. Processing configuration files
     *    - Copies stub template files
     *    - Replaces placeholders with actual values
     *    - Generates app-specific configuration
     *
     * 4. Running additional setup tasks
     *    - Runs database migrations
     *    - Compiles assets
     *    - Generates application keys
     *    - Performs app-type-specific setup
     *
     * Progress feedback:
     * - In normal mode: Shows spinner with step message
     * - In quiet/json mode: Executes silently
     * - In verbose mode: Shows step duration
     *
     * @param  AppTypeInterface     $appType   App type instance
     * @param  array<string, mixed> $config    Configuration array
     * @param  string               $name      Application name
     * @param  string               $appPath   Full path to app directory
     * @param  bool                 $isQuiet   Suppress output messages
     * @param  bool                 $isJson    Output in JSON format
     * @param  bool                 $isVerbose Show detailed output
     * @return float                Total duration in seconds
     */
    private function executeCreationSteps(
        AppTypeInterface $appType,
        array $config,
        string $name,
        string $appPath,
        bool $isQuiet,
        bool $isJson,
        bool $isVerbose
    ): float {
        // Get monorepo paths
        $root = $this->getMonorepoRoot();
        $appsDir = "{$root}/apps";
        $filesystem = $this->filesystem();

        // Define creation steps as closures
        $steps = [
            'Installing application framework' => fn (): bool => $this->runInstallCommand($appType, $config, $appsDir, $isVerbose),
            'Setting up infrastructure' => fn (): bool => $this->setupInfrastructure($appType, $appPath, $name, $isVerbose),
            'Processing configuration files' => fn () => $this->processStubs($appType, $config, $appPath, $filesystem),
            'Running additional setup tasks' => fn (): bool => $this->runPostInstallCommands($appType, $config, $appPath, $isVerbose),
        ];

        // Add spacing before steps
        if (! $isQuiet && ! $isJson) {
            $this->line('');
        }

        // Track total duration
        $startTime = microtime(true);

        // Execute each step in sequence
        foreach ($steps as $message => $step) {
            $this->executeStep($step, $message, $name, $appType, $isQuiet, $isJson, $isVerbose);
        }

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
     * @param callable         $step      Step closure to execute
     * @param string           $message   Step description for display
     * @param string           $name      Application name (for error messages)
     * @param AppTypeInterface $appType   App type (for error messages)
     * @param bool             $isQuiet   Suppress output messages
     * @param bool             $isJson    Output in JSON format
     * @param bool             $isVerbose Show detailed output
     *
     * @throws Exception If step fails (returns false)
     */
    private function executeStep(
        callable $step,
        string $message,
        string $name,
        AppTypeInterface $appType,
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
                    'app_name' => $name,
                    'app_type' => $appType->getName(),
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
     * Handle command failure.
     *
     * Handles exceptions that occur during application creation by performing
     * cleanup and displaying appropriate error messages.
     *
     * Cleanup process:
     * 1. Check if app directory was created
     * 2. Display cleanup message (unless in quiet/json mode)
     * 3. Call cleanupFailedWorkspace() to:
     *    - Stop and remove Docker containers (docker compose down -v)
     *    - Remove Docker volumes
     *    - Delete the app directory
     *
     * Error message display:
     * - JSON mode: Outputs structured error with success=false
     * - Normal mode: Displays error message with exception details
     *
     * This method is called from the execute() method's catch block when any
     * exception occurs during the creation process, including:
     * - Step failures (from executeStep throwing Exception)
     * - User cancellation (Ctrl+C via signal handlers)
     * - Unexpected errors (database connection, file system, etc.)
     *
     * @param  Exception   $exception  The exception that caused the failure
     * @param  string|null $appPath    Path to app directory (null if not created yet)
     * @param  bool        $appCreated Whether app directory was created
     * @param  bool        $isQuiet    Suppress output messages
     * @param  bool        $isJson     Output in JSON format
     * @return int         Command::FAILURE exit code
     */
    private function handleFailure(
        Exception $exception,
        ?string $appPath,
        bool $appCreated,
        bool $isQuiet,
        bool $isJson
    ): int {
        // Cleanup on failure or cancellation
        if ($appCreated && $appPath !== null) {
            // Display cleanup message
            if (! $isQuiet && ! $isJson) {
                $this->line('');
                $this->warning('Cleaning up failed application...');
            }

            // Remove Docker containers, volumes, and app directory
            $this->cleanupFailedWorkspace($appPath, $isQuiet, $isJson);
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
            $this->error('Application creation failed: ' . $exception->getMessage());
        }

        return Command::FAILURE;
    }

    /**
     * Get and validate application name with smart suggestions.
     *
     * Obtains and validates the application name from command argument with
     * smart suggestions if the name is already taken.
     *
     * Validation rules:
     * - Must be lowercase alphanumeric with hyphens
     * - Pattern: ^[a-z0-9]+(-[a-z0-9]+)*$
     * - Examples: admin, api-gateway, user-service
     * - Invalid: Admin, api_gateway, -admin, admin-
     *
     * Name availability check:
     * - Checks if directory already exists in apps/ directory
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
     * @return string         Validated application name
     */
    private function getValidatedAppName(InputInterface $input, bool $isQuiet, bool $isJson): string
    {
        // Get application name from command argument
        $name = $input->getArgument('name');

        // Validate that name was provided
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

        // Validate name format (lowercase alphanumeric with hyphens)
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

        // Build application path
        $root = $this->getMonorepoRoot();
        $appPath = "{$root}/apps/{$name}";

        // Check if name is available
        if (! $this->checkDirectoryExists($name, $appPath, 'application', $isQuiet, $isJson)) {
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
            'app',
            // Filter suggestions: valid format and available
            fn (?string $suggestedName): bool => is_string($suggestedName) && $suggestedName !== '' && preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $suggestedName) === 1 && ! $this->filesystem()->isDirectory("{$root}/apps/{$suggestedName}")
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

        // Display success message
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
            $relativePath = Str::replace($stubPath . '/', '', $stubFile);

            // Check if this is an append stub
            $isAppendStub = Str::endsWith($relativePath, '.append.stub');

            // Remove .stub or .append.stub extension for target file
            $targetPath = $isAppendStub ? Str::replace('.append.stub', '', $relativePath) : Str::replace('.stub', '', $relativePath);

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
            elseif (Str::endsWith($item, '.stub')) {
                $files[] = $path;
            }
        }

        return $files;
    }
}
