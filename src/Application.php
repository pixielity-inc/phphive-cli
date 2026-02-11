<?php

declare(strict_types=1);

namespace MonoPhp\Cli;

use function in_array;
use function Laravel\Prompts\clear;

use MonoPhp\Cli\Concerns\HasDiscovery;
use MonoPhp\Cli\Support\Container;
use MonoPhp\Cli\Support\Reflection;
use Override;

use const PHP_SAPI;

use function sprintf;

use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * CLI Application.
 *
 * The main application class that manages the Symfony Console application,
 * command registration, dependency injection, and application lifecycle.
 *
 * This class extends Symfony Console's Application and adds:
 * - Automatic command discovery from the Command directory
 * - Dependency injection container integration
 * - Beautiful ASCII art banner display
 * - Bootstrap lifecycle management
 * - Container injection into commands
 *
 * The application follows a boot-then-run lifecycle:
 * 1. Instantiation: Creates container and sets defaults
 * 2. Boot: Discovers and registers all commands
 * 3. Run: Displays banner and executes the requested command
 *
 * Example usage:
 * ```php
 * $app = new Application();
 * $app->boot();
 * $exitCode = $app->run();
 * ```
 */
final class Application extends BaseApplication
{
    use HasDiscovery;

    /**
     * Application name displayed in banner and version output.
     */
    private const string APP_NAME = 'Mono CLI';

    /**
     * Current application version.
     */
    private const string APP_VERSION = '1.0.0';

    /**
     * Whether the ASCII art banner has been displayed.
     *
     * Static to ensure banner is only shown once per process,
     * even if multiple Application instances are created.
     */
    private static bool $bannerDisplayed = false;

    /**
     * The dependency injection container.
     *
     * Provides service location and dependency resolution for commands
     * and other application components.
     */
    private Container $container;

    /**
     * Whether the application has been booted.
     *
     * Prevents duplicate command registration if boot() is called multiple times.
     */
    private bool $booted = false;

    /**
     * Create a new CLI application instance.
     *
     * Initializes the Symfony Console application with the configured name
     * and version, creates a new dependency injection container, and sets
     * the default command to 'list'.
     */
    public function __construct()
    {
        parent::__construct(self::APP_NAME, self::APP_VERSION);

        // Initialize dependency injection container
        $this->container = new Container();

        // Set default command (shown when no command is specified)
        $this->setDefaultCommand('list');
    }

    /**
     * Create a new application instance using static factory pattern.
     *
     * This is a convenience method that provides a fluent interface for
     * creating and configuring the application. It automatically boots
     * the application after instantiation.
     *
     * Example usage:
     * ```php
     * $exitCode = Application::make()->run();
     * ```
     *
     * @return static A new, booted application instance
     */
    public static function make(): static
    {
        $app = new self();
        $app->boot();

        return $app;
    }

    /**
     * Boot the application and discover commands.
     *
     * This method performs the application bootstrap process:
     * - Discovers all command classes in the Command directory
     * - Registers discovered commands with the application
     * - Marks the application as booted to prevent duplicate registration
     *
     * This method is idempotent - calling it multiple times has no effect
     * after the first call.
     */
    public function boot(): void
    {
        // Skip if already booted
        if ($this->booted) {
            return;
        }

        // Auto-discover and register all commands in the Commands directory
        $this->discoverCommands(
            __DIR__ . '/Commands',
            'MonoPhp\\Cli\\Commands',
        );

        // Mark as booted to prevent duplicate registration
        $this->booted = true;
    }

    /**
     * Run the application.
     *
     * This method extends Symfony Console's run() to add:
     * - Automatic boot if not already booted
     * - ASCII art banner display
     * - Command execution
     *
     * @param  InputInterface|null  $input  Optional input interface (defaults to stdin)
     * @param  OutputInterface|null $output Optional output interface (defaults to stdout)
     * @return int                  Exit code (0 for success, non-zero for failure)
     */
    #[Override]
    public function run(?InputInterface $input = null, ?OutputInterface $output = null): int
    {
        // Ensure application is booted before running
        if (! $this->booted) {
            $this->boot();
        }

        // Display ASCII art banner (once per process)
        $this->displayBanner();

        // Execute the requested command
        return parent::run($input, $output);
    }

    /**
     * Get the dependency injection container.
     *
     * Provides access to the application's DI container for service
     * resolution and dependency management.
     *
     * @return Container The application's DI container
     */
    public function container(): Container
    {
        return $this->container;
    }

    /**
     * Get the long version string for the application.
     *
     * Returns a formatted string with the application name and version,
     * used by the --version flag and help output.
     *
     * @return string Formatted version string with color codes
     */
    #[Override]
    public function getLongVersion(): string
    {
        return sprintf(
            '<info>%s</info> version <comment>%s</comment>',
            $this->getName(),
            $this->getVersion(),
        );
    }

    /**
     * Register a command with the application.
     *
     * This method instantiates a command class and registers it with the
     * Symfony Console application. If the command has a setContainer()
     * method, the application's container is injected.
     *
     * This method is called by the discovery process for each found command,
     * and can also be called manually to register commands programmatically.
     *
     * @param string $commandClass Fully qualified class name of the command
     */
    protected function registerCommand(string $commandClass): void
    {
        /** @var Command $command */
        $command = new $commandClass();

        // Inject container if command supports it (e.g., extends BaseCommand)
        if (Reflection::methodExists($command, 'setContainer')) {
            // @phpstan-ignore-next-line Method exists on BaseCommand but not on Symfony Command
            $command->setContainer($this->container);
        }

        // Register command with Symfony Console
        $this->addCommand($command);
    }

    /**
     * Display the application ASCII art banner.
     *
     * Shows a colorful ASCII art banner with the application name and version.
     * The banner is only displayed:
     * - Once per process (static flag prevents duplicate display)
     * - In CLI mode (not when running via web server)
     * - For actual commands (skipped for help, list, version)
     *
     * The terminal is cleared before displaying the banner for a clean
     * presentation.
     */
    private function displayBanner(): void
    {
        // Clear terminal for clean presentation
        clear();

        // Only show banner once per process
        if (self::$bannerDisplayed) {
            return;
        }

        // Only show in CLI mode (not web server)
        if (PHP_SAPI !== 'cli') {
            return;
        }

        // Skip banner for help/list/version commands
        if (isset($_SERVER['argv'][1]) && in_array($_SERVER['argv'][1], ['help', 'list', '--help', '-h', '--version', '-V'], true)) {
            return;
        }

        // ASCII art banner with ANSI color codes
        // \e[36m = cyan, \e[33m = yellow, \e[90m = gray, \e[0m = reset
        $banner = "\e[36m" . PHP_EOL
            . '███╗   ███╗ ██████╗ ███╗   ██╗ ██████╗' . PHP_EOL
            . '████╗ ████║██╔═══██╗████╗  ██║██╔═══██╗' . PHP_EOL
            . '██╔████╔██║██║   ██║██╔██╗ ██║██║   ██║' . PHP_EOL
            . '██║╚██╔╝██║██║   ██║██║╚██╗██║██║   ██║' . PHP_EOL
            . '██║ ╚═╝ ██║╚██████╔╝██║ ╚████║╚██████╔╝' . PHP_EOL
            . '╚═╝     ╚═╝ ╚═════╝ ╚═╝  ╚═══╝ ╚═════╝' . PHP_EOL
            . "\e[0m" . PHP_EOL
            . "\e[33mPHP Turborepo Monorepo Management\e[0m \e[90mv" . self::APP_VERSION . "\e[0m" . PHP_EOL
            . PHP_EOL;

        echo $banner;

        // Mark banner as displayed
        self::$bannerDisplayed = true;
    }
}
