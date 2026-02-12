# Changelog

All notable changes to PhpHive CLI will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.11] - 2026-02-12

### Added

- **Gradient Banner**: Added beautiful gradient banner with honey-themed color schemes
  - Randomly selects from 6 honey-inspired gradients on each run
  - Gradients include: Honey, Amber, Golden, Sunset, Caramel, and Wildflower
  - All gradients inspired by honey gold color (#F39C12)
  - Provides visual variety while maintaining brand consistency

## [1.0.10] - 2026-02-12

### Fixed

- **Symfony Compatibility**: Changed Symfony requirements from `^8.0` to `^7.0|^8.0`
  - Allows installation alongside other global packages (Laravel Installer, Sail, etc.)
  - Fixes "could not be resolved to an installable set of packages" error
  - Maintains compatibility with both Symfony 7 and 8
- **Release Workflow**: Fixed semantic version comparison using `sort -V` instead of string comparison
  - Fixes issue where version 1.0.10 was incorrectly considered older than 1.0.9

## [1.0.9] - 2026-02-12

### Fixed

- **Workspace Creation**: Fixed composer.json name format in `make:workspace` command
  - Now uses vendor/package format (`phphive/workspace-name`) instead of just workspace name
  - Ensures compatibility with Composer's package naming requirements
  - Prevents "Does not match the regex pattern" error during `composer install`

### Changed

- Template repository now includes `phphive/cli` as dev dependency
- Workspace `bin/hive` wrapper now uses local vendor installation first
- Updated template README with new installation workflow

## [1.0.8] - 2026-02-12

### Added

- **Update Checker**: Automatic version checking with beautiful notification banner
  - Checks Packagist API for latest version once per day
  - Caches results in `~/.cache/phphive/update-check.json` (respects `XDG_CACHE_HOME`)
  - Displays update notification similar to npm/pnpm/yarn
  - Non-blocking with 2-second timeout
  - Shows current version → latest version with update command
  - Only checks when running actual commands (not help/version)

### Changed

- Update notifications are now displayed automatically before the banner
- Cache directory follows XDG Base Directory specification

## [1.0.7] - 2026-02-12

### Fixed

- **Composer Execution**: Replaced shell command execution with direct Process array execution
  - Uses `findComposerBinary()` method to reliably locate Composer
  - Supports `COMPOSER_BINARY` environment variable for custom paths
  - Checks for `composer.phar` in current directory and monorepo root
  - Falls back to global composer via `which`/`where` command
  - Fixes issues with wrapper scripts and PATH resolution
  - More reliable across different environments (CI, Docker, local)
  - Better error handling and debugging capabilities
  - Resolves monorepo path detection issues with globally installed CLI

### Changed

- Composer commands now use direct binary execution instead of shell interpolation
- Improved cross-platform compatibility (Windows, macOS, Linux)

## [1.0.6] - 2026-02-12

### Added

- **Automatic Version Management**: Release workflow now automatically updates version numbers
  - Updates `APP_VERSION` constant in `Application.php`
  - Updates version badge in `.github/assets/banner.svg`
  - Commits changes automatically with `[skip ci]` flag
  - Ensures version consistency across all files

### Changed

- Version management is now fully automated through GitHub Actions
- No manual version updates needed in code files

## [1.0.5] - 2026-02-12

### Changed

- **Documentation Updates**: Updated all command examples and documentation
  - Changed binary references from `./cli/bin/hive` to `hive` for consistency
  - Aligned with global installation usage pattern
  - Better consistency with template repository structure

### Fixed

- **Template Repository**: Fixed package.json scripts to use correct binary path
  - Changed from `./cli/bin/hive` to `./bin/hive`
  - Fixes postinstall and other npm scripts in cloned workspaces

## [1.0.4] - 2026-02-12

### Changed

- **Minimum PHP Version**: Upgraded from PHP 8.3 to PHP 8.4
  - Symfony 8 requires PHP 8.4+
  - CI workflow now tests on PHP 8.4 and 8.5 only
  - Platform configuration updated to PHP 8.4.0
- **Symfony 8 Upgrade**: All Symfony components upgraded from ^7.0 to ^8.0
  - symfony/console ^8.0
  - symfony/process ^8.0
  - symfony/finder ^8.0

### Fixed

- CI workflow compatibility with Symfony 8 requirements
- Composer platform checks now properly aligned with minimum PHP version

## [1.0.3] - 2026-02-12

### Changed

- **Template-Based Workspace Creation**: `make:workspace` now clones from official template repository
  - Clones from https://github.com/pixielity-co/hive-template
  - Includes pre-configured sample app and package
  - Complete monorepo structure with all configuration files
  - Automatic package name updates in package.json and composer.json
  - Fresh git repository initialization
- **Simplified Command Options**: Removed `--type` and `--no-git` options (no longer needed with template)

### Fixed

- PHPStan level 8 compliance issues in AbstractAppType and Arr.php
- Redundant instanceof checks removed for cleaner code

## [1.0.2] - 2026-02-11

### Fixed

- **--no-interaction flag**: Now properly supported across all app types
  - Helper methods (askText, askSelect, askConfirm) check `isInteractive()` automatically
  - Returns default values when running in non-interactive mode
  - No need to manually check in each app type's collectConfiguration
- **Command execution directory**: Fixed composer install running in wrong directory
  - Changed from `passthru` with `cd` to Symfony Process with proper working directory
  - Post-installation commands now execute in the correct app directory
- **Monorepo root detection**: Now starts from current working directory instead of CLI installation directory
  - Works correctly with globally installed CLI
  - Properly detects turbo.json and pnpm-workspace.yaml from where command is run

### Changed

- Refactored non-interactive mode handling to AbstractAppType for consistency
- All app types (Laravel, Symfony, Magento, Skeleton) automatically support --no-interaction

## [1.0.1] - 2026-02-11

### Changed

- **Updated Dependencies** - Upgraded illuminate/support and illuminate/container from ^11.0 to ^12.0 for compatibility with Laravel 12
- **Improved Compatibility** - Now compatible with global Composer installations that have Laravel Installer v5.17+

### Fixed

- Resolved dependency conflict when installing globally alongside Laravel Installer
- Fixed illuminate package version constraints to match latest Laravel ecosystem

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

[1.0.2]: https://github.com/pixielity-co/phphive-cli/releases/tag/v1.0.2
[1.0.1]: https://github.com/pixielity-co/phphive-cli/releases/tag/v1.0.1
[1.0.0]: https://github.com/pixielity-co/phphive-cli/releases/tag/v1.0.0
[Unreleased]: https://github.com/pixielity-co/phphive-cli/compare/v1.0.2...HEAD
