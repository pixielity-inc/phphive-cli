<?php

declare(strict_types=1);

namespace MonoPhp\Cli\Commands;

use MonoPhp\Cli\Concerns\InteractsWithComposer;
use MonoPhp\Cli\Concerns\InteractsWithMonorepo;
use MonoPhp\Cli\Concerns\InteractsWithPrompts;
use MonoPhp\Cli\Concerns\InteractsWithTurborepo;
use MonoPhp\Cli\Support\Container;
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
     * // Command: ./bin/mono install --force
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
     * // Command: ./bin/mono create package calculator
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
     * // Command: ./bin/mono install --force
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
}
