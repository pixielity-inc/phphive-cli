<div align="center">

![PhpHive Banner](.github/assets/banner.svg)

# PhpHive CLI

**🐝 PHP Monorepo Management powered by Turborepo**

[![PHP Version](https://img.shields.io/badge/PHP-8.4%2B-777BB4?style=flat-square&logo=php&logoColor=white)](https://www.php.net/)
[![Packagist Version](https://img.shields.io/packagist/v/phphive/cli?style=flat-square&logo=packagist&logoColor=white)](https://packagist.org/packages/phphive/cli)
[![Downloads](https://img.shields.io/packagist/dt/phphive/cli?style=flat-square&logo=packagist&logoColor=white&label=downloads)](https://packagist.org/packages/phphive/cli)
[![License](https://img.shields.io/badge/License-MIT-green?style=flat-square)](LICENSE)
[![CI Status](https://img.shields.io/github/actions/workflow/status/pixielity-co/phphive-cli/ci.yml?branch=main&style=flat-square&logo=github&label=CI)](https://github.com/pixielity-co/phphive-cli/actions)
[![Tests](https://img.shields.io/badge/tests-58%20passing-success?style=flat-square&logo=phpunit&logoColor=white)](https://github.com/pixielity-co/phphive-cli/actions)

[Features](#-features) •
[Installation](#-installation) •
[Quick Start](#-quick-start) •
[Documentation](#-documentation) •
[Commands](#-commands)

</div>

---

## 🎯 What is PhpHive?

PhpHive is a powerful, production-ready CLI tool for managing PHP monorepos with Turborepo integration. It provides a unified interface for scaffolding applications, managing dependencies, running quality tools, and orchestrating tasks across multiple packages.

### Why PhpHive?

- **🚀 Multi-Framework Support** - Scaffold Laravel, Symfony, Magento, or skeleton apps with one command
- **⚡ Turbo Speed** - Parallel execution and intelligent caching via Turborepo
- **🎯 Quality Built-in** - PHPStan, Pint, Rector, PHPUnit, and Infection pre-configured
- **🐝 Hive Workflow** - All your PHP projects working together like bees in a hive
- **💎 Beautiful CLI** - Interactive prompts powered by Laravel Prompts
- **🔧 Zero Config** - Sensible defaults, works out of the box

---

## ✨ Features

### Core Features
- **Workspace Management** - Initialize and manage monorepo workspaces
- **App Scaffolding** - Create Laravel, Symfony, Magento, or skeleton apps
- **Package Creation** - Generate reusable packages with proper structure
- **Dependency Management** - Unified Composer operations across workspaces
- **Task Orchestration** - Run tasks in parallel with Turborepo

### Quality & Testing
- **PHPUnit** - Comprehensive test suite with 58+ tests
- **PHPStan** - Static analysis at level 8
- **Laravel Pint** - Automatic code formatting
- **Rector** - Automated refactoring for PHP 8.3+
- **Infection** - Mutation testing for test quality

### Developer Experience
- **Auto-discovery** - Commands are automatically discovered
- **Interactive Prompts** - Beautiful CLI powered by Laravel Prompts
- **Command Suggestions** - "Did you mean?" with fuzzy matching
- **Comprehensive Help** - Detailed help text for every command
- **Error Handling** - Clear, actionable error messages

---

## 📦 Installation

### Requirements

- **PHP**: 8.3 or higher
- **Composer**: 2.0 or higher
- **Extensions**: mbstring, json, tokenizer, xml, ctype, iconv

### Global Installation (Recommended)

```bash
composer global require phphive/cli
```

### Local Installation

```bash
composer require --dev phphive/cli
```

### Verify Installation

```bash
hive version
```

---

## ⚡ Quick Start

### 1. Initialize a New Workspace

```bash
hive make:workspace
```

Follow the interactive prompts to create your monorepo structure.

### 2. Create an Application

```bash
# Laravel application
hive make:app my-api --type=laravel

# Symfony application
hive make:app my-service --type=symfony

# Magento store
hive make:app my-shop --type=magento
```

### 3. Create a Package

```bash
hive make:package my-package
```

### 4. Install Dependencies

```bash
hive composer:install
```

### 5. Run Quality Checks

```bash
# Run tests
hive quality:test

# Check code style
hive quality:lint

# Run static analysis
hive quality:typecheck

# Run all checks
cd cli && composer check
```

---

## 📚 Documentation

Comprehensive documentation is available in the [docs](./docs) directory:

- **[Getting Started](./docs/getting-started/README.md)** - Installation, configuration, and first steps
- **[Commands Reference](./docs/commands/README.md)** - Complete command documentation
- **[Features Guide](./docs/features/README.md)** - In-depth feature explanations
- **[Guides & Tutorials](./docs/guides/README.md)** - Step-by-step tutorials
- **[API Reference](./docs/api/README.md)** - Programmatic usage

---

## 🎮 Commands

PhpHive provides 30+ commands organized into 10 categories:

### Workspace Management
- `hive make:workspace` - Initialize a new monorepo workspace
- `hive workspace:list` - List all workspaces
- `hive workspace:info` - Show workspace details

### Application Scaffolding
- `hive make:app` - Create a new application (Laravel/Symfony/Magento/Skeleton)
- `hive make:package` - Create a new package

### Dependency Management
- `hive composer:install` - Install dependencies
- `hive composer:require` - Add a package
- `hive composer:update` - Update dependencies
- `hive composer:run` - Run Composer commands

### Quality & Testing
- `hive quality:test` - Run PHPUnit tests
- `hive quality:lint` - Check code style with Pint
- `hive quality:format` - Fix code style
- `hive quality:typecheck` - Run PHPStan analysis
- `hive quality:refactor` - Apply Rector refactoring
- `hive quality:mutate` - Run Infection mutation testing

### Framework Commands
- `hive framework:artisan` - Run Laravel Artisan
- `hive framework:console` - Run Symfony Console
- `hive framework:magento` - Run Magento CLI

### Development
- `hive dev:start` - Start development server
- `hive dev:build` - Build for production

### Deployment
- `hive deploy:run` - Run deployment pipeline
- `hive deploy:publish` - Publish packages

### Maintenance
- `hive clean:cache` - Clean caches
- `hive clean:all` - Deep clean (destructive)

### Turborepo
- `hive turbo:run` - Run Turbo tasks
- `hive turbo:exec` - Execute Turbo commands

### System
- `hive system:doctor` - Check system health
- `hive system:version` - Show version information

[View complete command reference →](./docs/commands/README.md)

---

## 🏗️ Project Structure

```
monorepo/
├── apps/                    # Applications
│   ├── api/                # Laravel API
│   ├── admin/              # Admin dashboard
│   └── shop/               # Magento store
├── packages/                # Shared packages
│   ├── calculator/         # Example package
│   └── utilities/          # Shared utilities
├── cli/                     # PhpHive CLI tool
│   ├── bin/hive            # CLI entry point
│   ├── src/                # Source code
│   ├── tests/              # Test suite
│   └── docs/               # Documentation
├── composer.json            # Root composer file
├── turbo.json              # Turborepo config
└── pnpm-workspace.yaml     # Workspace config
```

---

## 🤝 Contributing

We welcome contributions! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

### Quick Contribution Guide

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Make your changes
4. Run quality checks (`composer check`)
5. Commit your changes (`git commit -m 'feat: add amazing feature'`)
6. Push to the branch (`git push origin feature/amazing-feature`)
7. Open a Pull Request

---

## 📄 License

PhpHive CLI is open-sourced software licensed under the [MIT license](LICENSE).

---

## 🙏 Credits

Built with:

- [Symfony Console](https://symfony.com/doc/current/components/console.html) - Command-line interface framework
- [Laravel Prompts](https://laravel.com/docs/prompts) - Beautiful interactive prompts
- [Laravel Pint](https://laravel.com/docs/pint) - Code style fixer
- [PHPStan](https://phpstan.org/) - Static analysis tool
- [Rector](https://getrector.com/) - Automated refactoring
- [PHPUnit](https://phpunit.de/) - Testing framework
- [Infection](https://infection.github.io/) - Mutation testing
- [Turborepo](https://turbo.build/) - High-performance build system

---

## 📞 Support

- **Documentation**: [GitHub Docs](./docs)
- **Issues**: [GitHub Issues](https://github.com/pixielity-co/phphive-cli/issues)
- **Packagist**: [phphive/cli](https://packagist.org/packages/phphive/cli)

---

<div align="center">

**[⬆ back to top](#phphive-cli)**

Made with ❤️ by the PhpHive team

</div>
