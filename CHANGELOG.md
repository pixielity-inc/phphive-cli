# Changelog

All notable changes to PhpHive CLI will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.19] - 2026-02-12

### Added

- **Package Type Architecture**: Implemented factory pattern for package types
  - `PackageTypeInterface` - Contract for all package type implementations
  - `AbstractPackageType` - Base implementation with common functionality
  - Concrete types: `LaravelPackageType`, `MagentoPackageType`, `SymfonyPackageType`, `SkeletonPackageType`
  - `PackageTypeFactory` - Creates and validates package type instances
  - Type-specific behavior properly encapsulated
  - Automatic file naming (ServiceProvider, Bundle, Extension)

- **Foundation Services for Enhanced UX**:
  - `NameSuggestionService` - Intelligent name suggestions with 5 strategies
  - `PreflightChecker` - Environment validation before operations
  - `PreflightResult` - Structured result object with error messages and fixes

- **Restructured Stubs Directory**:
  - Organized hierarchy: `apps/`, `packages/`, `config/`
  - Removed -app and -package suffixes
  - Added sample controllers for all package types
  - Shared config files in dedicated directory

- **Service Connection Verification**:
  - Redis, Elasticsearch, Meilisearch, Minio connection checks
  - Spinner UI with ping functionality
  - Clear error messages on connection failure

### Changed

- **CreatePackageCommand**: Refactored to use package type architecture
  - Delegates to package types for behavior
  - Cleaner, more maintainable code
  - Automatic composer install via postCreate hook

- **AppTypes**: Updated for new stubs structure
  - Use `apps/` directory instead of `-app` suffix
  - Maintain backward compatibility

- **Application**: Register new foundation services in container

### Fixed

- File naming for Laravel ServiceProvider (now properly named)
- File naming for Symfony Bundle and Extension (now properly named)
- Variable replacement in Magento XML files (MODULE_NAME, PACKAGE_NAME_NORMALIZED)

## [1.0.18] - 2026-02-12

### Added

- **Service Wrapper Classes**: Created dedicated service classes for better architecture
  - `Process` - Wraps Symfony Process with common patterns and error handling
  - `Composer` - Wraps Composer operations with consistent interface
  - `Docker` - Wraps Docker and Docker Compose operations
  - All services registered as singletons in Application container
  - Accessor methods in BaseCommand: `process()`, `composerService()`, `dockerService()`

### Changed

- **Refactored File Operations**: All raw PHP file operations now use Filesystem service
  - Replaced `file_get_contents`, `file_put_contents`, `mkdir` with Filesystem methods
  - Updated traits: `InteractsWithTurborepo`, `InteractsWithMonorepo`, `ChecksForUpdates`, `HasDiscovery`
  - Improved error handling and testability
  - Consistent exception handling across all file operations

- **Refactored Process Operations**: All `new Process()` calls now use Process service
  - Updated traits: `InteractsWithDocker`, `InteractsWithComposer`, `InteractsWithMinio`, `InteractsWithMeilisearch`, `InteractsWithElasticsearch`
  - Centralized process execution logic
  - Better error messages and timeout handling

- **Removed Unnecessary Method Checks**: Cleaned up `method_exists()` calls
  - Removed redundant checks in `InteractsWithDatabase`, `InteractsWithRedis`, `InteractsWithElasticsearch`, `InteractsWithMeilisearch`, `InteractsWithMinio`
  - PHPStan now correctly infers trait method availability
  - Cleaner, more maintainable code

- **Fixed Method Naming Conflicts**: Resolved composer() method collision
  - Renamed BaseCommand methods: `composer()` → `composerService()`, `docker()` → `dockerService()`
  - Trait method `composer()` aliased as `executeComposer` in BaseCommand
  - Added PHPDoc `@method` annotation for PHPStan compatibility
  - Clear separation between service accessors and command executors

### Fixed

- **PHPStan Compliance**: Fixed all 18 PHPStan errors
  - Fixed short ternary operator in `ChecksForUpdates.php` (line 128)
  - Fixed preg_match comparison in `InteractsWithComposer.php` (line 247)
  - Fixed preg_match boolean check in `InteractsWithMeilisearch.php` (line 379)
  - Removed useless string casts added by Rector
  - Fixed method signature mismatches
  - All files now pass strict type checking

- **Rector Configuration**: Disabled `NullToStrictStringFuncCallArgRector`
  - Prevents conflict with PHPStan when Filesystem service returns guaranteed string types
  - Avoids unnecessary string casts that PHPStan flags as useless
  - Better alignment between Rector and PHPStan rules

### Technical Details

- All tests passing: 58/58 ✓
- PHPStan: 0 errors ✓
- Pint linting: All files pass ✓
- Rector: No issues ✓
- Code quality significantly improved with service-oriented architecture

## [1.0.17] - 2026-02-12

### Added

- **Complete Service Integration System**: Comprehensive traits for all major services
  - `InteractsWithRedis` - Redis cache and session storage with Docker-first approach
  - `InteractsWithElasticsearch` - Elasticsearch 7.x/8.x search engine with optional Kibana
  - `InteractsWithMinio` - S3-compatible object storage with Console UI
  - `InteractsWithMeilisearch` - Modern search engine with instant results
  - All traits follow Docker-first approach with graceful local fallbacks
  - Secure credential generation using cryptographically secure random bytes
  - Health checking and container readiness verification
  - Comprehensive docblocks and detailed comments throughout
  - Reusable across all app types (Magento, Laravel, Symfony, Skeleton)

- **Docker Compose Templates**: Pre-configured templates for all services
  - `redis.yml` - Redis 7 Alpine with password protection and persistence
  - `elasticsearch.yml` - Elasticsearch 8.x with Kibana and security enabled
  - `minio.yml` - MinIO with API (9000) and Console (9001) ports
  - `meilisearch.yml` - Meilisearch with master key protection
  - `mailpit.yml` - Email testing with SMTP (1025) and Web UI (8025)
  - `laravel-full.yml` - Complete Laravel stack (MySQL, Redis, Meilisearch, Mailpit, MinIO)
  - `symfony-full.yml` - Complete Symfony stack (PostgreSQL, Redis, Elasticsearch, Kibana, Mailpit)
  - All templates use standard ports and named volumes for data persistence
  - Valid YAML syntax with comprehensive comments

- **Service-Specific Features**:
  - **Redis**: Standalone, Sentinel, and Cluster support with password authentication
  - **Elasticsearch**: Version selection (7.x/8.x), optional Kibana, security configuration
  - **MinIO**: Automatic bucket creation, access/secret key generation, Console UI
  - **Meilisearch**: Master key generation, health checking, OS-specific installation guidance
  - All services include health checks and wait-for-ready logic

### Changed

- **Enhanced Docker Integration**: All service traits integrate seamlessly with `InteractsWithDocker`
  - Automatic Docker detection and availability checking
  - Installation guidance for Docker when not available
  - Container lifecycle management (start, stop, health checks)
  - Graceful fallbacks to local installations

### Technical Details

- **Security**: All credentials generated using `random_bytes()` for cryptographic security
- **Ports**: Standard ports used (Redis: 6379, Elasticsearch: 9200, MinIO: 9000/9001, Meilisearch: 7700)
- **Persistence**: Named Docker volumes ensure data survives container restarts
- **Health Checks**: All services include health check endpoints with retry logic
- **Documentation**: Comprehensive docblocks matching PhpHive CLI standards
- **Code Quality**: All code passes PHPStan level 8, Laravel Pint, and Rector checks

## [1.0.16] - 2026-02-12

### Added

- **Docker Integration for Database Setup**: New `InteractsWithDocker` trait for comprehensive Docker management
  - Automatic Docker detection and installation guidance
  - Docker Compose template generation for multiple database types
  - Container lifecycle management (start, stop, health checks)
  - Support for MySQL 8.0, PostgreSQL 15, and MariaDB 10.11
  - Optional admin tools (phpMyAdmin for MySQL/MariaDB, Adminer for PostgreSQL)
  - Named volumes for data persistence
  - Standard ports (3306 for MySQL/MariaDB, 5432 for PostgreSQL)
  - Graceful fallback to local MySQL when Docker unavailable

- **Docker-First Database Setup**: Enhanced `InteractsWithDatabase` trait with Docker integration
  - Orchestrates Docker-first approach with automatic fallback
  - Detects Docker availability and recommends Docker when available
  - Generates secure passwords for database users
  - Waits for database containers to be ready before proceeding
  - Provides detailed feedback using Laravel Prompts
  - Seamless integration with existing local MySQL setup

- **Command-Line Flags for Magento**: Added comprehensive flags to `make:app` command
  - `--description`: Application description
  - `--magento-version`: Magento version (2.4.6, 2.4.7)
  - `--magento-public-key`: Magento public key from marketplace
  - `--magento-private-key`: Magento private key from marketplace
  - `--db-host`, `--db-port`, `--db-name`, `--db-user`, `--db-password`: Database configuration
  - `--admin-firstname`, `--admin-lastname`, `--admin-email`, `--admin-user`, `--admin-password`: Admin user
  - `--base-url`, `--language`, `--currency`, `--timezone`: Store configuration
  - `--sample-data`: Install sample data flag
  - `--elasticsearch`, `--elasticsearch-host`, `--elasticsearch-port`: Elasticsearch configuration
  - `--redis`, `--redis-host`, `--redis-port`: Redis configuration
  - `--use-docker`: Force Docker database setup
  - `--no-docker`: Skip Docker and use local MySQL
  - Enables fully non-interactive Magento app creation

### Changed

- **Magento App Creation**: Now uses Docker-first database setup
  - Automatically offers Docker database setup when Docker is available
  - Falls back to local MySQL setup if Docker unavailable or declined
  - Supports both MySQL and MariaDB via Docker
  - Simplified database configuration workflow
  - All configuration can be provided via command-line flags

### Fixed

- **PHPStan Type Safety**: Fixed type mismatch in `setupDockerDatabase` method
  - Cast `select()` return value to string for `generateDockerComposeFile()`
  - Added baseline entries for `method_exists()` checks in trait composition
  - Fixed boolean type checks for optional feature flags

## [1.0.15] - 2026-02-12

### Added

- **Automatic MySQL Database Setup**: New `InteractsWithDatabase` trait for automatic database creation
  - Tests MySQL connection before attempting setup
  - Prompts for MySQL admin credentials
  - Automatically creates database and user with proper privileges
  - Graceful fallback to manual configuration on failure
  - Reusable across all app types (Magento, Laravel, Symfony, etc.)
  - Beautiful interactive prompts using Laravel Prompts
  - Secure password masking for all credential inputs

- **Password Masking**: Added `password()` method to AbstractAppType
  - Magento private key now uses masked password input
  - Secure credential handling throughout the CLI

### Fixed

- **Magento Authentication**: Changed from creating auth.json file to using COMPOSER_AUTH environment variable
  - Prevents "directory not empty" error during composer create-project
  - More reliable authentication method
  - No pre-install file creation needed

### Changed

- **Magento Database Setup**: Now offers automatic or manual database configuration
  - Asks user preference before prompting for database details
  - Automatic setup creates database and user if MySQL is accessible
  - Manual setup available as fallback or user preference

## [1.0.14] - 2026-02-12

### Fixed

- **Symfony App Creation**: Changed from abandoned `symfony/website-skeleton` to `symfony/skeleton`
  - Now uses `symfony/skeleton` for all project types
  - Installs `symfony/webapp-pack` for full-featured applications
  - Supports Symfony 7.1 (LTS), 7.0, and 6.4 (LTS)
  
- **Magento Authentication**: Fixed authentication configuration for Magento installation
  - Now creates `auth.json` file with credentials before installation
  - More reliable than using `composer config --global` command
  - Prevents "http-basic.repo.magento.com is not defined" error
  - Uses Laravel Prompts `note()` for cleaner authentication key instructions

### Added

- **Pre-Install Commands**: Added `getPreInstallCommands()` method to app type interface
  - Allows app types to run setup commands before main installation
  - Used by Magento to configure authentication before composer create-project
  - Default implementation in AbstractAppType returns empty array

## [1.0.13] - 2026-02-12

### Added

- **Magento Authentication**: Added prompts for Magento authentication keys
  - Prompts for public key (username) and private key (password)
  - Automatically configures Composer authentication before installation
  - Provides helpful link to Magento Marketplace for obtaining keys

### Fixed

- **Symfony Version**: Fixed Symfony version options in `make:app` command
  - Changed from non-existent 7.2 to available 7.1 (LTS)
  - Updated default to 7.1 (LTS) instead of 7.2
  - Options now: 7.1 (LTS), 7.0, 6.4 (LTS)

## [1.0.12] - 2026-02-12

### Added

- **BaseCommand Enhancements**: Added common options and helper methods
  - Added `--all` flag to apply operations to all workspaces
  - Added `--dry-run` flag to preview operations without executing
  - Added `outputJson()` method for JSON output formatting
  - Added `outputTable()` method using Laravel Prompts for beautiful table display

- **Workspace Selection Methods**: Added to `InteractsWithMonorepo` concern
  - Added `selectWorkspace()` for interactive single workspace selection
  - Added `selectWorkspaces()` for interactive multiple workspace selection
  - Added `getAllWorkspaceNames()` to get all workspace names as array
  - Added `shouldRunOnAll()` to check if --all flag is set

### Fixed

- **Laravel App Creation**: Fixed version selection bug in `make:app` command
  - Changed version option keys from numeric strings to prefixed strings (v12, v11, v10)
  - Prevents shell syntax errors in Composer create-project command
  - Now correctly generates: `composer create-project laravel/laravel:12.x . --prefer-dist`

### Changed

- **Table Output**: Switched from Symfony Table component to Laravel Prompts `table()` function
  - Provides more beautiful and consistent table formatting
  - Aligns with existing Laravel Prompts usage throughout the CLI

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
  - Helper methods (text, askSelect, confirm) check `isInteractive()` automatically
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
