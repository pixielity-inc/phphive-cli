# Commands Reference

Complete reference for all PhpHive CLI commands.

## 📋 Command Categories

PhpHive provides 30+ commands organized into 10 categories:

- [Workspace Management](#workspace-management) - Manage monorepo workspaces
- [Make Commands](#make-commands) - Scaffold apps and packages
- [Composer Commands](#composer-commands) - Dependency management
- [Quality Commands](#quality-commands) - Testing and code quality
- [Framework Commands](#framework-commands) - Laravel, Symfony, Magento
- [Development Commands](#development-commands) - Dev server and builds
- [Deployment Commands](#deployment-commands) - Deploy and publish
- [Turbo Commands](#turbo-commands) - Turborepo integration
- [System Commands](#system-commands) - Health checks and info
- [Maintenance Commands](#maintenance-commands) - Clean and maintain

## Common Options

All commands support these common options:

- `--workspace, -w=NAME` - Target specific workspace
- `--force, -f` - Force operation (skip cache)
- `--no-cache` - Disable Turbo cache
- `--no-interaction, -n` - Run in non-interactive mode
- `--all` - Apply operation to all workspaces
- `--json` - Output data in JSON format
- `--parallel` - Enable parallel execution across workspaces
- `--dry-run` - Preview what would happen without executing
- `--help, -h` - Display help
- `--quiet, -q` - Suppress output
- `--verbose, -v` - Verbose output

**[→ See detailed common options documentation](./common-options.md)**

---

## Workspace Management

### `hive make:workspace`

Initialize a new monorepo workspace with interactive setup.

**Aliases:** `init`, `new`

**Usage:**
```bash
# Interactive mode
hive make:workspace

# Non-interactive mode
hive make:workspace --name=my-monorepo --type=monorepo --no-interaction

# Skip Git initialization
hive make:workspace --no-git
```

**Options:**
- `--name=NAME` - Workspace name
- `--type=TYPE` - Workspace type (monorepo, package, app)
- `--no-git` - Skip Git initialization
- `--no-interaction, -n` - Run without prompts

**What it creates:**
- Root `composer.json` with workspace configuration
- `turbo.json` for Turborepo configuration
- `pnpm-workspace.yaml` for workspace management
- `package.json` with scripts
- `.gitignore` with sensible defaults
- `README.md` with getting started guide
- `apps/` and `packages/` directories

---

### `hive workspace:list`

List all workspaces in the monorepo.

**Aliases:** `list`, `ls`, `workspaces`

**Usage:**
```bash
# List all workspaces
hive workspace:list

# Filter by type
hive workspace:list --type=package
```

**Options:**
- `--type=TYPE` - Filter by workspace type (app, package)

**Output:**
```
Workspaces:
  apps/api          Laravel API application
  apps/admin        Admin dashboard
  packages/utils    Shared utilities
```

---

### `hive workspace:info`

Show detailed information about a workspace.

**Aliases:** `info`, `show`, `details`

**Usage:**
```bash
# Show info for specific workspace
hive info api

# Show with dependencies
hive info api --dependencies
```

**Arguments:**
- `workspace` - Workspace name

**Options:**
- `--dependencies` - Show dependency tree

---

## Make Commands

### `hive make:app`

Create a new application with full scaffolding.

**Aliases:** `create:app`, `new:app`

**Usage:**
```bash
# Interactive mode - prompts for app type
hive make:app my-app

# Specify app type directly
hive make:app my-app --type=laravel
hive make:app shop --type=magento
hive make:app api --type=symfony
hive make:app service --type=skeleton
```

**Arguments:**
- `name` - Application name (required)

**Options:**
- `--type, -t=TYPE` - Application type (laravel, symfony, magento, skeleton)

**Supported App Types:**

#### Laravel
- Multiple versions (10, 11 LTS, 12 Latest)
- Starter kits (Breeze, Jetstream)
- Database configuration
- Optional packages (Horizon, Telescope, Sanctum, Octane)

#### Symfony
- Multiple versions (6.4 LTS, 7.1 LTS, 7.2 Latest)
- Project types (webapp, skeleton)
- Database configuration
- Optional bundles (Maker, Security)

#### Magento
- Versions (2.4.6, 2.4.7)
- Database and admin configuration
- Sample data installation
- Elasticsearch and Redis setup

#### Skeleton
- Minimal PHP application
- Composer configuration
- PHPUnit setup
- Optional quality tools

**Example:**
```bash
# Create Laravel API with Sanctum
hive make:app api --type=laravel
# Follow prompts to select version, starter kit, and packages

# Create Symfony microservice
hive make:app service --type=symfony
# Select skeleton type for minimal setup

# Create Magento store
hive make:app shop --type=magento
# Configure database, admin, and optional features
```

---

### `hive make:package`

Create a new reusable package.

**Aliases:** `create:package`, `new:package`

**Usage:**
```bash
# Interactive mode
hive make:package my-package

# With description
hive make:package utilities --description="Shared utility functions"
```

**Arguments:**
- `name` - Package name (required)

**Options:**
- `--description=TEXT` - Package description

**What it creates:**
- `composer.json` with PSR-4 autoloading
- `src/` directory with example class
- `tests/` directory with PHPUnit setup
- `phpunit.xml` configuration
- `README.md` with usage instructions
- `.gitignore` for package-specific files

---

## Composer Commands

### `hive composer:install`

Install all dependencies across workspaces.

**Aliases:** `install`, `i`

**Usage:**
```bash
# Install all dependencies
hive composer:install

# Install without dev dependencies
hive composer:install --no-dev

# Update lock file
hive composer:install --update-lock
```

**Options:**
- `--no-dev` - Skip dev dependencies
- `--update-lock` - Update lock file
- `--optimize-autoloader, -o` - Optimize autoloader

---

### `hive composer:require`

Add a package dependency.

**Aliases:** `require`, `req`, `add`

**Usage:**
```bash
# Add production dependency
hive require vendor/package

# Add dev dependency
hive require --dev phpunit/phpunit

# Add to specific workspace
hive require vendor/package --workspace=my-package

# Add with version constraint
hive require "vendor/package:^2.0"
```

**Arguments:**
- `package` - Package name (required)

**Options:**
- `--dev, -D` - Add as dev dependency
- `--update-with-dependencies` - Update dependencies
- `--workspace, -w=NAME` - Target workspace

---

### `hive composer:update`

Update dependencies.

**Aliases:** `update`, `up`, `upgrade`

**Usage:**
```bash
# Update all dependencies
hive update

# Update specific package
hive update vendor/package

# Update in specific workspace
hive update --workspace=my-package

# Update with dependencies
hive update --with-dependencies
```

**Arguments:**
- `package` - Package name (optional)

**Options:**
- `--with-dependencies` - Update with dependencies
- `--prefer-stable` - Prefer stable versions
- `--workspace, -w=NAME` - Target workspace

---

### `hive composer:run`

Run arbitrary Composer commands.

**Aliases:** `composer`, `comp`

**Usage:**
```bash
# Run any Composer command
hive composer show

# With arguments
hive composer require vendor/package --dev

# In specific workspace
hive composer show --workspace=my-package
```

**Arguments:**
- `command` - Composer command to run
- `args` - Additional arguments (optional)

---

## Quality Commands

### `hive quality:test`

Run PHPUnit tests.

**Aliases:** `test`, `t`, `phpunit`

**Usage:**
```bash
# Run all tests
hive test

# Run specific test suite
hive test --testsuite=Unit

# Run with coverage
hive test --coverage

# Run specific workspace tests
hive test --workspace=my-package

# Run specific test file
hive test --filter=MyTest

# Parallel execution
hive test --parallel
```

**Options:**
- `--testsuite=SUITE` - Test suite to run (Unit, Feature)
- `--coverage` - Generate coverage report
- `--filter=PATTERN` - Filter tests by pattern
- `--parallel` - Run tests in parallel
- `--workspace, -w=NAME` - Target workspace

---

### `hive quality:lint`

Check code style with Laravel Pint.

**Aliases:** `lint`, `pint`

**Usage:**
```bash
# Check code style
hive lint

# Check specific workspace
hive lint --workspace=my-package

# Check specific path
hive lint src/Commands

# Check only uncommitted files
hive lint --dirty
```

**Options:**
- `--dirty` - Only check uncommitted files
- `--workspace, -w=NAME` - Target workspace

---

### `hive quality:format`

Fix code style automatically.

**Aliases:** `format`, `fmt`, `fix`

**Usage:**
```bash
# Fix all files
hive format

# Fix specific workspace
hive format --workspace=my-package

# Fix specific path
hive format src/Commands

# Dry run (preview changes)
hive format --dry-run

# Fix only uncommitted files
hive format --dirty
```

**Options:**
- `--dry-run` - Preview changes without applying
- `--dirty` - Only fix uncommitted files
- `--workspace, -w=NAME` - Target workspace

---

### `hive quality:typecheck`

Run PHPStan static analysis.

**Aliases:** `typecheck`, `tc`, `phpstan`, `analyse`, `analyze`

**Usage:**
```bash
# Run static analysis
hive typecheck

# Specific workspace
hive typecheck --workspace=my-package

# Generate baseline
hive typecheck --generate-baseline

# Custom level
hive typecheck --level=8
```

**Options:**
- `--level=LEVEL` - Analysis level (0-9)
- `--generate-baseline` - Generate baseline file
- `--workspace, -w=NAME` - Target workspace

---

### `hive quality:refactor`

Run Rector refactoring.

**Aliases:** `refactor`, `rector`

**Usage:**
```bash
# Preview refactoring
hive refactor

# Apply refactoring
hive refactor --fix

# Specific workspace
hive refactor --workspace=my-package

# Clear cache
hive refactor --clear-cache
```

**Options:**
- `--fix` - Apply refactoring changes
- `--dry-run` - Preview changes only (default)
- `--clear-cache` - Clear Rector cache
- `--workspace, -w=NAME` - Target workspace

---

### `hive quality:mutate`

Run Infection mutation testing.

**Aliases:** `mutate`, `mutation`, `infection`

**Usage:**
```bash
# Run mutation tests
hive mutate

# Specific workspace
hive mutate --workspace=my-package

# Set minimum MSI score
hive mutate --min-msi=80

# Use multiple threads
hive mutate --threads=8

# Show all mutations
hive mutate --show-mutations
```

**Options:**
- `--min-msi=SCORE` - Minimum MSI score
- `--min-covered-msi=SCORE` - Minimum covered MSI score
- `--threads=N` - Number of threads
- `--show-mutations` - Show all mutations
- `--workspace, -w=NAME` - Target workspace

---

## Framework Commands

### `hive framework:artisan`

Run Laravel Artisan command in a workspace.

**Aliases:** `artisan`, `art`

**Usage:**
```bash
# Run Artisan command
hive artisan migrate

# With options
hive artisan cache:clear --workspace=api

# Multiple arguments
hive artisan make:controller UserController

# List routes
hive artisan route:list

# Run queue worker
hive artisan queue:work --tries=3

# Run tests
hive artisan test --parallel
```

**Arguments:**
- `command` - Artisan command to run
- `args` - Additional arguments (optional)

**Options:**
- `--workspace, -w=NAME` - Target workspace

---

### `hive framework:console`

Run Symfony Console command in a workspace.

**Aliases:** `console`, `sf`, `symfony`

**Usage:**
```bash
# Run Console command
hive console cache:clear

# With workspace
hive console doctrine:migrations:migrate --workspace=api

# Make controller
hive console make:controller UserController

# Debug routes
hive console debug:router

# Run messenger
hive console messenger:consume async
```

**Arguments:**
- `command` - Console command to run
- `args` - Additional arguments (optional)

**Options:**
- `--workspace, -w=NAME` - Target workspace

---

### `hive framework:magento`

Run Magento CLI command in a workspace.

**Aliases:** `magento`, `mage`, `bin/magento`

**Usage:**
```bash
# Reindex
hive magento indexer:reindex

# Flush cache
hive magento cache:flush --workspace=shop

# Setup upgrade
hive magento setup:upgrade --keep-generated

# Set deploy mode
hive magento deploy:mode:set production

# Enable module
hive magento module:enable Vendor_Module

# Deploy static content
hive magento setup:static-content:deploy en_US -f
```

**Arguments:**
- `command` - Magento command to run
- `args` - Additional arguments (optional)

**Options:**
- `--workspace, -w=NAME` - Target workspace

---

## Development Commands

### `hive dev:start`

Start development server with hot reload.

**Aliases:** `dev`, `serve`

**Usage:**
```bash
# Start dev server
hive dev

# Specify workspace
hive dev --workspace=my-app

# Custom port
hive dev --port=8080

# Custom host
hive dev --host=0.0.0.0
```

**Options:**
- `--port=PORT` - Server port (default: 8000)
- `--host=HOST` - Server host (default: localhost)
- `--workspace, -w=NAME` - Target workspace

---

### `hive dev:build`

Build for production.

**Aliases:** `build`

**Usage:**
```bash
# Build all workspaces
hive build

# Build specific workspace
hive build --workspace=my-app

# Production build
hive build --production

# Minify output
hive build --minify
```

**Options:**
- `--production` - Production build mode
- `--minify` - Minify output
- `--workspace, -w=NAME` - Target workspace

---

## Deployment Commands

### `hive deploy:run`

Run deployment pipeline.

**Aliases:** `deploy`

**Usage:**
```bash
# Deploy all workspaces
hive deploy

# Deploy specific workspace
hive deploy --workspace=my-app

# Deploy to specific environment
hive deploy --env=production

# Dry run
hive deploy --dry-run
```

**Options:**
- `--env=ENV` - Target environment
- `--dry-run` - Preview deployment steps
- `--workspace, -w=NAME` - Target workspace

---

### `hive deploy:publish`

Publish packages to registry.

**Aliases:** `publish`

**Usage:**
```bash
# Publish all packages
hive publish

# Publish specific package
hive publish --workspace=my-package

# Publish with tag
hive publish --tag=latest

# Dry run
hive publish --dry-run
```

**Options:**
- `--tag=TAG` - Version tag
- `--dry-run` - Preview publish
- `--workspace, -w=NAME` - Target workspace

---

## Turbo Commands

### `hive turbo:exec`

Direct access to Turborepo commands.

**Aliases:** `turbo`, `tb`

**Usage:**
```bash
# Run turbo command
hive turbo run build

# With filters
hive turbo run test --filter=my-package

# With cache options
hive turbo run build --no-cache

# Prune workspace
hive turbo prune --scope=api
```

**Arguments:**
- `command` - Turbo command to run
- `args` - Additional arguments (optional)

---

### `hive turbo:run`

Run arbitrary Turbo tasks.

**Aliases:** `run`, `exec`, `execute`

**Usage:**
```bash
# Run task across all workspaces
hive run build

# Run in specific workspace
hive run test --workspace=my-package

# Run with filters
hive run build --filter=my-*

# Parallel execution
hive run test --parallel

# Skip cache
hive run build --no-cache
```

**Arguments:**
- `task` - Task name to run

**Options:**
- `--filter=PATTERN` - Filter workspaces
- `--parallel` - Run in parallel
- `--no-cache` - Skip cache
- `--workspace, -w=NAME` - Target workspace

---

## System Commands

### `hive system:doctor`

System health check and diagnostics.

**Aliases:** `doctor`, `check`, `health`

**Usage:**
```bash
# Run health check
hive doctor

# Verbose output
hive doctor --verbose

# Check specific component
hive doctor --check=php
```

**Options:**
- `--check=COMPONENT` - Check specific component
- `--verbose, -v` - Verbose output

**Checks:**
- PHP version and extensions
- Composer installation
- Turborepo availability
- Workspace configuration
- File permissions

---

### `hive system:version`

Show version information.

**Aliases:** `version`, `ver`, `v`, `--version`, `-V`

**Usage:**
```bash
# Show version
hive version

# Show all versions
hive version --all
```

**Options:**
- `--all` - Show all component versions

**Output:**
```
PhpHive CLI:
  Version: 1.0.0

PHP:
  Version: 8.3.0
  Binary: /usr/bin/php

Composer:
  Version: 2.6.5

Turbo:
  Version: 1.10.16
```

---

## Maintenance Commands

### `hive clean:cache`

Clean caches and temporary files.

**Aliases:** `clean`, `clear`

**Usage:**
```bash
# Clean all caches
hive clean

# Clean specific cache
hive clean --cache=phpstan

# Clean specific workspace
hive clean --workspace=my-package
```

**Options:**
- `--cache=TYPE` - Specific cache to clean (phpstan, phpunit, turbo, pint)
- `--workspace, -w=NAME` - Target workspace

**What it cleans:**
- `.phpstan.cache` - PHPStan cache
- `.phpunit.cache` - PHPUnit cache
- `.turbo` - Turborepo cache
- `.pint.cache` - Pint cache
- `var/cache` - Application cache

---

### `hive clean:all`

Deep clean (destructive operation).

**Aliases:** `cleanup`

**Usage:**
```bash
# Deep clean with confirmation
hive cleanup

# Force cleanup without confirmation
hive cleanup --force

# Clean specific workspace
hive cleanup --workspace=my-package
```

**Options:**
- `--force, -f` - Skip confirmation
- `--workspace, -w=NAME` - Target workspace

**Warning:** This removes:
- `vendor/` directories
- `composer.lock` files
- `node_modules/` directories
- All cache directories
- Build artifacts

---

## Command Cheat Sheet

Quick reference for common commands:

```bash
# Workspace
hive make:workspace              # Create workspace
hive workspace:list              # List workspaces
hive workspace:info api          # Show workspace info

# Create
hive make:app my-app             # Create app
hive make:package my-pkg         # Create package

# Dependencies
hive composer:install            # Install deps
hive composer:require pkg        # Add package
hive composer:update             # Update deps

# Quality
hive quality:test                # Run tests
hive quality:lint                # Check style
hive quality:format              # Fix style
hive quality:typecheck           # Static analysis
hive quality:refactor            # Refactor code
hive quality:mutate              # Mutation tests

# Framework
hive artisan migrate             # Laravel
hive console cache:clear         # Symfony
hive magento indexer:reindex     # Magento

# Development
hive dev                         # Start server
hive build                       # Build for prod

# Deployment
hive deploy                      # Deploy
hive publish                     # Publish packages

# Turbo
hive run build                   # Run task
hive turbo run test              # Turbo command

# System
hive doctor                      # Health check
hive version                     # Show version

# Maintenance
hive clean                       # Clean caches
hive cleanup                     # Deep clean
```

---

**Need more help?** Check the [Getting Started Guide](../getting-started/README.md) or [open an issue](https://github.com/pixielity-co/phphive-cli/issues).
