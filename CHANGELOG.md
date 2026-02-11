# Changelog

All notable changes to PhpHive CLI will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-02-11

### 🎉 Initial Release

PhpHive CLI v1.0.0 is here! A powerful, production-ready CLI tool for managing PHP monorepos with Turborepo integration.

### Added

#### Core Features
- **Multi-Framework Support** - Scaffold Laravel, Symfony, Magento, or skeleton apps
- **Workspace Management** - Initialize and manage monorepo workspaces
- **Package Creation** - Generate reusable packages with proper structure
- **Dependency Management** - Unified Composer operations across workspaces
- **Task Orchestration** - Run tasks in parallel with Turborepo

#### Quality & Testing
- **PHPUnit Integration** - Comprehensive test suite with 58+ tests
- **PHPStan** - Static analysis at level 8
- **Laravel Pint** - Automatic code formatting
- **Rector** - Automated refactoring for PHP 8.3+
- **Infection** - Mutation testing for test quality

#### Commands (30+)
- **Workspace Commands** - `make:workspace`, `workspace:list`, `workspace:info`
- **Make Commands** - `make:app`, `make:package`
- **Composer Commands** - `composer:install`, `composer:require`, `composer:update`, `composer:run`
- **Quality Commands** - `quality:test`, `quality:lint`, `quality:format`, `quality:typecheck`, `quality:refactor`, `quality:mutate`
- **Framework Commands** - `framework:artisan`, `framework:console`, `framework:magento`
- **Development Commands** - `dev:start`, `dev:build`
- **Deployment Commands** - `deploy:run`, `deploy:publish`
- **Turbo Commands** - `turbo:exec`, `turbo:run`
- **System Commands** - `system:doctor`, `system:version`
- **Maintenance Commands** - `clean:cache`, `clean:all`

#### Developer Experience
- **Interactive Prompts** - Beautiful CLI powered by Laravel Prompts
- **Command Suggestions** - "Did you mean?" with fuzzy matching using Levenshtein distance
- **Auto-discovery** - Commands are automatically discovered from Console/Commands directory
- **Comprehensive Help** - Detailed help text for every command with examples
- **Error Handling** - Clear, actionable error messages
- **ASCII Banner** - Beautiful PhpHive banner with honeycomb theme

#### App Types
- **Laravel App Type** - Support for Laravel 10, 11 LTS, 12 Latest with Breeze, Jetstream, Sanctum, Octane, Horizon, Telescope
- **Symfony App Type** - Support for Symfony 6.4 LTS, 7.1 LTS, 7.2 Latest with webapp/skeleton types
- **Magento App Type** - Support for Magento 2.4.6, 2.4.7 with Elasticsearch, Redis, sample data
- **Skeleton App Type** - Minimal PHP application with Composer and optional quality tools

#### Documentation
- **Comprehensive README** - Clear project overview with features, installation, and quick start
- **Getting Started Guide** - Installation, configuration, and first workspace tutorial
- **Commands Reference** - Complete documentation for all 30+ commands with examples
- **Command Cheat Sheet** - Quick reference for common commands
- **GitBook Integration** - Full documentation structure with book.json and SUMMARY.md
- **API Reference** - Documentation for programmatic usage

#### Infrastructure
- **GitHub Repository** - https://github.com/pixielity-co/phphive-cli
- **Packagist Package** - https://packagist.org/packages/phphive/cli
- **Automated Webhook** - GitHub to Packagist integration for automatic updates
- **CI/CD Ready** - GitHub Actions workflow configuration
- **Quality Checks** - All 58 tests passing, PHPStan level 8, Pint compliant

### Technical Details

#### Requirements
- PHP 8.3 or higher
- Composer 2.0 or higher
- Required extensions: mbstring, json, tokenizer, xml, ctype, iconv

#### Dependencies
- symfony/console ^7.2
- symfony/process ^7.2
- symfony/finder ^7.2
- laravel/prompts ^0.3
- illuminate/support ^11.0
- illuminate/container ^11.0

#### Dev Dependencies
- phpstan/phpstan ^2.0
- phpstan/phpstan-strict-rules ^2.0
- laravel/pint ^1.18
- rector/rector ^2.0
- phpunit/phpunit ^11.5
- mockery/mockery ^1.6
- infection/infection ^0.29

### Breaking Changes

This is the initial release, so there are no breaking changes. However, note:

- **Binary name** - The CLI binary is `hive` (not `mono` or `phphive`)
- **Package name** - Install via `composer require phphive/cli`
- **Namespace** - All classes use `PhpHive\Cli` namespace

### Migration from Mono CLI

If you were using the previous Mono CLI:

1. **Update package name**
   ```bash
   composer remove mono-php/cli
   composer require phphive/cli
   ```

2. **Update binary references**
   ```bash
   # Old
   mono test
   
   # New
   hive test
   ```

3. **Update namespaces** (if extending)
   ```php
   // Old
   use MonoPhp\Cli\Console\Commands\BaseCommand;
   
   // New
   use PhpHive\Cli\Console\Commands\BaseCommand;
   ```

4. **Update composer.json scripts**
   ```json
   {
     "scripts": {
       "test": "hive quality:test",
       "lint": "hive quality:lint"
     }
   }
   ```

### Known Issues

None at this time. Please report issues at https://github.com/pixielity-co/phphive-cli/issues

### Credits

Built with:
- [Symfony Console](https://symfony.com/doc/current/components/console.html)
- [Laravel Prompts](https://laravel.com/docs/prompts)
- [Laravel Pint](https://laravel.com/docs/pint)
- [PHPStan](https://phpstan.org/)
- [Rector](https://getrector.com/)
- [PHPUnit](https://phpunit.de/)
- [Infection](https://infection.github.io/)
- [Turborepo](https://turbo.build/)

---

## [Unreleased]

### Planned Features

- [ ] Docker integration for development environments
- [ ] GitHub Actions workflow templates
- [ ] More app types (WordPress, Drupal, CakePHP)
- [ ] Plugin system for custom commands
- [ ] Configuration file support (.phphive.json)
- [ ] Interactive workspace dashboard
- [ ] Dependency graph visualization
- [ ] Performance profiling tools
- [ ] Automated security scanning
- [ ] Package template system

### Roadmap

**v1.1.0** (Q2 2026)
- Docker integration
- GitHub Actions templates
- WordPress app type

**v1.2.0** (Q3 2026)
- Plugin system
- Configuration file support
- Interactive dashboard

**v2.0.0** (Q4 2026)
- Major architecture improvements
- Enhanced Turborepo integration
- Advanced caching strategies

---

[1.0.0]: https://github.com/pixielity-co/phphive-cli/releases/tag/v1.0.0
[Unreleased]: https://github.com/pixielity-co/phphive-cli/compare/v1.0.0...HEAD
