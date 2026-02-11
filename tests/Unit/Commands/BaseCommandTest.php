<?php

declare(strict_types=1);

namespace MonoPhp\Cli\Tests\Unit\Commands;

use MonoPhp\Cli\Support\Container;
use MonoPhp\Cli\Tests\Fixtures\TestCommand;
use MonoPhp\Cli\Tests\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Base Command Test.
 *
 * Tests for the BaseCommand abstract class that provides common functionality
 * for all CLI commands. Verifies container injection, output methods, option
 * handling, and verbosity checks.
 */
final class BaseCommandTest extends TestCase
{
    /**
     * The test command instance.
     */
    private TestCommand $command;

    /**
     * Mock input for testing.
     */
    private ArrayInput $input;

    /**
     * Buffered output for capturing command output.
     */
    private BufferedOutput $output;

    /**
     * Set up the test environment before each test.
     *
     * Creates a test command instance with mock input/output and
     * injects a container instance.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Create test command
        $this->command = new TestCommand();

        // Create mock input and output
        $this->input = new ArrayInput([]);
        $this->output = new BufferedOutput();

        // Inject container
        $container = new Container();
        $this->command->setContainer($container);
    }

    /**
     * Test that container can be set and accessed.
     *
     * Verifies that the setContainer() method properly injects
     * the container instance into the command.
     */
    public function test_can_set_container(): void
    {
        // Create a new container
        $container = new Container();

        // Set the container
        $this->command->setContainer($container);

        // Assert container is accessible
        $this->assertInstanceOf(Container::class, $this->command->getContainer());
    }

    /**
     * Test that line() writes output to the console.
     *
     * Verifies that the line() method correctly writes messages
     * to the output buffer.
     */
    public function test_line_writes_output(): void
    {
        // Run the command
        $this->command->run($this->input, $this->output);

        // Get the output content
        $content = $this->output->fetch();

        // Assert the expected output is present
        $this->assertStringContainsString('Test output', $content);
    }

    /**
     * Test that isVerbose() checks verbosity level.
     *
     * Verifies that the method correctly identifies non-verbose mode.
     */
    public function test_is_verbose_checks_verbosity(): void
    {
        // Initialize command with input/output
        $this->command->initialize($this->input, $this->output);

        // Assert not in verbose mode by default
        $this->assertFalse($this->command->isVerbose());
    }

    /**
     * Test that isQuiet() checks quiet mode.
     *
     * Verifies that the method correctly identifies non-quiet mode.
     */
    public function test_is_quiet_checks_quiet_mode(): void
    {
        // Initialize command with input/output
        $this->command->initialize($this->input, $this->output);

        // Assert not in quiet mode by default
        $this->assertFalse($this->command->isQuiet());
    }

    /**
     * Test that option() retrieves option values.
     *
     * Verifies that the method returns the correct value for defined options.
     */
    public function test_option_retrieves_option_value(): void
    {
        // Create a new command instance to get proper definition
        $command = new TestCommand();

        // Create input with the test option
        $definition = $command->getDefinition();
        $input = new ArrayInput(['--test' => 'value'], $definition);
        $output = new BufferedOutput();

        // Initialize command
        $command->initialize($input, $output);

        // Assert test option returns the value
        $this->assertSame('value', $command->option('test'));
    }

    /**
     * Test that hasOption() checks option existence.
     *
     * Verifies that the method correctly identifies non-existent options.
     */
    public function test_has_option_checks_option_existence(): void
    {
        // Create input without options
        $input = new ArrayInput([]);

        // Initialize command
        $this->command->initialize($input, $this->output);

        // Assert non-existent option returns false
        $this->assertFalse($this->command->hasOption('nonexistent'));
    }
}
