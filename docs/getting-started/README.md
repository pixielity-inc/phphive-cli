# Getting Started with PhpHive CLI

Welcome to PhpHive! This guide will help you get up and running in minutes.

## 📋 Table of Contents

1. [Installation](#installation)
2. [System Requirements](#system-requirements)
3. [Your First Workspace](#your-first-workspace)
4. [Core Concepts](#core-concepts)
5. [Next Steps](#next-steps)

## Installation

### Global Installation (Recommended)

Install PhpHive globally to use it across all your projects:

```bash
composer global require phphive/cli
```

Verify the installation:

```bash
hive version
```

### Local Installation

Install PhpHive as a dev dependency in your project:

```bash
composer require --dev phphive/cli
```

Use it via Composer scripts or directly:

```bash
./vendor/bin/hive version
```

## System Requirements

PhpHive requires:

- **PHP 8.4+** - Modern PHP features and performance
- **Composer 2.0+** - Dependency management
- **Symfony 7.0 or 8.0** - Automatically resolved
- **Node.js 18+** (optional) - For Turborepo features
- **pnpm** (optional) - Workspace package manager

### Required PHP Extensions

- `mbstring` - Multi-byte string handling
- `json` - JSON encoding/decoding
- `tokenizer` - PHP tokenization
- `xml` - XML processing
- `ctype` - Character type checking
- `iconv` - Character encoding conversion

### Check Your System

Run the system health check:

```bash
hive system:doctor
```

This command verifies:
- PHP version and extensions
- Composer installation
- Turborepo availability
- Node.js and pnpm (if installed)
- File permissions

## Your First Workspace

Let's create your first monorepo workspace!

### Step 1: Initialize Workspace

PhpHive clones from an official template repository with pre-configured structure:

```bash
hive make:workspace
```

You'll be prompted for:
- **Workspace name** - e.g., `my-monorepo`

The command will:
- Clone from https://github.com/pixielity-co/hive-template
- Set up complete monorepo structure
- Include sample app and package
- Initialize fresh Git repository
- Update package names automatically

### Step 2: Navigate to Workspace

```bash
cd my-monorepo
```

### Step 3: Install Dependencies

```bash
pnpm install
hive composer:install
```

### Step 4: Create Your First App

```bash
hive make:app my-api --type=laravel
```

### Step 5: Run Tests

```bash
# Run all tests
hive quality:test

# Run tests in parallel
hive quality:test --parallel

# Run with coverage
hive quality:test --coverage
```

🎉 **Congratulations!** You've created your first PhpHive workspace.

## Core Concepts

### Workspaces

A **workspace** is a collection of related packages and applications managed together. PhpHive supports:

- **Monorepo** - Multiple apps and packages in one repository
- **Package** - A single reusable package
- **App** - A standalone application

### Applications

PhpHive can scaffold four types of applications:

1. **Laravel** - Full-stack PHP framework
2. **Symfony** - High-performance framework
3. **Magento** - E-commerce platform
4. **Skeleton** - Minimal PHP application

### Packages

Packages are reusable libraries shared across your workspace. They can contain:
- Business logic
- Utilities
- Shared components
- API clients

### Turborepo Integration

PhpHive leverages Turborepo for:
- **Parallel execution** - Run tasks across workspaces simultaneously
- **Intelligent caching** - Skip unchanged tasks
- **Dependency awareness** - Execute in correct order

## Next Steps

Now that you're set up, explore these topics:

1. **[Create Applications](../commands/make.md#makeapp)** - Scaffold Laravel, Symfony, or Magento apps
2. **[Manage Dependencies](../commands/composer.md)** - Add and update packages
3. **[Run Quality Checks](../commands/quality.md)** - Test, lint, and analyze your code
4. **[Deploy Your Apps](../commands/deployment.md)** - Publish and deploy

### Recommended Reading

- [Gradient Banner Feature](../features/gradient-banner.md)
- [Common Command Options](../commands/common-options.md)
- [Configuration Guide](./configuration.md)
- [Command Reference](../commands/README.md)
- [Best Practices](../guides/best-practices.md)
- [Troubleshooting](./troubleshooting.md)

### Example Workflows

- [Building a Laravel API](../guides/laravel-api.md)
- [Creating Shared Packages](../guides/shared-packages.md)
- [Setting Up CI/CD](../guides/cicd.md)

---

**Questions?** Check the [FAQ](./faq.md) or [open an issue](https://github.com/pixielity-co/phphive-cli/issues).
