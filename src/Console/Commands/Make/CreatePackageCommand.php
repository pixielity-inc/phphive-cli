<?php

declare(strict_types=1);

namespace PhpHive\Cli\Console\Commands\Make;

use Exception;
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

use function str_replace;

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

        // Store mode flags for signal handler
        $this->isQuietMode = $isQuiet;
        $this->isJsonMode = $isJson;

        // Register signal handlers for Ctrl+C cleanup
        $this->registerSignalHandlers();

        // Track package path for cleanup on failure
        $packagePath = null;
        $packageCreated = false;

        try {
            // Display intro banner (skip in quiet/json mode)
            // Step 1: Run preflight checks
            if (! $isQuiet && ! $isJson) {
                $this->intro('Package Creation');
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

            // Step 2: Get and validate package name with smart suggestions
            $name = $this->getValidatedPackageName($input, $isQuiet, $isJson);

            // Step 3: Determine package type (prompt if not provided)
            $type = $input->getOption('type');
            $packageTypeFactory = $this->packageTypeFactory();

            if ($type === null) {
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
                if ($isJson) {
                    $this->outputJson([
                        'success' => false,
                        'error' => $invalidArgumentException->getMessage(),
                    ]);
                } else {
                    $this->error($invalidArgumentException->getMessage());
                }

                return Command::FAILURE;
            }

            if (! $isQuiet && ! $isJson) {
                $this->line('');
                $this->comment("Selected: {$packageType->getDisplayName()}");
                $this->line('');
            }

            // Step 4: Execute package creation steps with progress feedback
            $root = $this->getMonorepoRoot();
            $packagePath = "{$root}/packages/{$name}";

            // Mark that package directory will be created and store for signal handler
            $packageCreated = true;
            $this->workspacePathForCleanup = $packagePath;
            $this->workspaceCreatedForCleanup = true;

            $steps = [
                'Checking name availability' => fn (): bool => $this->checkNameAvailability($name, $packagePath),
                'Creating package structure' => fn (): bool => $this->createPackageStructure($packagePath),
                'Generating configuration files' => fn (): bool => $this->generateConfigFiles($input, $name, $type, $packagePath, $packageType, $isVerbose),
            ];

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
                            'package_name' => $name,
                            'package_type' => $type,
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

            // Step 5: Install dependencies with progress feedback
            if (! $isQuiet && ! $isJson) {
                $this->line('');
            }

            $installStartTime = microtime(true);
            if ($isQuiet || $isJson) {
                $installResult = $this->installDependencies($packageType, $packagePath);
            } else {
                $installResult = $this->spin(
                    fn (): bool => $this->installDependencies($packageType, $packagePath),
                    'Installing dependencies...'
                );
            }
            $installDuration = microtime(true) - $installStartTime;

            if ($installResult) {
                if (! $isQuiet && ! $isJson) {
                    $this->comment('✓ Dependencies installed successfully');
                } elseif ($isVerbose && ! $isJson) {
                    $this->comment(sprintf('✓ Dependencies installed successfully (%.2fs)', $installDuration));
                }
            } elseif (! $isQuiet && ! $isJson) {
                $this->warning('⚠ Dependency installation had issues (you may need to run composer install manually)');
            }

            $totalDuration = microtime(true) - $startTime;

            // Step 6: Display success summary
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
            // Cleanup on failure or cancellation
            if ($packageCreated && $packagePath !== null) {
                if (! $isQuiet && ! $isJson) {
                    $this->line('');
                    $this->warning('Cleaning up failed package...');
                }

                $this->cleanupFailedWorkspace($packagePath, $isQuiet, $isJson);
            }

            // Display error message
            if ($isJson) {
                $this->outputJson([
                    'success' => false,
                    'error' => $exception->getMessage(),
                ]);
            } else {
                $this->error('Package creation failed: ' . $exception->getMessage());
            }

            return Command::FAILURE;
        }
    }

    /**
     * Get and validate package name with smart suggestions.
     *
     * @return string Validated package name
     */
    private function getValidatedPackageName(InputInterface $input, bool $isQuiet, bool $isJson): string
    {
        $name = $input->getArgument('name');

        // Validate the name format first (inline validation)
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

        $root = $this->getMonorepoRoot();
        $packagePath = "{$root}/packages/{$name}";

        // Check if name is available
        if (! $this->checkDirectoryExists($name, $packagePath, 'package', $isQuiet, $isJson)) {
            return $name;
        }

        // Name is taken, offer suggestions
        if (! $isQuiet && ! $isJson) {
            $this->line('');
        }

        $nameSuggestionService = NameSuggestionService::make();
        $suggestions = $nameSuggestionService->suggest(
            $name,
            'package',
            fn (?string $suggestedName): bool => is_string($suggestedName) && $suggestedName !== '' && preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $suggestedName) === 1 && ! $this->filesystem()->isDirectory("{$root}/packages/{$suggestedName}")
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

        if (! $isQuiet && ! $isJson) {
            $this->info("✓ Package name '{$choice}' is available");
        }

        return $choice;
    }

    /**
     * Check if package name is available.
     *
     * @param  string $name        Package name
     * @param  string $packagePath Full package path
     * @return bool   True if available
     */
    private function checkNameAvailability(string $name, string $packagePath): bool
    {
        if ($this->filesystem()->isDirectory($packagePath)) {
            $this->error("Package '{$name}' already exists");

            return false;
        }

        return true;
    }

    /**
     * Create package directory structure.
     *
     * @param  string $packagePath Full package path
     * @return bool   True on success
     */
    private function createPackageStructure(string $packagePath): bool
    {
        try {
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
     * @param  InputInterface       $input       Command input
     * @param  string               $name        Package name
     * @param  string               $type        Package type
     * @param  string               $packagePath Full package path
     * @param  PackageTypeInterface $packageType Package type instance
     * @param  bool                 $isVerbose   Show verbose output
     * @return bool                 True on success
     */
    private function generateConfigFiles(InputInterface $input, string $name, string $type, string $packagePath, PackageTypeInterface $packageType, bool $isVerbose): bool
    {
        try {
            // Get stub path for the selected package type
            $stubsBasePath = dirname(__DIR__, 4) . '/stubs';
            $stubPath = $packageType->getStubPath($stubsBasePath);

            if (! $this->filesystem()->isDirectory($stubPath)) {
                $this->error("Stub directory not found for package type '{$type}' at: {$stubPath}");

                return false;
            }

            // Prepare stub variables using package type
            $description = $input->getOption('description') ?? "A {$type} package";
            $variables = $packageType->prepareVariables($name, $description);

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
     * @param  PackageTypeInterface $packageType Package type instance
     * @param  string               $packagePath Full package path
     * @return bool                 True on success
     */
    private function installDependencies(PackageTypeInterface $packageType, string $packagePath): bool
    {
        try {
            $packageType->postCreate($packagePath);

            return true;
        } catch (Exception) {
            // Log error but don't fail the command
            return false;
        }
    }

    /**
     * Copy stub files to package directory with variable replacement.
     *
     * @param string                $stubPath    Source stub directory
     * @param string                $packagePath Destination package directory
     * @param array<string, string> $variables   Variables for template replacement
     * @param Filesystem            $filesystem  Filesystem service
     * @param array<string, string> $namingRules File naming rules for special files
     * @param bool                  $isVerbose   Show verbose output
     */
    private function copyStubFiles(string $stubPath, string $packagePath, array $variables, Filesystem $filesystem, array $namingRules = [], bool $isVerbose = false): void
    {
        // Set base path for Stub facade
        Stub::setBasePath($stubPath);

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($stubPath, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $relativePath = substr($item->getPathname(), strlen($stubPath) + 1);
            $destinationPath = $packagePath . '/' . $relativePath;

            if ($item->isDir()) {
                $filesystem->makeDirectory($destinationPath, 0755, true);
            } else {
                // Remove .stub extension if present
                $targetPath = $relativePath;
                if (str_ends_with($targetPath, '.stub')) {
                    $targetPath = substr($targetPath, 0, -5);
                }

                // Apply naming rules from package type
                foreach ($namingRules as $pattern => $replacement) {
                    // Normalize paths for comparison (remove leading slashes)
                    $normalizedPattern = ltrim($pattern, '/');
                    $normalizedTarget = ltrim($targetPath, '/');

                    if ($normalizedTarget === $normalizedPattern) {
                        // Convert variable keys to {{UPPERCASE}} format for replacement
                        $replacementVars = [];
                        foreach ($variables as $key => $value) {
                            $replacementVars['{{' . strtoupper($key) . '}}'] = $value;
                        }

                        // Replace pattern with actual values from variables
                        $replacedPattern = str_replace(array_keys($replacementVars), array_values($replacementVars), $replacement);
                        // Remove leading slash from replacement for consistency
                        $targetPath = ltrim($replacedPattern, '/');

                        break;
                    }
                }

                // For JSON files, escape backslashes in namespace values
                $isJsonFile = str_ends_with($targetPath, '.json');
                $variablesToUse = $variables;

                if ($isJsonFile && isset($variables[PackageTypeInterface::NAMESPACE])) {
                    // Escape single backslashes to double backslashes for JSON
                    // But don't double-escape already escaped backslashes
                    $namespace = $variables[PackageTypeInterface::NAMESPACE];
                    $variablesToUse[PackageTypeInterface::NAMESPACE] = str_replace('\\', '\\\\', $namespace);
                }

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
