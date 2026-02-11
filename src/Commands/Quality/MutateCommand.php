<?php

declare(strict_types=1);

namespace MonoPhp\Cli\Commands\Quality;

use MonoPhp\Cli\Commands\BaseCommand;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

/**
 * Mutate Command.
 *
 * This command runs Infection mutation testing to measure the quality and
 * effectiveness of your test suite. It introduces small changes (mutations)
 * to your code and verifies that your tests catch these bugs. A high mutation
 * score indicates strong test coverage and quality.
 *
 * The mutation testing process:
 * 1. Runs your test suite to establish baseline
 * 2. Creates mutants (modified versions of your code)
 * 3. Runs tests against each mutant
 * 4. Tracks which mutants are killed (caught by tests)
 * 5. Calculates Mutation Score Indicator (MSI)
 * 6. Reports escaped mutants (bugs tests missed)
 *
 * Mutation Score Indicator (MSI):
 * - MSI = (killed mutants / total mutants) × 100
 * - Higher MSI = better test quality
 * - 80%+ is considered good
 * - 90%+ is excellent
 *
 * Features:
 * - Parallel execution with configurable threads
 * - Customizable MSI thresholds
 * - Covered code MSI tracking
 * - Detailed mutation reports
 * - Show all mutations option
 * - Integration with PHPUnit
 *
 * Example usage:
 * ```bash
 * # Run with default settings (MSI 80%, 4 threads)
 * ./cli/bin/mono mutate
 *
 * # Set custom MSI threshold
 * ./cli/bin/mono mutate --min-msi=90
 *
 * # Use more threads for faster execution
 * ./cli/bin/mono mutate --threads=8
 *
 * # Show all mutations (including killed ones)
 * ./cli/bin/mono mutate --show-mutations
 *
 * # Strict settings for CI/CD
 * ./cli/bin/mono infection --min-msi=85 --min-covered-msi=90
 * ```
 *
 * @see BaseCommand For inherited functionality
 * @see TestCommand For running tests before mutation testing
 */
#[AsCommand(
    name: 'mutate',
    description: 'Run Infection mutation testing',
    aliases: ['infection', 'mutation'],
)]
final class MutateCommand extends BaseCommand
{
    /**
     * Configure the command options.
     *
     * Defines command-specific options for mutation testing. Common options
     * (workspace, force, no-cache, no-interaction) are inherited from BaseCommand.
     *
     * @see BaseCommand::configure() For inherited common options
     */
    #[Override]
    protected function configure(): void
    {
        parent::configure();

        $this
            ->addOption(
                'min-msi',
                null,
                InputOption::VALUE_REQUIRED,
                'Minimum Mutation Score Indicator (default: 80)',
                '80',
            )
            ->addOption(
                'min-covered-msi',
                null,
                InputOption::VALUE_REQUIRED,
                'Minimum Covered Code MSI (default: 85)',
                '85',
            )
            ->addOption(
                'threads',
                't',
                InputOption::VALUE_REQUIRED,
                'Number of threads to use (default: 4)',
                '4',
            )
            ->addOption(
                'show-mutations',
                null,
                InputOption::VALUE_NONE,
                'Show all mutations',
            )
            ->setHelp(
                <<<'HELP'
                The <info>mutate</info> command runs Infection mutation testing.

                <comment>Examples:</comment>
                  <info>mono mutate</info>                    Run with default settings
                  <info>mono mutate --min-msi=80</info>       Set minimum MSI threshold
                  <info>mono mutate --threads=8</info>        Use 8 threads
                  <info>mono mutate --show-mutations</info>   Show all mutations

                Mutation testing measures the quality of your tests by introducing
                bugs (mutations) and checking if tests catch them.
                HELP
            );
    }

    /**
     * Execute the mutate command.
     *
     * This method orchestrates the entire mutation testing process:
     * 1. Displays an intro message
     * 2. Extracts user options (MSI thresholds, threads)
     * 3. Builds the Infection command with options
     * 4. Executes Infection with TTY support
     * 5. Reports mutation testing results
     *
     * @param  InputInterface  $input  Command input (arguments and options)
     * @param  OutputInterface $output Command output (for displaying messages)
     * @return int             Exit code (0 for success, 1 for failure)
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Display intro banner
        $this->intro('Running mutation testing...');

        // Get monorepo root directory
        $root = $this->getMonorepoRoot();

        // Extract options from user input
        $minMsiOption = $input->getOption('min-msi');
        $minCoveredMsiOption = $input->getOption('min-covered-msi');
        $threadsOption = $input->getOption('threads');
        $minMsi = is_string($minMsiOption) ? $minMsiOption : '80';
        $minCoveredMsi = is_string($minCoveredMsiOption) ? $minCoveredMsiOption : '85';
        $threads = is_string($threadsOption) ? $threadsOption : '4';
        $showMutations = $this->hasOption('show-mutations');

        // Build Infection command with required options
        // --threads: Number of parallel processes
        // --min-msi: Minimum Mutation Score Indicator threshold
        // --min-covered-msi: Minimum MSI for covered code only
        $command = "vendor/bin/infection --threads={$threads} --min-msi={$minMsi} --min-covered-msi={$minCoveredMsi}";

        // Add optional show-mutations flag
        // This displays all mutations, not just escaped ones
        if ($showMutations) {
            $command .= ' --show-mutations';
        }

        // Display configuration to user
        $this->info('Running: ' . $command);
        $this->comment("Threads: {$threads}");
        $this->comment("Min MSI: {$minMsi}%");
        $this->comment("Min Covered MSI: {$minCoveredMsi}%");
        $this->line('');

        // Execute Infection with TTY support for interactive output
        // TTY allows Infection to display progress bars and colors
        $process = Process::fromShellCommandline($command, $root, timeout: null);
        $process->setTty(Process::isTtySupported());

        // Run process and stream output in real-time
        $exitCode = $process->run(function ($type, $buffer): void {
            echo $buffer;
        });

        $this->line('');

        // Report results to user
        if ($exitCode === 0) {
            // Success - MSI thresholds met
            $this->outro('✓ Mutation testing passed');
        } else {
            // Failure - MSI below threshold or tests failed
            $this->error('✗ Mutation testing failed - improve your tests!');
        }

        return $exitCode;
    }
}
