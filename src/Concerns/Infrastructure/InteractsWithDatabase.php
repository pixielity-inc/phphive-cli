<?php

declare(strict_types=1);

namespace PhpHive\Cli\Concerns\Infrastructure;

use PhpHive\Cli\Contracts\AppTypeInterface;
use PhpHive\Cli\DTOs\Infrastructure\DatabaseConfig;
use PhpHive\Cli\Enums\DatabaseType;
use PhpHive\Cli\Services\Infrastructure\DatabaseSetupService;
use PhpHive\Cli\Services\Infrastructure\MySQLService;
use RuntimeException;

/**
 * Database Interaction Trait.
 *
 * This trait provides user interaction and prompting for database setup across
 * multiple database types. It focuses solely on collecting user input and delegates
 * all business logic to specialized services for better separation of concerns and testability.
 *
 * Supported database types:
 * - MySQL: Popular open-source relational database
 * - PostgreSQL: Advanced open-source relational database with rich features
 * - MariaDB: MySQL-compatible database with enhanced performance
 *
 * Key features:
 * - Docker-first approach: Recommends Docker when available for isolation
 * - Multiple database types: MySQL, PostgreSQL, MariaDB support
 * - Interactive prompts for configuration collection
 * - Graceful error handling with fallback options
 * - Automatic database creation with admin credentials
 * - Manual configuration for existing databases
 * - Optional admin tools (phpMyAdmin/Adminer) for Docker setups
 *
 * Architecture:
 * - This trait handles user prompts and input collection
 * - DatabaseSetupService handles Docker setup and orchestration
 * - MySQLService handles MySQL-specific operations (connection, database creation)
 * - DatabaseConfig DTO provides type-safe configuration
 * - DockerComposeGenerator handles docker-compose.yml generation
 * - Docker service handles container operations
 *
 * Docker-first approach workflow:
 * 1. Check if Docker is available on the system
 * 2. If yes, offer Docker setup (recommended for isolation and ease)
 * 3. If Docker fails or unavailable, fall back to local database setup
 * 4. For local setup, offer automatic (create DB) or manual (existing DB) configuration
 *
 * Security considerations:
 * - Admin credentials are only used temporarily for database creation
 * - Admin credentials are never stored in configuration
 * - Only application-specific user credentials are persisted
 * - Passwords are collected using secure password prompts (hidden input)
 * - Generated passwords use cryptographically secure random bytes
 *
 * Example usage:
 * ```php
 * use PhpHive\Cli\Concerns\Infrastructure\InteractsWithDatabase;
 * use PhpHive\Cli\Enums\DatabaseType;
 *
 * class MyAppType extends AbstractAppType
 * {
 *     use InteractsWithDatabase;
 *
 *     public function collectConfiguration($input, $output): array
 *     {
 *         // Setup database for the application
 *         $dbConfig = $this->setupDatabase(
 *             'my-app',
 *             [DatabaseType::MYSQL, DatabaseType::POSTGRESQL],
 *             '/path/to/app'
 *         );
 *
 *         return array_merge($config, $dbConfig);
 *     }
 * }
 * ```
 *
 * @see DatabaseSetupService For database setup business logic
 * @see MySQLService For MySQL-specific operations
 * @see DatabaseConfig For type-safe configuration DTO
 * @see InteractsWithDocker For Docker availability checks
 * @see InteractsWithPrompts For prompt helper methods
 */
trait InteractsWithDatabase
{
    /**
     * Get the DatabaseSetupService instance.
     *
     * This abstract method must be implemented by the class using this trait
     * to provide access to the DatabaseSetupService for delegating setup operations.
     *
     * @return DatabaseSetupService The database setup service instance
     */
    abstract protected function databaseSetupService(): DatabaseSetupService;

    /**
     * Orchestrate database setup with Docker-first approach.
     *
     * This is the main entry point for database setup. It determines whether
     * to use Docker or local installation based on availability and user preference,
     * then delegates to the appropriate setup method.
     *
     * Workflow:
     * 1. Check if Docker is available on the system
     * 2. If yes, inform user about Docker benefits and prompt for usage
     * 3. If user accepts Docker:
     *    - Collect Docker configuration (database type, credentials, admin tool)
     *    - Delegate to DatabaseSetupService for container setup
     *    - Return configuration if successful
     * 4. If Docker fails or user declines:
     *    - Fall back to local database setup
     *    - Offer automatic (create DB) or manual (existing DB) configuration
     * 5. If Docker is not installed, optionally show installation instructions
     *
     * Docker benefits explained to user:
     * - Isolated databases (no conflicts with system installations)
     * - Easy management (start/stop with docker-compose)
     * - No local installation needed (runs in containers)
     * - Consistent environment across development machines
     *
     * @param  string              $appName            Application name for generating default database names
     * @param  array<DatabaseType> $supportedDatabases Array of supported database types for this app
     * @param  string              $appPath            Absolute path to application directory for Docker Compose
     * @return array               Database configuration array with keys:
     *                             - db_type: Database type (mysql, postgresql, mariadb)
     *                             - db_host: Database host
     *                             - db_port: Database port
     *                             - db_name: Database name
     *                             - db_user: Database username
     *                             - db_password: Database password
     *                             - using_docker: Whether Docker is being used
     *
     * Common failure scenarios:
     * - Docker not available: Falls back to local setup
     * - Docker setup fails: Falls back to local setup with warning
     * - Local connection fails: Returns null from automatic setup, prompts for manual
     * - Database creation fails: Returns null from automatic setup, prompts for manual
     */
    protected function setupDatabase(string $appName, array $supportedDatabases, string $appPath): array
    {
        // Check if Docker is available on the system
        if ($this->isDockerAvailable()) {
            // Inform user about Docker benefits to encourage adoption
            $this->note(
                'Docker detected! Using Docker provides isolated databases, easy management, and no local installation needed.',
                'Database Setup'
            );

            // Prompt user to use Docker (recommended approach)
            $useDocker = $this->confirm(
                label: 'Would you like to use Docker for the database? (recommended)',
                default: true
            );

            // User chose to use Docker
            if ($useDocker) {
                // Attempt Docker database setup
                $dbConfig = $this->setupDockerDatabase($appName, $supportedDatabases, $appPath);

                // If Docker setup succeeded, return the configuration
                if ($dbConfig !== null) {
                    return $dbConfig;
                }

                // Docker setup failed, inform user and fall back to local
                $this->warning('Docker setup failed. Falling back to local database setup.');
            }
        } elseif (! $this->isDockerInstalled()) {
            // Docker is not installed at all, offer installation guidance
            $installDocker = $this->confirm(
                label: 'Docker is not installed. Would you like to see installation instructions?',
                default: false
            );

            // User wants to see Docker installation instructions
            if ($installDocker) {
                $this->provideDockerInstallationGuidance();
                $this->info('After installing Docker, you can recreate this application to use Docker.');
            }
        }

        // Fall back to local database setup (Docker unavailable, declined, or failed)
        return $this->setupLocalDatabase($appName);
    }

    /**
     * Set up database using Docker containers.
     *
     * Collects configuration and delegates Docker container setup to DatabaseSetupService.
     * This method handles the interactive prompting for Docker-based database configuration.
     *
     * Workflow:
     * 1. Prompt user to select database type (MySQL, PostgreSQL, MariaDB)
     * 2. Ask if they want to include admin tool (phpMyAdmin for MySQL, Adminer for others)
     * 3. Collect database credentials (name, username, password)
     * 4. Create DatabaseConfig DTO with collected information
     * 5. Delegate to DatabaseSetupService to generate docker-compose.yml
     * 6. Start Docker containers and verify setup
     * 7. Return configuration array on success
     *
     * Admin tools:
     * - phpMyAdmin: Web-based MySQL administration (port 8080)
     * - Adminer: Lightweight database management for multiple DB types (port 8080)
     *
     * Security:
     * - Passwords are collected using secure password prompts (hidden input)
     * - User is prompted to enter a secure password with hint
     * - Credentials are only used for the application database user
     *
     * @param  string              $appName            Application name for generating defaults
     * @param  array<DatabaseType> $supportedDatabases Supported database types for selection
     * @param  string              $appPath            Application directory path for docker-compose.yml
     * @return array|null          Database config array on success, null on failure
     *
     * Return value example:
     * ```php
     * [
     *     'db_type' => 'mysql',
     *     'db_host' => 'localhost',
     *     'db_port' => 3306,
     *     'db_name' => 'my_app',
     *     'db_user' => 'my_app_user',
     *     'db_password' => 'secure_password',
     *     'using_docker' => true,
     * ]
     * ```
     *
     * Common failure scenarios:
     * - Non-interactive mode: Returns null (Docker setup requires interaction)
     * - Docker container fails to start: Returns null with error message
     * - docker-compose.yml generation fails: Returns null with error message
     */
    protected function setupDockerDatabase(string $appName, array $supportedDatabases, string $appPath): ?array
    {
        // Prompt user to select database type (MySQL, PostgreSQL, MariaDB)
        // In non-interactive mode, uses first supported database type
        $dbTypeEnum = $this->promptDatabaseType($supportedDatabases);

        // Ask if user wants to include database admin tool (phpMyAdmin/Adminer)
        // Admin tools provide web-based database management at http://localhost:8080
        $includeAdmin = $this->confirm(
            label: 'Include database admin tool? (phpMyAdmin/Adminer)',
            default: true
        );

        // Normalize app name for use as database name (lowercase, alphanumeric + underscores)
        // Example: "My App!" becomes "my_app"
        $normalizedName = strtolower(preg_replace('/[^a-zA-Z0-9]/', '_', $appName) ?? $appName);

        // Prompt for database name (defaults to normalized app name)
        $dbName = $this->text(
            label: 'Database name',
            default: $normalizedName,
            required: true
        );

        // Prompt for database username (defaults to normalized app name + "_user")
        $dbUser = $this->text(
            label: 'Database username',
            default: "{$normalizedName}_user",
            required: true
        );

        // Prompt for database password (secure input, hidden from display)
        $dbPassword = $this->password(
            label: 'Database password',
            required: true,
            hint: 'Enter a secure password for the database user'
        );

        // Create type-safe configuration object for Docker setup
        // Port is set to database type's default (MySQL: 3306, PostgreSQL: 5432, etc.)
        $databaseConfig = new DatabaseConfig(
            type: $dbTypeEnum,
            host: 'localhost',
            port: $dbTypeEnum->getDefaultPort() ?? 3306,
            name: $dbName,
            user: $dbUser,
            password: $dbPassword,
            usingDocker: true,
        );

        // Inform user that docker-compose.yml is being generated
        $this->info('Generating docker-compose.yml...');

        try {
            // Delegate Docker setup to service with spinner for user feedback
            // Service will generate docker-compose.yml and start containers
            $finalConfig = $this->spin(
                callback: fn (): DatabaseConfig => $this->databaseSetupService()->setupDocker($databaseConfig, $appPath) ?? throw new RuntimeException('Docker setup failed'),
                message: 'Setting up Docker database...'
            );

            // Docker setup succeeded, inform user
            $this->info('✓ Docker database setup complete!');

            // If admin tool was included, show access URL
            if ($includeAdmin) {
                $this->info('Database admin tool: http://localhost:8080');
            }

            // Convert DatabaseConfig DTO to array format for application configuration
            return $finalConfig->toArray();
        } catch (RuntimeException) {
            // Docker setup failed (container start failed, file generation failed, etc.)
            $this->error('Failed to set up Docker database');

            return null;
        }
    }

    /**
     * Set up database using local MySQL installation.
     *
     * Provides two setup modes for local MySQL databases:
     * 1. Automatic: Creates new database and user with admin credentials
     * 2. Manual: Uses existing database with provided credentials
     *
     * Workflow:
     * 1. Inform user about local MySQL setup requirements
     * 2. Prompt user to choose automatic or manual setup
     * 3. If automatic:
     *    - Collect admin credentials to create database
     *    - Test connection with admin credentials
     *    - Create database and user automatically
     *    - Return configuration if successful
     * 4. If automatic fails or user chooses manual:
     *    - Prompt for existing database credentials
     *    - Return configuration for manual setup
     *
     * When to use automatic vs manual:
     * - Automatic: User has MySQL admin access and wants new database created
     * - Manual: User has existing database or no admin access
     *
     * @param  string $appName Application name for generating default database names
     * @return array  Database configuration array with connection details
     *
     * Return value example:
     * ```php
     * [
     *     'db_type' => 'mysql',
     *     'db_host' => '127.0.0.1',
     *     'db_port' => 3306,
     *     'db_name' => 'my_app',
     *     'db_user' => 'my_app_user',
     *     'db_password' => 'secure_password',
     *     'using_docker' => false,
     * ]
     * ```
     */
    protected function setupLocalDatabase(string $appName): array
    {
        // Inform user about local MySQL setup requirements
        // User must have MySQL installed and running on their system
        $this->note(
            'Setting up local MySQL database. Ensure MySQL is installed and running.',
            'Local Database Setup'
        );

        // Prompt user to choose between automatic (create DB) or manual (existing DB) setup
        $autoSetup = $this->confirm(
            label: 'Would you like automatic MySQL database setup?',
            default: true
        );

        // User chose automatic setup (create database with admin credentials)
        if ($autoSetup) {
            // Attempt automatic database creation
            $dbConfig = $this->promptAutomaticDatabaseSetup($appName);

            // If automatic setup succeeded, return the configuration
            if ($dbConfig !== null) {
                return $dbConfig;
            }

            // Automatic setup failed, will fall through to manual setup
        }

        // Fall back to manual setup (user provides existing database credentials)
        return $this->promptManualDatabaseSetup($appName);
    }

    /**
     * Prompt user for automatic database setup with MySQL.
     *
     * Automatically creates a new MySQL database and user using admin credentials.
     * This is the recommended approach when the user has MySQL admin access.
     *
     * Workflow:
     * 1. Explain automatic setup process to user
     * 2. Collect MySQL admin credentials (root or other admin user)
     * 3. Test connection to MySQL server with admin credentials
     * 4. If connection fails, return null (will fall back to manual setup)
     * 5. Collect desired database name and user credentials
     * 6. Create database and user with appropriate privileges
     * 7. Return configuration array on success
     *
     * Admin credentials usage:
     * - Used only to create database and user (CREATE DATABASE, CREATE USER privileges)
     * - Never stored in configuration files
     * - Only application user credentials are persisted
     * - Admin credentials are discarded after database creation
     *
     * Connection testing approach:
     * - Tests connection before attempting database creation
     * - Provides immediate feedback if credentials are incorrect
     * - Prevents partial setup failures
     * - Uses MySQLService for connection validation
     *
     * Database creation process:
     * 1. Connect to MySQL with admin credentials
     * 2. Execute CREATE DATABASE statement
     * 3. Execute CREATE USER statement
     * 4. Execute GRANT ALL PRIVILEGES statement
     * 5. Execute FLUSH PRIVILEGES statement
     * 6. Verify database and user were created successfully
     *
     * @param  string     $appName Application name for generating default names
     * @return array|null Database configuration array on success, null on failure
     *
     * Return value example:
     * ```php
     * [
     *     'db_type' => 'mysql',
     *     'db_host' => '127.0.0.1',
     *     'db_port' => 3306,
     *     'db_name' => 'my_app',
     *     'db_user' => 'my_app_user',
     *     'db_password' => 'secure_password',
     *     'using_docker' => false,
     * ]
     * ```
     *
     * Common failure scenarios:
     * - Non-interactive mode: Returns null (requires user input)
     * - MySQL connection fails: Returns null with error message
     * - Admin credentials incorrect: Returns null after connection test
     * - Insufficient privileges: Returns null after creation attempt
     * - Database already exists: Returns null with error message
     */
    protected function promptAutomaticDatabaseSetup(string $appName): ?array
    {
        // Explain automatic setup process to user
        $this->note(
            'Automatic database setup will create a MySQL database and user for your application.',
            'Automatic Database Setup'
        );

        // Collect MySQL server connection details
        // Host: MySQL server hostname or IP address (default: 127.0.0.1 for local)
        $host = $this->text(
            label: 'MySQL host',
            default: '127.0.0.1',
            required: true,
            hint: 'The MySQL server hostname or IP address'
        );

        // Port: MySQL server port number (default: 3306 - MySQL standard port)
        $portInput = $this->text(
            label: 'MySQL port',
            default: '3306',
            required: true,
            hint: 'The MySQL server port number'
        );
        $port = (int) $portInput;

        // Collect MySQL admin credentials for database creation
        // Admin user: Must have CREATE DATABASE and CREATE USER privileges
        // Common admin users: root, admin, dba
        $adminUser = $this->text(
            label: 'MySQL admin username',
            default: 'root',
            required: true,
            hint: 'User with CREATE DATABASE and CREATE USER privileges'
        );

        // Admin password: Secure input (hidden from display)
        // Can be empty if MySQL is configured without root password (development only)
        $adminPass = $this->password(
            label: 'MySQL admin password',
            required: false,
            hint: 'Leave empty if no password is set'
        );

        // Test MySQL connection before attempting database creation
        // This provides immediate feedback if credentials are incorrect
        $this->info('Testing MySQL connection...');
        $mysqlService = MySQLService::make();

        // Attempt connection with admin credentials using spinner for feedback
        $connectionSuccess = $this->spin(
            callback: fn (): bool => $mysqlService->checkConnection($host, $port, $adminUser, $adminPass),
            message: 'Connecting to MySQL...'
        );

        // Connection failed - admin credentials incorrect or MySQL not running
        if (! $connectionSuccess) {
            $this->error('Failed to connect to MySQL. Please check your credentials and try again.');

            return null;
        }

        // Connection successful, proceed with database creation
        $this->info('✓ MySQL connection successful!');

        // Normalize app name for use as database name (lowercase, alphanumeric + underscores)
        // Example: "My App!" becomes "my_app"
        $normalizedName = strtolower(preg_replace('/[^a-zA-Z0-9]/', '_', $appName) ?? $appName);

        // Prompt for database name (defaults to normalized app name)
        $dbName = $this->text(
            label: 'Database name',
            default: $normalizedName,
            required: true
        );

        // Prompt for database username (defaults to normalized app name + "_user")
        // This is the application user, not the admin user
        $dbUser = $this->text(
            label: 'Database username',
            default: "{$normalizedName}_user",
            required: true
        );

        // Prompt for database password (secure input, hidden from display)
        // This password will be stored in application configuration
        $dbPass = $this->password(
            label: 'Database password',
            required: true,
            hint: 'Password for the database user'
        );

        // Create database and user using admin credentials
        $this->info('Creating database and user...');

        // Delegate database creation to MySQLService with spinner for feedback
        // Service will execute CREATE DATABASE, CREATE USER, and GRANT statements
        $creationSuccess = $this->spin(
            callback: fn (): bool => $mysqlService->createDatabase(
                $host,
                $port,
                $adminUser,
                $adminPass,
                $dbName,
                $dbUser,
                $dbPass
            ),
            message: 'Setting up database...'
        );

        // Database creation failed - insufficient privileges or database already exists
        if (! $creationSuccess) {
            $this->error('Failed to create database or user. Please check admin privileges and try again.');

            return null;
        }

        // Database and user created successfully
        $this->info('✓ Database and user created successfully!');

        // Return configuration array with application user credentials
        // Note: Admin credentials are NOT included in the configuration
        return [
            AppTypeInterface::CONFIG_DB_TYPE => DatabaseType::MYSQL->value,
            AppTypeInterface::CONFIG_DB_HOST => $host,
            AppTypeInterface::CONFIG_DB_PORT => $port,
            AppTypeInterface::CONFIG_DB_NAME => $dbName,
            AppTypeInterface::CONFIG_DB_USER => $dbUser,
            AppTypeInterface::CONFIG_DB_PASSWORD => $dbPass,
            AppTypeInterface::CONFIG_USING_DOCKER => false,
        ];
    }

    /**
     * Prompt user for manual database configuration.
     *
     * Collects connection details for an existing MySQL database. This is used when:
     * - User has an existing database they want to use
     * - User doesn't have admin credentials to create new database
     * - Automatic database creation failed
     * - User prefers to manage database manually
     *
     * Workflow:
     * 1. Inform user about manual configuration requirements
     * 2. Prompt for database connection details (host, port)
     * 3. Prompt for existing database name
     * 4. Prompt for database user credentials
     * 5. Return configuration array
     *
     * In non-interactive mode, returns sensible defaults for automated setups.
     *
     * Important: This method assumes the database and user already exist.
     * The user is responsible for:
     * - Creating the database beforehand
     * - Creating the user with appropriate privileges
     * - Ensuring the user has access to the database
     *
     * @param  string $appName Application name for generating default database names
     * @return array  Database configuration array with connection details
     *
     * Return value example:
     * ```php
     * [
     *     'db_type' => 'mysql',
     *     'db_host' => '127.0.0.1',
     *     'db_port' => 3306,
     *     'db_name' => 'my_app',
     *     'db_user' => 'root',
     *     'db_password' => 'password',
     *     'using_docker' => false,
     * ]
     * ```
     */
    protected function promptManualDatabaseSetup(string $appName): array
    {
        // Normalize app name for use as database name (lowercase, alphanumeric + underscores)
        // Example: "My App!" becomes "my_app"
        $normalizedName = strtolower(preg_replace('/[^a-zA-Z0-9]/', '_', $appName) ?? $appName);

        // Inform user about manual configuration requirements
        // User must have an existing database with proper credentials
        $this->note(
            'Please enter the connection details for your existing MySQL database.',
            'Manual Database Configuration'
        );

        // Prompt for database host (default: 127.0.0.1 for local MySQL)
        $host = $this->text(
            label: 'Database host',
            default: '127.0.0.1',
            required: true
        );

        // Prompt for database port (default: 3306 - MySQL standard port)
        $portInput = $this->text(
            label: 'Database port',
            default: '3306',
            required: true
        );
        $port = (int) $portInput;

        // Prompt for existing database name (defaults to normalized app name)
        // User must ensure this database exists before running the application
        $dbName = $this->text(
            label: 'Database name',
            default: $normalizedName,
            required: true
        );

        // Prompt for database username (defaults to 'root')
        // User must ensure this user has appropriate privileges on the database
        $dbUser = $this->text(
            label: 'Database username',
            default: 'root',
            required: true
        );

        // Prompt for database password (secure input, hidden from display)
        // Can be empty if MySQL user is configured without password (development only)
        $dbPass = $this->password(
            label: 'Database password',
            required: false,
            hint: 'Leave empty if no password is set'
        );

        // Return configuration array with provided credentials
        return [
            AppTypeInterface::CONFIG_DB_TYPE => DatabaseType::MYSQL->value,
            AppTypeInterface::CONFIG_DB_HOST => $host,
            AppTypeInterface::CONFIG_DB_PORT => $port,
            AppTypeInterface::CONFIG_DB_NAME => $dbName,
            AppTypeInterface::CONFIG_DB_USER => $dbUser,
            AppTypeInterface::CONFIG_DB_PASSWORD => $dbPass,
            AppTypeInterface::CONFIG_USING_DOCKER => false,
        ];
    }

    /**
     * Prompt for database type selection.
     *
     * Handles database type selection based on supported databases for the application.
     * If only one database type is supported, automatically selects it without prompting.
     * If multiple types are supported, presents an interactive selection menu.
     *
     * Database type selection logic:
     * - Single option: Auto-select and inform user
     * - Multiple options: Present interactive menu with descriptions
     * - Each option shows name and description for informed choice
     *
     * Workflow:
     * 1. Check number of supported database types
     * 2. If only one, auto-select and inform user
     * 3. If multiple, build selection menu with names and descriptions
     * 4. Present menu to user and collect selection
     * 5. Convert selected value to DatabaseType enum
     * 6. Return selected database type
     *
     * @param  array<DatabaseType> $supportedDatabases Array of supported database types
     * @return DatabaseType        Selected database type enum
     *
     * Example with single database:
     * - Input: [DatabaseType::MYSQL]
     * - Output: DatabaseType::MYSQL (auto-selected)
     * - User sees: "Using MySQL database"
     *
     * Example with multiple databases:
     * - Input: [DatabaseType::MYSQL, DatabaseType::POSTGRESQL, DatabaseType::MARIADB]
     * - User sees menu:
     *   - MySQL (Popular open-source relational database)
     *   - PostgreSQL (Advanced open-source relational database)
     *   - MariaDB (MySQL-compatible database with enhanced performance)
     * - User selects one
     * - Output: Selected DatabaseType enum
     */
    private function promptDatabaseType(array $supportedDatabases): DatabaseType
    {
        // If only one database type is supported, auto-select it
        if (count($supportedDatabases) === 1) {
            $dbType = $supportedDatabases[0];
            // Inform user which database type is being used
            $this->info("Using {$dbType->getName()} database");

            return $dbType;
        }

        // Multiple database types supported, build selection menu
        // Format: "Name (Description)" => value
        $dbTypeOptions = [];
        foreach ($supportedDatabases as $supportedDatabase) {
            // Create user-friendly option label with name and description
            $dbTypeOptions[$supportedDatabase->getName() . ' (' . $supportedDatabase->getDescription() . ')'] = $supportedDatabase->value;
        }

        // Present interactive selection menu to user
        $selectedType = $this->select(
            label: 'Select database type',
            options: $dbTypeOptions,
            default: $supportedDatabases[0]->value  // Default to first option
        );

        // Convert selected value back to DatabaseType enum
        return DatabaseType::from(is_string($selectedType) ? $selectedType : (string) $selectedType);
    }
}
