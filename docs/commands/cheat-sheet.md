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

# Check code style
hive quality:lint

# Fix code style
hive quality:format

# Run static analysis
hive quality:typecheck

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

# Publish packages
hive publish

# Dry run
hive deploy --dry-run
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
--force, -f             # Force operation
--no-cache              # Disable cache
--no-interaction, -n    # Non-interactive mode
--help, -h              # Show help
--quiet, -q             # Suppress output
--verbose, -v           # Verbose output
```

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
# 1. Create workspace
hive make:workspace

# 2. Navigate to workspace
cd my-monorepo

# 3. Install dependencies
pnpm install
hive composer:install

# 4. Create your first app
hive make:app api --type=laravel

# 5. Run tests
hive quality:test
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
hive quality:test
hive quality:lint
hive quality:typecheck
hive quality:refactor --dry-run
```

### Deployment

```bash
# Build all apps
hive build

# Run tests
hive quality:test

# Deploy
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
hive run test  # Runs tests in all workspaces in parallel
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
```

---

**Need more details?** Check the [full command reference](./README.md).
