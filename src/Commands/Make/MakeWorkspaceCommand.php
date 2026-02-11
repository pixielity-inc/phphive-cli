<?php

declare(strict_types=1);

namespace MonoPhp\Cli\Commands\Make;

use function exec;
use function file_put_contents;
use function in_array;
use function is_dir;
use function json_encode;

use const JSON_PRETTY_PRINT;
use const JSON_UNESCAPED_SLASHES;

use function mkdir;

use MonoPhp\Cli\Commands\BaseCommand;
use Override;

use function preg_match;
use function str_contains;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function trim;

/**
 * Make Workspace Command.
 *
 * This command initializes a new monorepo workspace structure in the current directory.
 * It creates the necessary configuration files, directory structure, and integrates
 * with Turborepo for optimal monorepo management. This is typically run after installing
 * the CLI tool globally via Composer.
 *
 * The workspace creation process:
 * 1. Prompts for workspace name (or uses provided argument)
 * 2. Prompts for workspace type (monorepo root, package, app)
 * 3. Creates directory structure based on type
 * 4. Generates configuration files (turbo.json, pnpm-workspace.yaml, etc.)
 * 5. Initializes git repository (optional)
 * 6. Provides next steps for getting started
 *
 * Workspace types:
 * - **monorepo**: Full monorepo structure with apps/ and packages/ directories
 * - **package**: Single package workspace (library, shared code)
 * - **app**: Single application workspace (web app, API, CLI)
 *
 * Created structure (monorepo):
 * ```
 * workspace-name/
 * ├── apps/                    # Application workspaces
 * ├── packages/                # Package workspaces
 * ├── turbo.json               # Turborepo configuration
 * ├── pnpm-workspace.yaml      # pnpm workspace configuration
 * ├── package.json             # Root package.json
 * ├── composer.json            # Root composer.json
 * ├── .gitignore               # Git ignore patterns
 * └── README.md                # Project documentation
 * ```
 *
 * Features:
 * - Interactive prompts for workspace configuration
 * - Non-interactive mode support (--no-interaction)
 * - Multiple workspace types (monorepo, package, app)
 * - Automatic Turborepo configuration
 * - pnpm workspace setup
 * - Git initialization (optional)
 * - Comprehensive README generation
 * - Validation of workspace name
 * - Directory existence checks
 * - Beautiful CLI output with Laravel Prompts
 *
 * Common options inherited from BaseCommand:
 * - --no-interaction, -n: Run in non-interactive mode
 *
 * Example usage:
 * ```bash
 * # Interactive mode (prompts for all options)
 * mono make:workspace
 *
 * # With workspace name
 * mono make:workspace my-project
 *
 * # Non-interactive mode (uses defaults)
 * mono make:workspace my-project --no-interaction
 *
 * # Specify workspace type
 * mono make:workspace my-project --type=monorepo
 *
 * # Skip git initialization
 * mono make:workspace my-project --no-git
 * ```
 *
 * After running this command:
 * 1. cd into the new workspace directory
 * 2. Run `pnpm install` to install dependencies
 * 3. Run `mono install` to install workspace dependencies
 * 4. Start developing!
 *
 * @see BaseCommand For inherited functionality and common options
 * @see InteractsWithPrompts For interactive prompts
 * @see CreateAppCommand For creating apps within workspace
 * @see CreatePackageCommand For creating packages within workspace
 */
#[AsCommand(
    name: 'make:workspace',
    description: 'Initialize a new monorepo workspace',
    aliases: ['init', 'new'],
)]
final class MakeWorkspaceCommand extends BaseCommand
{
    /**
     * Configure the command options and arguments.
     *
     * Inherits common options from BaseCommand and defines workspace-specific
     * options for name, type, and git initialization.
     */
    #[Override]
    protected function configure(): void
    {
        parent::configure();

        $this
            ->addArgument(
                'name',
                InputArgument::OPTIONAL,
                'Workspace name (e.g., my-project)',
            )
            ->addOption(
                'type',
                't',
                InputOption::VALUE_REQUIRED,
                'Workspace type: monorepo, package, or app',
                'monorepo',
            )
            ->addOption(
                'no-git',
                null,
                InputOption::VALUE_NONE,
                'Skip git initialization',
            )
            ->setHelp(
                <<<'HELP'
                The <info>make:workspace</info> command initializes a new monorepo workspace.

                <comment>Examples:</comment>
                  <info>mono make:workspace</info>                    Interactive mode
                  <info>mono make:workspace my-project</info>         With name
                  <info>mono make:workspace my-project -n</info>      Non-interactive
                  <info>mono make:workspace --type=package</info>     Specific type

                <comment>Workspace Types:</comment>
                  <info>monorepo</info>  Full monorepo with apps/ and packages/
                  <info>package</info>   Single package workspace
                  <info>app</info>       Single application workspace

                After creation, run:
                  <info>cd workspace-name</info>
                  <info>pnpm install</info>
                  <info>mono install</info>
                HELP,
            );
    }

    /**
     * Execute the make:workspace command.
     *
     * This method orchestrates the entire workspace creation process:
     * 1. Gets workspace name (from argument or prompt)
     * 2. Gets workspace type (from option or prompt)
     * 3. Validates inputs
     * 4. Creates directory structure
     * 5. Generates configuration files
     * 6. Initializes git repository (optional)
     * 7. Displays success message with next steps
     *
     * @param  InputInterface  $input  Command input (arguments and options)
     * @param  OutputInterface $output Command output (for displaying messages)
     * @return int             Exit code (0 for success, 1 for failure)
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Display intro banner
        $this->intro('Create New Workspace');

        // Get workspace name from argument or prompt
        $name = $this->argument('name');

        if (($name === null || $name === '') && $this->hasOption('no-interaction') === false) {
            // Interactive mode - prompt for name
            $name = $this->text(
                label: 'What is the workspace name?',
                placeholder: 'my-project',
                required: true,
                validate: fn (?string $value): ?string => $this->validateWorkspaceName($value),
            );
        } elseif ($name === null || $name === '') {
            // Non-interactive mode without name - error
            $this->error('Workspace name is required in non-interactive mode');

            return Command::FAILURE;
        }

        // Validate workspace name
        $validation = $this->validateWorkspaceName($name);

        if ($validation !== null) {
            $this->error($validation);

            return Command::FAILURE;
        }

        // Get workspace type from option or prompt
        $type = $this->option('type');

        if (! $this->hasOption('no-interaction')) {
            // Interactive mode - prompt for type
            $type = $this->select(
                label: 'What type of workspace?',
                options: [
                    'monorepo' => 'Full monorepo (apps + packages)',
                    'package' => 'Single package',
                    'app' => 'Single application',
                ],
                default: $type,
            );
        }

        // Validate workspace type
        if (! in_array($type, ['monorepo', 'package', 'app'], true)) {
            $this->error("Invalid workspace type: {$type}");

            return Command::FAILURE;
        }

        // Check if directory already exists
        if (is_dir($name)) {
            $this->error("Directory '{$name}' already exists");

            return Command::FAILURE;
        }

        // Create workspace directory
        $this->info("Creating workspace: {$name}");

        if (! mkdir($name, 0755, true)) {
            $this->error("Failed to create directory: {$name}");

            return Command::FAILURE;
        }

        // Create workspace structure based on type
        $this->spin(
            fn () => $this->createWorkspaceStructure($name, $type),
            'Creating workspace structure...',
        );

        // Initialize git repository (unless --no-git)
        if (! $this->hasOption('no-git')) {
            $this->spin(
                fn () => $this->initializeGit($name),
                'Initializing git repository...',
            );
        }

        // Display success message
        $this->outro('✓ Workspace created successfully!');

        // Show next steps
        $this->info('Next steps:');
        $this->note(
            "  cd {$name}\n" .
            "  pnpm install\n" .
            '  mono install',
        );

        return Command::SUCCESS;
    }

    /**
     * Validate workspace name.
     *
     * Ensures the workspace name follows conventions:
     * - Not empty
     * - Contains only lowercase letters, numbers, and hyphens
     * - Starts with a letter
     * - No consecutive hyphens
     *
     * @param  string|null $name The workspace name to validate
     * @return string|null Error message if invalid, null if valid
     */
    private function validateWorkspaceName(?string $name): ?string
    {
        if ($name === null || trim($name) === '') {
            return 'Workspace name cannot be empty';
        }

        if (preg_match('/^[a-z][a-z0-9-]*$/', $name) !== 1) {
            return 'Workspace name must start with a letter and contain only lowercase letters, numbers, and hyphens';
        }

        if (str_contains($name, '--')) {
            return 'Workspace name cannot contain consecutive hyphens';
        }

        return null;
    }

    /**
     * Create workspace structure based on type.
     *
     * @param string $name Workspace name
     * @param string $type Workspace type (monorepo, package, app)
     */
    private function createWorkspaceStructure(string $name, string $type): void
    {
        // Create base directories
        if ($type === 'monorepo') {
            mkdir("{$name}/apps", 0755, true);
            mkdir("{$name}/packages", 0755, true);
        }

        // Create configuration files
        $this->createPackageJson($name, $type);
        $this->createComposerJson($name);
        $this->createTurboJson($name);

        if ($type === 'monorepo') {
            $this->createPnpmWorkspace($name);
        }

        $this->createGitignore($name);
        $this->createReadme($name, $type);
    }

    /**
     * Create package.json file.
     *
     * @param string $name Workspace name
     * @param string $type Workspace type
     */
    private function createPackageJson(string $name, string $type): void
    {
        $packageJson = [
            'name' => $name,
            'version' => '1.0.0',
            'private' => true,
            'scripts' => [
                'dev' => 'mono dev',
                'build' => 'mono build',
                'test' => 'mono test',
                'lint' => 'mono lint',
                'format' => 'mono format',
            ],
        ];

        if ($type === 'monorepo') {
            $packageJson['workspaces'] = ['apps/*', 'packages/*'];
        }

        file_put_contents(
            "{$name}/package.json",
            json_encode($packageJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n",
        );
    }

    /**
     * Create composer.json file.
     *
     * @param string $name Workspace name
     */
    private function createComposerJson(string $name): void
    {
        $composerJson = [
            'name' => $name,
            'type' => 'project',
            'require' => [
                'php' => '^8.3',
            ],
            'require-dev' => [
                'phpunit/phpunit' => '^11.0',
                'phpstan/phpstan' => '^2.0',
                'laravel/pint' => '^1.0',
            ],
            'autoload' => [
                'psr-4' => [],
            ],
            'config' => [
                'sort-packages' => true,
            ],
        ];

        file_put_contents(
            "{$name}/composer.json",
            json_encode($composerJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n",
        );
    }

    /**
     * Create turbo.json file.
     *
     * @param string $name Workspace name
     */
    private function createTurboJson(string $name): void
    {
        $turboJson = [
            '$schema' => 'https://turbo.build/schema.json',
            'tasks' => [
                'build' => [
                    'dependsOn' => ['^build'],
                    'outputs' => ['dist/**', 'build/**'],
                ],
                'test' => [
                    'dependsOn' => ['build'],
                ],
                'lint' => [],
                'dev' => [
                    'cache' => false,
                    'persistent' => true,
                ],
            ],
        ];

        file_put_contents(
            "{$name}/turbo.json",
            json_encode($turboJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n",
        );
    }

    /**
     * Create pnpm-workspace.yaml file.
     *
     * @param string $name Workspace name
     */
    private function createPnpmWorkspace(string $name): void
    {
        $content = <<<'YAML'
        packages:
          - 'apps/*'
          - 'packages/*'
        YAML;

        file_put_contents("{$name}/pnpm-workspace.yaml", $content);
    }

    /**
     * Create .gitignore file.
     *
     * @param string $name Workspace name
     */
    private function createGitignore(string $name): void
    {
        $content = <<<'GITIGNORE'
        # Dependencies
        node_modules/
        vendor/

        # Build outputs
        dist/
        build/
        .turbo/

        # Caches
        .phpstan.cache/
        .phpunit.cache/

        # IDE
        .idea/
        .vscode/
        *.swp

        # OS
        .DS_Store
        Thumbs.db

        # Logs
        *.log
        GITIGNORE;

        file_put_contents("{$name}/.gitignore", $content);
    }

    /**
     * Create README.md file.
     *
     * @param string $name Workspace name
     * @param string $type Workspace type
     */
    private function createReadme(string $name, string $type): void
    {
        $content = <<<README
        # {$name}

        A {$type} workspace powered by Mono CLI and Turborepo.

        ## Getting Started

        Install dependencies:

        ```bash
        pnpm install
        mono install
        ```

        ## Available Commands

        - `mono dev` - Start development server
        - `mono build` - Build for production
        - `mono test` - Run tests
        - `mono lint` - Check code style
        - `mono format` - Fix code style

        ## Project Structure

        ```
        {$name}/
        ├── apps/         # Applications
        ├── packages/     # Shared packages
        └── turbo.json    # Turborepo config
        ```

        ## Learn More

        - [Mono CLI Documentation](https://github.com/mono-php/cli)
        - [Turborepo Documentation](https://turbo.build/repo/docs)
        README;

        file_put_contents("{$name}/README.md", $content);
    }

    /**
     * Initialize git repository.
     *
     * @param string $name Workspace name
     */
    private function initializeGit(string $name): void
    {
        exec("cd {$name} && git init && git add . && git commit -m 'Initial commit'");
    }
}
