# What is PhpHive?

PhpHive is a comprehensive CLI tool designed to simplify PHP monorepo management. It combines the power of Turborepo with PHP-specific tooling to create a seamless development experience.

## The Problem

Managing PHP monorepos is challenging:

- **Multiple frameworks** - Laravel, Symfony, Magento each have different setup processes
- **Dependency hell** - Managing Composer dependencies across packages is complex
- **Quality tools** - Setting up PHPStan, Pint, Rector, PHPUnit consistently is time-consuming
- **Task orchestration** - Running tasks across multiple packages efficiently is difficult
- **Inconsistent workflows** - Each project has different commands and conventions

## The Solution

PhpHive provides a unified interface that:

### 🚀 Scaffolds Applications Instantly

Create production-ready Laravel, Symfony, or Magento applications with a single command. All dependencies, configurations, and quality tools are set up automatically.

```bash
hive make:app my-api --type=laravel
```

### ⚡ Orchestrates Tasks with Turborepo

Run tests, linting, and builds across all packages in parallel with intelligent caching. Only rebuild what changed.

```bash
hive quality:test  # Runs tests in parallel across all packages
```

### 🎯 Enforces Quality Standards

PHPStan, Pint, Rector, PHPUnit, and Infection are pre-configured with sensible defaults. Run all quality checks with one command.

```bash
cd cli && composer check  # Runs all quality tools
```

### 🐝 Creates a Unified Workflow

All your PHP projects work together like bees in a hive. Share code, dependencies, and configurations seamlessly.

```bash
hive workspace:list  # See all your projects at a glance
```

## Key Features

### Multi-Framework Support

PhpHive understands the nuances of different PHP frameworks:

- **Laravel** - Breeze, Jetstream, Sanctum, Octane, Horizon, Telescope
- **Symfony** - Maker, Security, Doctrine, different project types
- **Magento** - Sample data, Elasticsearch, Redis, admin configuration
- **Skeleton** - Minimal setup for custom applications

### Intelligent Dependency Management

- Unified Composer operations across all packages
- Automatic dependency resolution
- Workspace-aware package installation
- Version constraint management

### Built-in Quality Tools

- **PHPUnit** - Testing framework with parallel execution
- **PHPStan** - Static analysis at level 8
- **Laravel Pint** - Code style fixer
- **Rector** - Automated refactoring
- **Infection** - Mutation testing

### Developer Experience

- **Interactive prompts** - Beautiful CLI powered by Laravel Prompts
- **Command suggestions** - "Did you mean?" with fuzzy matching
- **Auto-discovery** - Commands are automatically discovered
- **Comprehensive help** - Detailed documentation for every command
- **Error handling** - Clear, actionable error messages

## How It Works

PhpHive is built on three core principles:

### 1. Convention over Configuration

Sensible defaults mean you can start immediately without configuration. Everything works out of the box.

### 2. Workspace-Centric

All operations are workspace-aware. Run commands across all packages or target specific ones.

### 3. Turborepo Integration

Leverage Turborepo's parallel execution and intelligent caching for maximum performance.

## Architecture

```
PhpHive CLI
├── Command Layer (Symfony Console)
├── App Type System (Laravel, Symfony, Magento, Skeleton)
├── Quality Tools Integration (PHPStan, Pint, Rector, PHPUnit, Infection)
├── Turborepo Orchestration
└── Workspace Management
```

## Who Should Use PhpHive?

PhpHive is perfect for:

- **Teams** managing multiple PHP applications
- **Agencies** building client projects with shared code
- **Open source maintainers** managing multiple packages
- **Developers** who want a unified workflow across frameworks
- **Companies** standardizing their PHP development process

## What's Next?

Ready to get started? Check out:

- [Installation Guide](../getting-started/installation.md)
- [Your First Workspace](../getting-started/first-workspace.md)
- [Command Reference](../commands/README.md)
