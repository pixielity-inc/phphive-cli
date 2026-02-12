<?php

declare(strict_types=1);

namespace PhpHive\Cli;

use function in_array;

use Override;

use const PHP_SAPI;

use PhpHive\Cli\Concerns\ChecksForUpdates;
use PhpHive\Cli\Concerns\HasDiscovery;
use PhpHive\Cli\Concerns\InteractsWithPrompts;
use PhpHive\Cli\Support\Composer;
use PhpHive\Cli\Support\Container;
use PhpHive\Cli\Support\Docker;
use PhpHive\Cli\Support\Filesystem;
use PhpHive\Cli\Support\Process;
use PhpHive\Cli\Support\Reflection;

use function sprintf;

use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\CommandNotFoundException;
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
    use ChecksForUpdates;
    use HasDiscovery;
    use InteractsWithPrompts;

    /**
     * Application name displayed in banner and version output.
     */
    private const string APP_NAME = 'PhpHive CLI';

    /**
     * Current application version.
     */
    private const string APP_VERSION = '1.0.19';

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
     * and version, creates a new dependency injection container, registers
     * core services, and sets the default command to 'list'.
     */
    public function __construct()
    {
        parent::__construct(self::APP_NAME, self::APP_VERSION);

        // Initialize dependency injection container
        $this->container = new Container();

        // Register core services in the container
        $this->registerServices();

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
            __DIR__ . '/Console/Commands',
            'PhpHive\\Cli\\Console\\Commands',
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
     * Find a command by name or alias.
     *
     * This method overrides Symfony Console's find() to provide better error
     * messages with command suggestions when a command is not found.
     *
     * Uses Laravel Prompts for interactive command selection when alternatives
     * are available, providing a better user experience than plain text suggestions.
     *
     * @param  string  $name The command name or alias
     * @return Command The found command
     *
     * @throws CommandNotFoundException When command is not found
     */
    #[Override]
    public function find(string $name): Command
    {
        try {
            return parent::find($name);
        } catch (CommandNotFoundException $commandNotFoundException) {
            // Get alternatives for better error message
            $alternatives = $this->findAlternatives($name);

            if ($alternatives !== []) {
                // Use Laravel Prompts for interactive selection
                $this->displayBanner();

                $selected = $this->select(
                    label: "Command \"{$name}\" is not defined. Did you mean one of these?",
                    options: array_combine($alternatives, $alternatives),
                    default: $alternatives[0] ?? null,
                );

                // Find and return the selected command
                return parent::find((string) $selected);
            }

            // No alternatives found, throw original exception
            throw $commandNotFoundException;
        }
    }

    /**
     * Get the Filesystem service from the container.
     *
     * Provides convenient access to the Filesystem service for file and
     * directory operations. The Filesystem is registered as a singleton
     * in the container, so the same instance is returned on each call.
     *
     * This method is required by traits that use filesystem operations
     * (ChecksForUpdates, HasDiscovery).
     *
     * @return Filesystem The Filesystem service instance
     */
    protected function filesystem(): Filesystem
    {
        return $this->container->make(Filesystem::class);
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
     * Find command alternatives based on user input.
     *
     * This method enhances Symfony Console's default command suggestion by:
     * - Checking command aliases for better matches
     * - Using Levenshtein distance for fuzzy matching
     * - Suggesting commands from the same namespace
     * - Limiting suggestions to the most relevant matches
     *
     * When a user types an invalid command, this method finds similar commands
     * and suggests them, improving the user experience.
     *
     * @param  string        $name The invalid command name entered by user
     * @return array<string> Array of suggested command names
     */
    private function findAlternatives(string $name): array
    {
        // Get all registered commands
        $allCommands = array_keys($this->all());

        // Calculate Levenshtein distance for each command
        $alternatives = [];
        foreach ($allCommands as $allCommand) {
            // Ensure command name is a string
            if (! is_string($allCommand)) {
                continue;
            }

            // Skip help and list commands
            if (in_array($allCommand, ['help', 'list', 'completion'], true)) {
                continue;
            }

            // Calculate similarity using Levenshtein distance
            // Lower distance = more similar
            $distance = levenshtein($name, $allCommand);

            // Also check aliases
            $command = $this->get($allCommand);
            foreach ($command->getAliases() as $alias) {
                $aliasDistance = levenshtein($name, $alias);
                $distance = min($distance, $aliasDistance);
            }

            // Only suggest if distance is reasonable (less than half the command length)
            if ($distance <= strlen($name) / 2) {
                $alternatives[$allCommand] = $distance;
            }
        }

        // Sort by distance (closest matches first)
        asort($alternatives);

        // Return top 3 suggestions as array values
        return array_values(array_slice(array_keys($alternatives), 0, 3));
    }

    /**
     * Register core services in the dependency injection container.
     *
     * This method registers all core application services as singletons
     * in the container. Services registered here are available to all
     * commands via dependency injection.
     *
     * Registered services:
     * - Filesystem: File and directory operations
     * - Process: Shell command execution with Symfony Process
     * - Composer: Composer dependency management operations
     * - Docker: Docker and Docker Compose container operations
     *
     * All services are registered as singletons to ensure a single instance
     * is shared across the application lifecycle.
     */
    private function registerServices(): void
    {
        // Register Filesystem as singleton
        $this->container->singleton(Filesystem::class, fn (): Filesystem => Filesystem::make());

        // Register Process as singleton
        $this->container->singleton(Process::class, fn (): Process => Process::make());

        // Register Composer as singleton
        $this->container->singleton(Composer::class, fn (): Composer => Composer::make());

        // Register Docker as singleton
        $this->container->singleton(Docker::class, fn (): Docker => Docker::make());
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
     *
     * Also checks for available updates and displays notification if found.
     */
    private function displayBanner(): void
    {
        // Clear terminal for clean presentation
        $this->clear();

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

        // Check for updates before displaying banner
        $this->checkForUpdates(self::APP_VERSION);

        // ASCII art lines
        $lines = [
            '██████╗ ██╗  ██╗██████╗ ██╗  ██╗██╗██╗   ██╗███████╗',
            '██╔══██╗██║  ██║██╔══██╗██║  ██║██║██║   ██║██╔════╝',
            '██████╔╝███████║██████╔╝███████║██║██║   ██║█████╗  ',
            '██╔═══╝ ██╔══██║██╔═══╝ ██╔══██║██║╚██╗ ██╔╝██╔══╝  ',
            '██║     ██║  ██║██║     ██║  ██║██║ ╚████╔╝ ███████╗',
            '╚═╝     ╚═╝  ╚═╝╚═╝     ╚═╝  ╚═╝╚═╝  ╚═══╝  ╚══════╝',
        ];

        // Honey-themed gradients inspired by #F39C12 (Honey Gold)
        $gradients = [
            'Honey' => [214, 208, 202, 178, 172, 136],           // Warm honey gradient
            'Amber' => [220, 214, 208, 202, 178, 172],           // Amber honey
            'Golden' => [226, 220, 214, 208, 202, 178],          // Golden honey
            'Sunset' => [214, 208, 202, 196, 160, 124],          // Sunset honey
            'Caramel' => [180, 174, 168, 162, 136, 130],         // Caramel honey
            'Wildflower' => [221, 215, 209, 203, 179, 173],      // Wildflower honey
        ];

        // Randomly select a gradient theme
        $themeName = array_rand($gradients);
        $gradient = $gradients[$themeName];

        // Display banner with gradient
        echo PHP_EOL;
        foreach ($lines as $index => $line) {
            $color = $gradient[$index];
            echo "\e[38;5;{$color}m{$line}\e[0m" . PHP_EOL;
        }

        // Display tagline and version
        echo PHP_EOL;
        echo "\e[38;5;214mPHP Monorepo Management powered by Turborepo\e[0m \e[90mv" . self::APP_VERSION . "\e[0m" . PHP_EOL;
        echo PHP_EOL;

        // Mark banner as displayed
        self::$bannerDisplayed = true;
    }
}
