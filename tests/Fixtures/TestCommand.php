<?php

declare(strict_types=1);

namespace MonoPhp\Cli\Tests\Fixtures;

use MonoPhp\Cli\Commands\BaseCommand;
use MonoPhp\Cli\Support\Container;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

/**
 * Test Command Fixture.
 *
 * A simple test command used for testing BaseCommand functionality.
 * Exposes protected methods as public for testing purposes.
 *
 * This fixture is used across multiple test cases to verify
 * BaseCommand behavior without modifying production code.
 */
#[AsCommand(name: 'test:command', description: 'Test command')]
final class TestCommand extends BaseCommand
{
    /**
     * Expose initialize as public for testing.
     */
    public function initialize($input, $output): void
    {
        parent::initialize($input, $output);
    }

    /**
     * Expose isVerbose as public for testing.
     */
    public function isVerbose(): bool
    {
        return parent::isVerbose();
    }

    /**
     * Expose isQuiet as public for testing.
     */
    public function isQuiet(): bool
    {
        return parent::isQuiet();
    }

    /**
     * Expose option as public for testing.
     */
    public function option(string $name): mixed
    {
        return parent::option($name);
    }

    /**
     * Expose hasOption as public for testing.
     */
    public function hasOption(string $name): bool
    {
        return parent::hasOption($name);
    }

    /**
     * Expose container property for testing.
     */
    public function getContainer(): Container
    {
        return $this->container;
    }

    /**
     * Configure the command.
     *
     * Adds test options for testing purposes.
     */
    protected function configure(): void
    {
        $this->addOption('test', null, InputOption::VALUE_OPTIONAL, 'Test option');
    }

    /**
     * Execute the test command.
     *
     * Outputs a simple test message to verify command execution.
     */
    protected function execute($input, $output): int
    {
        $this->line('Test output');

        return self::SUCCESS;
    }
}
