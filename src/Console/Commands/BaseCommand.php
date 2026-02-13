<?php

declare(strict_types=1);

namespace PhpHive\Cli\Console\Commands;

use function in_array;
use function json_encode;

use const JSON_PRETTY_PRINT;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

use PhpHive\Cli\Concerns\InteractsWithComposer;
use PhpHive\Cli\Concerns\InteractsWithMonorepo;
use PhpHive\Cli\Concerns\InteractsWithPrompts;
use PhpHive\Cli\Concerns\InteractsWithTurborepo;
use PhpHive\Cli\Factories\AppTypeFactory;
use PhpHive\Cli\Factories\PackageTypeFactory;
use PhpHive\Cli\Services\NameSuggestionService;
use PhpHive\Cli\Support\Composer;
use PhpHive\Cli\Support\Container;
use PhpHive\Cli\Support\Docker;
use PhpHive\Cli\Support\Filesystem;
use PhpHive\Cli\Support\PreflightChecker;
use PhpHive\Cli\Support\Process;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Base Command Class.
 *
 * This abstract class serves as the foundation for all CLI commands in the monorepo.
 * It extends Symfony Console's Command class and integrates multiple concerns to provide
 * a rich set of functionality for interacting with the monorepo environment.
 *
 * Features:
 * - Composer integration for PHP dependency management
 * - Turborepo integration for task orchestration
 * - Monorepo workspace discovery and management
 * - Laravel Prompts for beautiful interactive CLI prompts
 * - Dependency injection container support
 * - Convenient output helpers and verbosity checks
 *
 * All custom commands should extend this class to inherit these capabilities.
 *
 * Example usage:
 * ```php
 * class InstallCommand extends BaseCommand
 * {
 *     protected function configure(): void
 *     {
 *         $this->setName('install')
 *              ->setDescription('Install dependencies');
 *     }
 *
 *     protected function execute(InputInterface $input, OutputInterface $output): int
 *     {
 *         $this->intro('Installing dependencies...');
 *         $this->turboRun('composer:install');
 *         $this->outro('Installation complete!');
 *         return Command::SUCCESS;
 *     }
 * }
 * ```
 */
abstract class BaseCommand extends Command
{
    use InteractsWithComposer;
    use InteractsWithMonorepo;
    use InteractsWithPrompts;
    use InteractsWithTurborepo;

    /**
     * The input interface for reading command arguments and options.
     *
     * Provides access to user input passed to the command via CLI arguments,
     * options, and interactive prompts.
     */
    protected InputInterface $input;

    /**
     * The output interface for writing messages to the console.
     *
     * Used to display information, warnings, errors, and other messages
     * to the user during command execution.
     */
    protected OutputInterface $output;

    /**
     * The dependency injection container.
     *
     * Provides access to application services and allows for dependency
     * resolution throughout the command lifecycle.
     */
    protected Container $container;

    /**
     * Set the dependency injection container.
     *
     * This method is called by the Application during command registration
     * to inject the container instance. The container provides access to
     * application services and enables dependency resolution.
     *
     * @param Container $container The DI container instance
     */
    public function setContainer(Container $container): void
    {
        $this->container = $container;
    }

    /**
     * Get the dependency injection container.
     *
     * Provides access to the application's DI container for service
     * resolution and dependency management. Use this to resolve services
     * that are registered in the container.
     *
     * Example:
     * ```php
     * $customService = $this->container()->make(CustomService::class);
     * ```
     *
     * @return Container The DI container instance
     */
    protected function container(): Container
    {
        return $this->container;
    }

    /**
     * Get the Filesystem service from the container.
     *
     * Provides convenient access to the Filesystem service for file and
     * directory operations. The Filesystem is registered as a singleton
     * in the container, so the same instance is returned on each call.
     *
     * Example:
     * ```php
     * if ($this->filesystem()->exists('/path/to/file')) {
     *     $content = $this->filesystem()->read('/path/to/file');
     * }
     * ```
     *
     * @return Filesystem The Filesystem service instance
     */
    protected function filesystem(): Filesystem
    {
        return $this->container->make(Filesystem::class);
    }

    /**
     * Get the Process service from the container.
     *
     * Provides convenient access to the Process service for executing
     * shell commands. The Process service wraps Symfony Process with
     * common patterns and error handling.
     *
     * Example:
     * ```php
     * $output = $this->process()->run(['ls', '-la'], '/path/to/dir');
     * if ($this->process()->commandExists('docker')) {
     *     // Docker is available
     * }
     * ```
     *
     * @return Process The Process service instance
     */
    protected function process(): Process
    {
        return $this->container->make(Process::class);
    }

    /**
     * Get the Composer service from the container.
     *
     * Provides convenient access to the Composer service for dependency
     * management operations. The Composer service wraps common Composer
     * commands with error handling and validation.
     *
     * Example:
     * ```php
     * $this->composer()->install('/path/to/project');
     * $this->composer()->require('/path/to/project', 'symfony/console');
     * if ($this->composer()->isInstalled()) {
     *     $version = $this->composer()->getVersion();
     * }
     * ```
     *
     * @return Composer The Composer service instance
     */
    /**
     * Get the Composer service from the container.
     *
     * Provides convenient access to the Composer service for dependency
     * management operations. The Composer service wraps common Composer
     * commands with error handling and validation.
     *
     * Example:
     * ```php
     * $this->composerService()->install('/path/to/project');
     * $this->composerService()->require('/path/to/project', 'symfony/console');
     * if ($this->composerService()->isInstalled()) {
     *     $version = $this->composerService()->getVersion();
     * }
     * ```
     *
     * @return Composer The Composer service instance
     */
    protected function composerService(): Composer
    {
        return $this->container->make(Composer::class);
    }

    /**
     * Get the Docker service from the container.
     *
     * Provides convenient access to the Docker service for container
     * management operations. The Docker service wraps Docker and Docker
     * Compose commands with error handling and validation.
     *
     * Example:
     * ```php
     * if ($this->docker()->isInstalled()) {
     *     $this->docker()->composeUp('/path/to/project');
     * }
     * ```
     *
     * @return Docker The Docker service instance
     */
    /**
     * Get the Docker service from the container.
     *
     * Provides convenient access to the Docker service for container
     * management operations. The Docker service wraps Docker and Docker
     * Compose commands with error handling and validation.
     *
     * Example:
     * ```php
     * if ($this->dockerService()->isInstalled()) {
     *     $this->dockerService()->composeUp('/path/to/project');
     * }
     * ```
     *
     * @return Docker The Docker service instance
     */
    protected function dockerService(): Docker
    {
        return $this->container->make(Docker::class);
    }

    /**
     * Get the PreflightChecker service instance.
     *
     * Returns the PreflightChecker service for validating the development
     * environment before executing commands. The service checks for:
     * - Required system dependencies (PHP, Composer, Node.js, etc.)
     * - Correct versions of tools
     * - Proper configuration
     * - Available disk space
     *
     * Example usage:
     * ```php
     * $checker = $this->preflightChecker();
     * if (!$checker->checkDocker()) {
     *     $this->error('Docker is not available');
     *     return Command::FAILURE;
     * }
     * ```
     *
     * @return PreflightChecker The PreflightChecker service instance
     */
    protected function preflightChecker(): PreflightChecker
    {
        return $this->container->make(PreflightChecker::class);
    }

    /**
     * Get the PackageTypeFactory service instance.
     *
     * Returns the PackageTypeFactory for creating package type instances.
     * Package types define how different types of packages (library, bundle,
     * plugin, etc.) are scaffolded and configured.
     *
     * Example usage:
     * ```php
     * $factory = $this->packageTypeFactory();
     * $packageType = $factory->create('library');
     * $config = $packageType->collectConfiguration($input, $output);
     * ```
     *
     * @return PackageTypeFactory The PackageTypeFactory service instance
     */
    protected function packageTypeFactory(): PackageTypeFactory
    {
        return $this->container->make(PackageTypeFactory::class);
    }

    /**
     * Get the NameSuggestionService service instance.
     *
     * Returns the NameSuggestionService for generating alternative names
     * when conflicts are detected. The service provides intelligent
     * suggestions based on existing names in the workspace.
     *
     * Example usage:
     * ```php
     * $service = $this->nameSuggestionService();
     * if ($this->filesystem()->exists($path)) {
     *     $suggestion = $service->suggest($name, $existingNames);
     *     $name = $this->text('Name already exists. Try', $suggestion);
     * }
     * ```
     *
     * @return NameSuggestionService The NameSuggestionService service instance
     */
    protected function nameSuggestionService(): NameSuggestionService
    {
        return $this->container->make(NameSuggestionService::class);
    }

    /**
     * Get the AppTypeFactory service instance.
     *
     * Returns the AppTypeFactory for creating application type instances.
     * App types define how different types of applications (Laravel, Symfony,
     * Magento, etc.) are scaffolded and configured.
     *
     * Example usage:
     * ```php
     * $factory = $this->appTypeFactory();
     * $appType = $factory->create('laravel');
     * $config = $appType->collectConfiguration($input, $output);
     * ```
     *
     * @return AppTypeFactory The AppTypeFactory service instance
     */
    protected function appTypeFactory(): AppTypeFactory
    {
        return $this->container->make(AppTypeFactory::class);
    }

    /**
     * Configure common options available to all commands.
     *
     * This method defines a set of standard options that are available across
     * all commands in the CLI application. These options provide consistent
     * behavior for common operations like workspace targeting, cache control,
     * and interaction modes.
     *
     * Common Options:
     * - --workspace, -w: Target a specific workspace (e.g., demo-app, calculator)
     * - --force, -f: Force operation by ignoring cache
     * - --no-cache: Disable Turbo cache for this run
     * - --no-interaction, -n: Run in non-interactive mode
     * - --all: Apply operation to all workspaces
     * - --dry-run: Preview what would happen without executing
     *
     * Note: Some commands may define additional options like --json or --parallel
     * specific to their functionality. Check individual command help for details.
     *
     * Child commands should call parent::configure() first to inherit these
     * common options, then add their specific options and arguments:
     *
     * Example:
     * ```php
     * protected function configure(): void
     * {
     *     parent::configure(); // Inherit common options
     *
     *     $this->setName('my-command')
     *          ->setDescription('My custom command')
     *          ->addArgument('name', InputArgument::REQUIRED, 'The name');
     * }
     * ```
     */
    protected function configure(): void
    {
        $this->addOption(
            'workspace',
            'w',
            InputOption::VALUE_REQUIRED,
            'Target specific workspace (e.g., demo-app, calculator)',
        );

        $this->addOption(
            'force',
            'f',
            InputOption::VALUE_NONE,
            'Force operation by ignoring cache',
        );

        $this->addOption(
            'no-cache',
            null,
            InputOption::VALUE_NONE,
            'Disable Turbo cache for this run',
        );

        $this->addOption(
            'no-interaction',
            'n',
            InputOption::VALUE_NONE,
            'Run in non-interactive mode',
        );

        $this->addOption(
            'all',
            null,
            InputOption::VALUE_NONE,
            'Apply operation to all workspaces',
        );

        $this->addOption(
            'dry-run',
            null,
            InputOption::VALUE_NONE,
            'Preview what would happen without executing',
        );
    }

    /**
     * Initialize the command before execution.
     *
     * This method is called by Symfony Console before execute() runs.
     * It stores references to the input and output interfaces for easy
     * access throughout the command lifecycle.
     *
     * Override this method in child classes to perform custom initialization,
     * but always call parent::initialize() to ensure proper setup.
     *
     * @param InputInterface  $input  The input interface
     * @param OutputInterface $output The output interface
     */
    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        // Store input and output for convenient access in command methods
        $this->input = $input;
        $this->output = $output;

        // Set output for prompt methods
        self::setOutput($output);

        // Call parent initialization to maintain Symfony Console behavior
        parent::initialize($input, $output);
    }

    /**
     * Check if the command is running in verbose mode.
     *
     * Verbose mode is enabled with the -v option and provides additional
     * output details during command execution. Use this to conditionally
     * display extra information that might be helpful for debugging.
     *
     * @return bool True if verbose mode is enabled, false otherwise
     */
    protected function isVerbose(): bool
    {
        return $this->output->isVerbose();
    }

    /**
     * Check if the command is running in debug mode.
     *
     * Debug mode is enabled with the -vv or -vvv options and provides
     * the most detailed output. Use this for diagnostic information
     * that's only needed when troubleshooting issues.
     *
     * @return bool True if debug mode is enabled, false otherwise
     */
    protected function isDebug(): bool
    {
        return $this->output->isDebug();
    }

    /**
     * Check if the command is running in quiet mode.
     *
     * Quiet mode is enabled with the -q option and suppresses all output
     * except errors. Use this to check if you should skip informational
     * messages.
     *
     * @return bool True if quiet mode is enabled, false otherwise
     */
    protected function isQuiet(): bool
    {
        return $this->output->isQuiet();
    }

    /**
     * Write a line of text to the console output.
     *
     * This is a convenience method that wraps Symfony's writeln() with
     * optional styling support. The style parameter accepts any valid
     * Symfony Console style tag (info, comment, question, error).
     *
     * Example:
     * ```php
     * $this->line('Processing...', 'info');
     * $this->line('Done!');
     * ```
     *
     * @param string $message The message to display
     * @param string $style   Optional style tag (info, comment, question, error)
     */
    protected function line(string $message, string $style = ''): void
    {
        // Wrap message in style tags if a style is specified
        if ($style !== '' && $style !== '0') {
            $message = "<{$style}>{$message}</{$style}>";
        }

        $this->output->writeln($message);
    }

    /**
     * Write a comment-styled line to the console.
     *
     * Comments are typically displayed in a muted color (gray) and are
     * useful for supplementary information that's less important than
     * primary output.
     *
     * @param string $message The comment message to display
     */
    protected function comment(string $message): void
    {
        $this->line($message, 'comment');
    }

    /**
     * Write a question-styled line to the console.
     *
     * Questions are typically displayed in a distinct color (cyan/blue)
     * and are useful for prompting the user or highlighting interactive
     * elements.
     *
     * @param string $message The question message to display
     */
    protected function question(string $message): void
    {
        $this->line($message, 'question');
    }

    /**
     * Get the value of a command option.
     *
     * Options are passed to commands using the --option-name syntax.
     * This method retrieves the value of a named option, returning
     * null if the option wasn't provided.
     *
     * Example:
     * ```php
     * // Command: ./bin/hive install --force
     * $force = $this->option('force'); // Returns true
     * ```
     *
     * @param  string $name The option name
     * @return mixed  The option value, or null if not set
     */
    protected function option(string $name): mixed
    {
        return $this->input->getOption($name);
    }

    /**
     * Get the value of a command argument.
     *
     * Arguments are positional parameters passed to commands without
     * the -- prefix. This method retrieves the value of a named argument.
     *
     * Example:
     * ```php
     * // Command: ./bin/hive create package calculator
     * $type = $this->argument('type');     // Returns 'package'
     * $name = $this->argument('name');     // Returns 'calculator'
     * ```
     *
     * @param  string $name The argument name
     * @return mixed  The argument value, or null if not set
     */
    protected function argument(string $name): mixed
    {
        return $this->input->getArgument($name);
    }

    /**
     * Check if an option exists and has a truthy value.
     *
     * This is a convenience method that combines checking if an option
     * is defined and if it has a truthy value. Useful for boolean flags.
     *
     * Example:
     * ```php
     * // Command: ./bin/hive install --force
     * if ($this->hasOption('force')) {
     *     // Force flag is present and true
     * }
     * ```
     *
     * @param  string $name The option name to check
     * @return bool   True if the option exists and is truthy, false otherwise
     */
    protected function hasOption(string $name): bool
    {
        // First check if the option is defined in the command
        if (! $this->input->hasOption($name)) {
            return false;
        }

        $optionValue = $this->option($name);

        return in_array($optionValue, [true, '1', 1], true);
    }

    /**
     * Output data in JSON format.
     *
     * This method formats and outputs data as pretty-printed JSON to the console.
     * It's useful for commands that need to provide machine-readable output for
     * scripting, automation, or integration with other tools.
     *
     * The JSON output is formatted with proper indentation (4 spaces) and includes
     * unescaped slashes and unicode characters for better readability.
     *
     * Example:
     * ```php
     * $data = [
     *     'workspaces' => ['demo-app', 'calculator'],
     *     'status' => 'success',
     * ];
     * $this->outputJson($data);
     * ```
     *
     * @param array<mixed> $data The data to output as JSON
     */
    protected function outputJson(array $data): void
    {
        // Encode data as pretty-printed JSON with readable formatting
        $json = json_encode(
            $data,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        // Handle encoding failure
        if ($json === false) {
            throw new RuntimeException('Failed to encode data as JSON');
        }

        // Output the JSON string to console
        $this->output->writeln($json);
    }
}
