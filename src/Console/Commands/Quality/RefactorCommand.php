<?php

declare(strict_types=1);

namespace PhpHive\Cli\Console\Commands\Quality;

use Override;
use PhpHive\Cli\Console\Commands\BaseCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

/**
 * Refactor Command.
 *
 * This command runs Rector for automated code refactoring and modernization
 * across the monorepo. It applies PHP best practices, upgrades code to newer
 * standards, and performs safe automated transformations based on configured
 * rules. Rector helps maintain code quality and consistency at scale.
 *
 * The refactoring process:
 * 1. Loads Rector configuration (rector.php)
 * 2. Analyzes code against configured rules
 * 3. Identifies refactoring opportunities
 * 4. Applies transformations (or shows preview in dry-run)
 * 5. Reports changes made to files
 *
 * Common refactoring types:
 * - PHP version upgrades (7.4 → 8.x syntax)
 * - Type declarations (add missing types)
 * - Dead code removal
 * - Code simplification
 * - Framework-specific upgrades
 * - Best practice enforcement
 *
 * Features:
 * - Dry-run mode (preview changes without applying)
 * - Cache clearing option
 * - Safe automated transformations
 * - Detailed change reporting
 * - Integration with centralized config
 * - Monorepo-wide refactoring
 *
 * Example usage:
 * ```bash
 * # Apply refactoring changes
 * hive refactor
 *
 * # Preview changes without applying (safe)
 * hive refactor --dry-run
 *
 * # Clear cache and refactor
 * hive refactor --clear-cache
 *
 * # Preview with fresh cache
 * hive rector --clear-cache --dry-run
 * ```
 *
 * @see BaseCommand For inherited functionality
 * @see TypecheckCommand For static analysis after refactoring
 * @see TestCommand For verifying refactoring didn't break tests
 */
#[AsCommand(
    name: 'quality:refactor',
    description: 'Run Rector for automated refactoring',
    aliases: ['refactor', 'rector'],
)]
final class RefactorCommand extends BaseCommand
{
    /**
     * Configure the command options.
     *
     * Defines command-specific options for refactoring. Common options
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
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Show changes without applying them',
            )
            ->addOption(
                'clear-cache',
                null,
                InputOption::VALUE_NONE,
                'Clear Rector cache before running',
            )
            ->setHelp(
                <<<'HELP'
                The <info>refactor</info> command runs Rector for automated refactoring.

                <comment>Examples:</comment>
                  <info>hive refactor</info>              Apply refactoring changes
                  <info>hive refactor --dry-run</info>    Preview changes without applying
                  <info>hive refactor --clear-cache</info> Clear cache and refactor

                Rector applies PHP best practices and modernizes code automatically.
                HELP
            );
    }

    /**
     * Execute the refactor command.
     *
     * This method orchestrates the entire refactoring process:
     * 1. Displays an intro message
     * 2. Clears cache if requested
     * 3. Builds the Rector command with options
     * 4. Executes Rector with TTY support
     * 5. Reports refactoring results
     *
     * @param  InputInterface  $input  Command input (arguments and options)
     * @param  OutputInterface $output Command output (for displaying messages)
     * @return int             Exit code (0 for success, 1 for failure)
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Extract options from user input
        $dryRun = $this->hasOption('dry-run');
        $clearCache = $this->hasOption('clear-cache');

        // Display intro banner with appropriate message
        $this->intro($dryRun ? 'Previewing refactoring changes...' : 'Running Rector refactoring...');

        // Get monorepo root directory
        $root = $this->getMonorepoRoot();

        // Clear Rector cache if requested
        // This forces fresh analysis, useful when rules change
        if ($clearCache) {
            $this->info('Clearing Rector cache...');
            $cacheDir = $root . '/.rector.cache';

            // Remove cache directory if it exists
            if ($this->filesystem()->isDirectory($cacheDir)) {
                $process = Process::fromShellCommandline("rm -rf {$cacheDir}");
                $process->run();
            }

            $this->line('');
        }

        // Build Rector command
        // Base command processes all configured paths
        $command = 'vendor/bin/rector process';

        // Add dry-run flag to preview changes without applying
        // This is safe and recommended before actual refactoring
        if ($dryRun) {
            $command .= ' --dry-run';
        }

        // Display command to user
        $this->info('Running: ' . $command);
        $this->line('');

        // Execute Rector with TTY support for interactive output
        // TTY allows Rector to display progress bars and colors
        $process = Process::fromShellCommandline($command, $root, timeout: null);
        $process->setTty(Process::isTtySupported());

        // Run process and stream output in real-time
        $exitCode = $process->run(function ($type, $buffer): void {
            echo $buffer;
        });

        $this->line('');

        // Report results to user
        if ($exitCode === 0) {
            if ($dryRun) {
                // Success - preview completed
                $this->outro('✓ Refactoring preview complete');
            } else {
                // Success - refactoring applied
                $this->outro('✓ Refactoring complete');
            }
        } else {
            // Failure - Rector encountered errors
            $this->error('✗ Refactoring failed');
        }

        return $exitCode;
    }
}
