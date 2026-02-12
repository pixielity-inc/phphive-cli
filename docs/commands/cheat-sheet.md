# Command Cheat Sheet

Quick reference for the most commonly used PhpHive commands.

## Workspace Management

```bash
# Create new workspace
hive make:workspace

# List all workspaces
hive workspace:list

# Show workspace info
hive workspace:info <name>
```

## Creating Apps & Packages

```bash
# Create Laravel app
hive make:app my-api --type=laravel

# Create Symfony app
hive make:app my-service --type=symfony

# Create Magento store
hive make:app my-shop --type=magento

# Create skeleton app
hive make:app my-app --type=skeleton

# Create package
hive make:package my-package
```

## Dependency Management

```bash
# Install all dependencies
hive composer:install

# Add package
hive composer:require vendor/package

# Add dev package
hive composer:require --dev vendor/package

# Update dependencies
hive composer:update

# Update specific package
hive composer:update vendor/package

# Run Composer command
hive composer show
```

## Quality & Testing

```bash
# Run all tests
hive quality:test

# Run specific test suite
hive quality:test --testsuite=Unit

# Run with coverage
hive quality:test --coverage

# Run tests in parallel
hive quality:test --parallel

# Check code style
hive quality:lint

# Fix code style
hive quality:format

# Preview formatting changes
hive quality:format --dry-run

# Run static analysis
hive quality:typecheck

# Preview refactoring
hive quality:refactor --dry-run

# Apply refactoring
hive quality:refactor

# Run mutation tests
hive quality:mutate

# Run all quality checks (in cli directory)
cd cli && composer check
```

## Framework Commands

```bash
# Laravel Artisan
hive artisan migrate
hive artisan cache:clear
hive artisan make:controller UserController

# Symfony Console
hive console cache:clear
hive console doctrine:migrations:migrate
hive console make:controller UserController

# Magento CLI
hive magento indexer:reindex
hive magento cache:flush
hive magento setup:upgrade
```

## Development

```bash
# Start dev server
hive dev

# Start on custom port
hive dev --port=8080

# Build for production
hive build

# Build specific workspace
hive build --workspace=my-app
```

## Deployment

```bash
# Deploy
hive deploy

# Deploy to specific environment
hive deploy --env=production

# Preview deployment
hive deploy --dry-run

# Publish packages
hive publish

# Preview publish
hive publish --dry-run
```

## Turborepo

```bash
# Run task across all workspaces
hive run build

# Run in specific workspace
hive run test --workspace=my-package

# Run with filter
hive run build --filter=my-*

# Skip cache
hive run build --no-cache

# Direct Turbo command
hive turbo run build --filter=api
```

## System & Maintenance

```bash
# Check system health
hive doctor

# Show version info
hive version

# Clean caches
hive clean

# Deep clean (destructive)
hive cleanup --force
```

## Common Options

All commands support these options:

```bash
--workspace, -w=NAME    # Target specific workspace
--force, -f             # Force operation (skip cache)
--no-cache              # Disable Turbo cache
--no-interaction, -n    # Non-interactive mode
--all                   # Apply to all workspaces
--json                  # Output in JSON format
--parallel              # Enable parallel execution
--dry-run               # Preview without executing
--help, -h              # Show help
--quiet, -q             # Suppress output
--verbose, -v           # Verbose output (-vv, -vvv for more)
```

**[→ See detailed documentation](./common-options.md)**

## Aliases

Many commands have shorter aliases:

```bash
# Workspace
hive init                    # make:workspace
hive ls                      # workspace:list
hive info                    # workspace:info

# Composer
hive i                       # composer:install
hive req                     # composer:require
hive up                      # composer:update

# Quality
hive test                    # quality:test
hive lint                    # quality:lint
hive fmt                     # quality:format
hive tc                      # quality:typecheck

# Framework
hive art                     # framework:artisan
hive sf                      # framework:console
hive mage                    # framework:magento

# System
hive check                   # system:doctor
hive ver                     # system:version
```

## Workflow Examples

### Starting a New Project

```bash
# 1. Create workspace (clones from template)
hive make:workspace

# 2. Navigate to workspace
cd my-monorepo

# 3. Install dependencies
pnpm install
hive composer:install

# 4. Create your first app
hive make:app api --type=laravel

# 5. Run tests
hive quality:test --parallel
```

### Daily Development

```bash
# Start dev server
hive dev --workspace=api

# Run tests on save
hive quality:test --workspace=api

# Check code style
hive quality:lint

# Fix code style
hive quality:format
```

### Before Committing

```bash
# Run all quality checks
cd cli && composer check

# Or individually
hive quality:test --parallel
hive quality:lint
hive quality:typecheck
hive quality:refactor --dry-run
```

### Deployment

```bash
# Preview deployment
hive deploy --dry-run

# Build all apps
hive build --all

# Run tests in parallel
hive quality:test --parallel

# Deploy to production
hive deploy --env=production
```

## Tips & Tricks

### Use Tab Completion

PhpHive supports shell completion. Generate it with:

```bash
hive completion bash > /etc/bash_completion.d/hive
```

### Target Specific Workspaces

Most commands accept `--workspace` or `-w`:

```bash
hive quality:test -w api
hive composer:require symfony/console -w my-package
```

### Run Commands in Parallel

Use Turborepo for parallel execution:

```bash
# Runs tests in all workspaces in parallel
hive run test

# Run with parallel flag
hive quality:test --parallel
```

### Check Command Help

Every command has detailed help:

```bash
hive make:app --help
hive quality:test -h
```

### Use Aliases for Speed

```bash
hive t              # quality:test
hive fmt            # quality:format
hive tc             # quality:typecheck
hive i              # composer:install
hive req            # composer:require
```

### Preview Before Executing

```bash
hive deploy --dry-run           # Preview deployment
hive quality:refactor --dry-run # Preview refactoring
hive clean:all --dry-run        # Preview cleanup
```

### Use JSON Output for Automation

```bash
hive workspace:list --json | jq '.workspaces[].name'
```

---

**Need more details?** Check the [full command reference](./README.md).
