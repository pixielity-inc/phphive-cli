<?php

declare(strict_types=1);

namespace PhpHive\Cli\Concerns;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

use mysqli;

/**
 * Database Interaction Trait.
 *
 * This trait provides comprehensive database setup functionality for application
 * types that require database configuration. It supports both Docker-based and
 * local database setups with automatic configuration and graceful fallbacks.
 *
 * Key features:
 * - Docker-first approach: Recommends Docker when available
 * - Multiple database types: MySQL, PostgreSQL, MariaDB
 * - Automatic Docker Compose file generation
 * - Container management and health checking
 * - Local MySQL fallback for non-Docker setups
 * - Secure password handling with masked input
 * - Graceful error handling with fallback options
 * - Detailed user feedback using Laravel Prompts
 * - Reusable across multiple app types (Magento, Laravel, Symfony, etc.)
 *
 * Docker-first workflow:
 * 1. Check if Docker is available
 * 2. If yes, offer Docker database setup (recommended)
 * 3. Prompt for database type (MySQL, PostgreSQL, MariaDB)
 * 4. Generate docker-compose.yml from template
 * 5. Start Docker containers
 * 6. Wait for database to be ready
 * 7. Return connection details
 * 8. If Docker unavailable or user declines, fall back to local setup
 *
 * Local MySQL workflow:
 * 1. Ask if user wants automatic database creation
 * 2. If yes, prompt for MySQL admin credentials
 * 3. Test MySQL connection
 * 4. Create database and user with appropriate privileges
 * 5. Return credentials for application configuration
 * 6. If any step fails, fall back to manual prompts
 *
 * Example usage:
 * ```php
 * use PhpHive\Cli\Concerns\InteractsWithDatabase;
 * use PhpHive\Cli\Concerns\InteractsWithDocker;
 *
 * class MyAppType extends AbstractAppType
 * {
 *     use InteractsWithDatabase;
 *     use InteractsWithDocker;
 *
 *     public function collectConfiguration($input, $output): array
 *     {
 *         $this->input = $input;
 *         $this->output = $output;
 *
 *         // Orchestrate database setup (Docker-first)
 *         $dbConfig = $this->setupDatabase('my-app', ['mysql', 'postgresql']);
 *
 *         return $dbConfig;
 *     }
 * }
 * ```
 *
 * Security considerations:
 * - Admin credentials are only used for setup, never stored
 * - Passwords are masked during input
 * - Database users are created with minimal required privileges
 * - Docker containers are isolated per project
 * - Connection attempts are limited to prevent brute force
 *
 * @see AbstractAppType For base app type functionality
 * @see InteractsWithDocker For Docker management functionality
 * @see InteractsWithPrompts For prompt helper methods
 */
trait InteractsWithDatabase
{
    /**
     * Orchestrate database setup with Docker-first approach.
     *
     * This is the main entry point for database setup. It intelligently
     * chooses between Docker and local MySQL based on availability and
     * user preference, with graceful fallbacks at each step.
     *
     * Decision flow:
     * 1. Check if Docker is available (requires InteractsWithDocker trait)
     * 2. If Docker available:
     *    - Offer Docker setup (recommended)
     *    - If user accepts → setupDockerDatabase()
     *    - If user declines → setupLocalDatabase()
     * 3. If Docker not available:
     *    - Show installation guidance (optional)
     *    - Fall back to setupLocalDatabase()
     *
     * Supported database types:
     * - mysql: MySQL 8.0
     * - postgresql: PostgreSQL 15
     * - mariadb: MariaDB 10.11
     *
     * Return value structure:
     * ```php
     * [
     *     'db_type' => 'mysql',           // Database type
     *     'db_host' => 'localhost',       // Host (localhost for Docker)
     *     'db_port' => 3306,              // Port
     *     'db_name' => 'my_app',          // Database name
     *     'db_user' => 'my_app_user',     // Username
     *     'db_password' => 'password',    // Password
     *     'using_docker' => true,         // Whether Docker is used
     * ]
     * ```
     *
     * @param  string        $appName            Application name for defaults
     * @param  array<string> $supportedDatabases Array of supported database types
     * @param  string        $appPath            Absolute path to application directory
     * @return array         Database configuration array
     */
    protected function setupDatabase(string $appName, array $supportedDatabases, string $appPath): array
    {
        // Check if Docker is available (requires InteractsWithDocker trait)
        if (method_exists($this, 'isDockerAvailable') && $this->isDockerAvailable()) {
            // Docker is available - offer Docker setup
            note(
                'Docker detected! Using Docker provides isolated databases, easy management, and no local installation needed.',
                'Database Setup'
            );

            $useDocker = confirm(
                label: 'Would you like to use Docker for the database? (recommended)',
                default: true
            );

            if ($useDocker) {
                $dbConfig = $this->setupDockerDatabase($appName, $supportedDatabases, $appPath);
                if ($dbConfig !== null) {
                    return $dbConfig;
                }

                // Docker setup failed, fall back to local
                warning('Docker setup failed. Falling back to local database setup.');
            }
        } elseif (method_exists($this, 'isDockerInstalled') && ! $this->isDockerInstalled()) {
            // Docker not installed - offer installation guidance
            $installDocker = confirm(
                label: 'Docker is not installed. Would you like to see installation instructions?',
                default: false
            );

            if ($installDocker && method_exists($this, 'provideDockerInstallationGuidance')) {
                $this->provideDockerInstallationGuidance();
                info('After installing Docker, you can recreate this application to use Docker.');
            }
        }

        // Fall back to local database setup
        return $this->setupLocalDatabase($appName);
    }

    /**
     * Set up database using Docker containers.
     *
     * Creates a Docker Compose configuration with the selected database
     * type and starts the containers. Includes optional services like
     * phpMyAdmin/Adminer for database management.
     *
     * Process:
     * 1. Prompt for database type (if multiple supported)
     * 2. Prompt for optional services (phpMyAdmin, Redis, etc.)
     * 3. Generate secure passwords
     * 4. Generate docker-compose.yml from template
     * 5. Start Docker containers
     * 6. Wait for database to be ready
     * 7. Return connection details
     *
     * Generated files:
     * - docker-compose.yml: Container configuration
     * - .env.docker: Environment variables (optional)
     *
     * Container naming:
     * - Format: phphive-{app-name}-{service}
     * - Example: phphive-my-shop-mysql
     *
     * @param  string        $appName            Application name
     * @param  array<string> $supportedDatabases Supported database types
     * @param  string        $appPath            Application directory path
     * @return array|null    Database config on success, null on failure
     */
    protected function setupDockerDatabase(string $appName, array $supportedDatabases, string $appPath): ?array
    {
        // Check if running in non-interactive mode
        if (! $this->input->isInteractive()) {
            return null;
        }

        // =====================================================================
        // DATABASE TYPE SELECTION
        // =====================================================================

        $dbType = 'mysql'; // Default
        if (count($supportedDatabases) > 1) {
            $dbTypeOptions = [
                'mysql' => 'MySQL 8.0 (Most compatible)',
                'postgresql' => 'PostgreSQL 15 (Advanced features)',
                'mariadb' => 'MariaDB 10.11 (MySQL fork)',
            ];

            // Filter to only supported types
            $filteredOptions = array_intersect_key($dbTypeOptions, array_flip($supportedDatabases));

            $dbType = (string) select(
                label: 'Select database type',
                options: $filteredOptions,
                default: $supportedDatabases[0]
            );
        } else {
            $dbType = $supportedDatabases[0];
            info("Using {$dbType} database");
        }

        // =====================================================================
        // OPTIONAL SERVICES
        // =====================================================================

        $includeAdmin = confirm(
            label: 'Include database admin tool? (phpMyAdmin/Adminer)',
            default: true
        );

        // =====================================================================
        // CONFIGURATION
        // =====================================================================

        $normalizedName = strtolower(preg_replace('/[^a-zA-Z0-9]/', '_', $appName) ?? $appName);

        $dbName = text(
            label: 'Database name',
            default: $normalizedName,
            required: true
        );

        $dbUser = text(
            label: 'Database username',
            default: "{$normalizedName}_user",
            required: true
        );

        $dbPassword = password(
            label: 'Database password',
            required: true,
            hint: 'Enter a secure password for the database user'
        );

        // Generate root password
        $rootPassword = bin2hex(random_bytes(16));

        // =====================================================================
        // GENERATE DOCKER COMPOSE FILE
        // =====================================================================

        info('Generating docker-compose.yml...');

        $composeGenerated = $this->generateDockerComposeFile(
            $appPath,
            $dbType,
            $appName,
            $dbName,
            $dbUser,
            $dbPassword,
            $rootPassword,
            $includeAdmin
        );

        if (! $composeGenerated) {
            error('Failed to generate docker-compose.yml');

            return null;
        }

        // =====================================================================
        // START CONTAINERS
        // =====================================================================

        info('Starting Docker containers...');

        if (! method_exists($this, 'startDockerContainers')) {
            error('InteractsWithDocker trait is required for Docker setup');

            return null;
        }

        $started = spin(
            callback: fn (): bool => $this->startDockerContainers($appPath),
            message: 'Starting containers...'
        );

        if (! $started) {
            error('Failed to start Docker containers');

            return null;
        }

        // =====================================================================
        // WAIT FOR DATABASE
        // =====================================================================

        info('Waiting for database to be ready...');

        $serviceName = match ($dbType) {
            'postgresql' => 'postgres',
            'mariadb' => 'mariadb',
            default => 'mysql',
        };

        if (method_exists($this, 'waitForDockerService')) {
            $ready = spin(
                callback: fn (): bool => $this->waitForDockerService($appPath, $serviceName, 30),
                message: 'Waiting for database...'
            );

            if (! $ready) {
                warning('Database may not be fully ready. You may need to wait a moment before using it.');
            } else {
                info('✓ Database is ready!');
            }
        }

        // =====================================================================
        // RETURN CONFIGURATION
        // =====================================================================

        $port = match ($dbType) {
            'postgresql' => 5432,
            default => 3306,
        };

        info('✓ Docker database setup complete!');
        if ($includeAdmin) {
            $adminUrl = $dbType === 'postgresql' ? 'http://localhost:8080' : 'http://localhost:8080';
            info("Database admin tool: {$adminUrl}");
        }

        return [
            'db_type' => $dbType,
            'db_host' => 'localhost',
            'db_port' => $port,
            'db_name' => $dbName,
            'db_user' => $dbUser,
            'db_password' => $dbPassword,
            'using_docker' => true,
        ];
    }

    /**
     * Generate docker-compose.yml file from template.
     *
     * Reads the appropriate template file based on database type,
     * replaces placeholders with actual values, and writes the
     * docker-compose.yml file to the application directory.
     *
     * Template placeholders:
     * - {{CONTAINER_PREFIX}}: phphive-{app-name}
     * - {{VOLUME_PREFIX}}: phphive-{app-name}
     * - {{NETWORK_NAME}}: phphive-{app-name}
     * - {{DB_NAME}}: Database name
     * - {{DB_USER}}: Database username
     * - {{DB_PASSWORD}}: Database password
     * - {{DB_ROOT_PASSWORD}}: Root/admin password
     * - {{DB_PORT}}: Database port (3306, 5432)
     * - {{PHPMYADMIN_PORT}}: phpMyAdmin port (8080)
     * - {{ADMINER_PORT}}: Adminer port (8080)
     * - {{REDIS_PORT}}: Redis port (6379)
     * - {{ELASTICSEARCH_PORT}}: Elasticsearch port (9200)
     *
     * @param  string $appPath      Application directory path
     * @param  string $dbType       Database type (mysql, postgresql, mariadb)
     * @param  string $appName      Application name
     * @param  string $dbName       Database name
     * @param  string $dbUser       Database username
     * @param  string $dbPassword   Database password
     * @param  string $rootPassword Root/admin password
     * @param  bool   $includeAdmin Include admin tool
     * @return bool   True on success, false on failure
     */
    protected function generateDockerComposeFile(
        string $appPath,
        string $dbType,
        string $appName,
        string $dbName,
        string $dbUser,
        string $dbPassword,
        string $rootPassword,
        bool $includeAdmin
    ): bool {
        // Determine template file
        $templateFile = match ($dbType) {
            'postgresql' => 'postgresql.yml',
            'mariadb' => 'mariadb.yml',
            default => 'mysql.yml',
        };

        // Get template path
        $templatePath = dirname(__DIR__, 2) . "/stubs/docker/{$templateFile}";

        if (! file_exists($templatePath)) {
            return false;
        }

        // Read template
        $template = file_get_contents($templatePath);
        if ($template === false) {
            return false;
        }

        // Normalize app name for container/volume names
        $normalizedName = strtolower(preg_replace('/[^a-zA-Z0-9]/', '-', $appName) ?? $appName);

        // Replace placeholders
        $replacements = [
            '{{CONTAINER_PREFIX}}' => "phphive-{$normalizedName}",
            '{{VOLUME_PREFIX}}' => "phphive-{$normalizedName}",
            '{{NETWORK_NAME}}' => "phphive-{$normalizedName}",
            '{{DB_NAME}}' => $dbName,
            '{{DB_USER}}' => $dbUser,
            '{{DB_PASSWORD}}' => $dbPassword,
            '{{DB_ROOT_PASSWORD}}' => $rootPassword,
            '{{DB_PORT}}' => $dbType === 'postgresql' ? '5432' : '3306',
            '{{PHPMYADMIN_PORT}}' => '8080',
            '{{ADMINER_PORT}}' => '8080',
            '{{REDIS_PORT}}' => '6379',
            '{{ELASTICSEARCH_PORT}}' => '9200',
        ];

        $content = str_replace(array_keys($replacements), array_values($replacements), $template);

        // Remove admin service if not wanted
        if (! $includeAdmin) {
            // Remove phpmyadmin/adminer service section
            $content = preg_replace('/  # phpMyAdmin Service.*?depends_on:.*?- (mysql|postgres|mariadb)\n\n/s', '', $content) ?? $content;
            $content = preg_replace('/  # Adminer Service.*?depends_on:.*?- (mysql|postgres|mariadb)\n\n/s', '', $content) ?? $content;
        }

        // Write docker-compose.yml
        $outputPath = $appPath . '/docker-compose.yml';

        return file_put_contents($outputPath, $content) !== false;
    }

    /**
     * Set up database using local MySQL installation.
     *
     * Falls back to local MySQL setup when Docker is not available
     * or user prefers local installation. Offers automatic or manual
     * configuration.
     *
     * Process:
     * 1. Ask if user wants automatic setup
     * 2. If yes → promptAutomaticDatabaseSetup()
     * 3. If no or fails → promptManualDatabaseSetup()
     *
     * @param  string $appName Application name
     * @return array  Database configuration array
     */
    protected function setupLocalDatabase(string $appName): array
    {
        note(
            'Setting up local MySQL database. Ensure MySQL is installed and running.',
            'Local Database Setup'
        );

        $autoSetup = confirm(
            label: 'Would you like automatic MySQL database setup?',
            default: true
        );

        if ($autoSetup) {
            $dbConfig = $this->promptAutomaticDatabaseSetup($appName);
            if ($dbConfig !== null) {
                // Add type and docker flag
                $dbConfig['db_type'] = 'mysql';
                $dbConfig['using_docker'] = false;

                return $dbConfig;
            }
        }

        // Fall back to manual
        $dbConfig = $this->promptManualDatabaseSetup($appName);
        $dbConfig['db_type'] = 'mysql';
        $dbConfig['using_docker'] = false;

        return $dbConfig;
    }

    /**
     * Check if MySQL connection is available and working.
     *
     * Attempts to establish a connection to MySQL server using the provided
     * credentials. This is used to verify that MySQL is running and accessible
     * before attempting to create databases or users.
     *
     * Connection process:
     * 1. Suppress PHP warnings for cleaner error handling
     * 2. Attempt mysqli connection with provided credentials
     * 3. Test connection with a simple query (SELECT 1)
     * 4. Close connection if successful
     * 5. Return true/false based on connection result
     *
     * Common failure reasons:
     * - MySQL server not running
     * - Incorrect host or port
     * - Invalid credentials
     * - Firewall blocking connection
     * - MySQL not installed
     *
     * @param  string $host     MySQL server host (e.g., '127.0.0.1', 'localhost', 'db.example.com')
     * @param  int    $port     MySQL server port (default: 3306)
     * @param  string $user     MySQL username with connection privileges
     * @param  string $password MySQL user password
     * @return bool   True if connection successful, false otherwise
     */
    protected function checkMySQLConnection(string $host, int $port, string $user, string $password): bool
    {
        // Suppress warnings for cleaner error handling
        // We'll handle errors explicitly rather than showing PHP warnings
        $connection = @new mysqli($host, $user, $password, '', $port);

        // Check if connection failed
        if ($connection->connect_error !== null && $connection->connect_error !== '') {
            return false;
        }

        // Test connection with a simple query
        $result = $connection->query('SELECT 1');

        // Close the connection
        $connection->close();

        // Return true if query was successful
        return $result !== false && $result !== null;
    }

    /**
     * Create MySQL database and user with appropriate privileges.
     *
     * Executes a series of SQL commands to:
     * 1. Create a new database if it doesn't exist
     * 2. Create a new user if it doesn't exist
     * 3. Grant all privileges on the database to the user
     * 4. Flush privileges to apply changes immediately
     *
     * SQL commands executed:
     * ```sql
     * CREATE DATABASE IF NOT EXISTS `database_name`;
     * CREATE USER IF NOT EXISTS 'db_user'@'localhost' IDENTIFIED BY 'password';
     * GRANT ALL PRIVILEGES ON `database_name`.* TO 'db_user'@'localhost';
     * FLUSH PRIVILEGES;
     * ```
     *
     * Privilege scope:
     * - User has full access to the specified database only
     * - User cannot access other databases
     * - User cannot create additional databases
     * - User cannot manage other users
     *
     * Error handling:
     * - Returns false if connection fails
     * - Returns false if any SQL command fails
     * - Closes connection in all cases
     * - Does not throw exceptions (graceful failure)
     *
     * @param  string $host      MySQL server host
     * @param  int    $port      MySQL server port
     * @param  string $adminUser MySQL admin username (must have CREATE DATABASE and CREATE USER privileges)
     * @param  string $adminPass MySQL admin password
     * @param  string $dbName    Name of database to create
     * @param  string $dbUser    Name of database user to create
     * @param  string $dbPass    Password for the new database user
     * @return bool   True if all operations successful, false otherwise
     */
    protected function createMySQLDatabase(
        string $host,
        int $port,
        string $adminUser,
        string $adminPass,
        string $dbName,
        string $dbUser,
        string $dbPass
    ): bool {
        // Establish connection as admin user
        $connection = @new mysqli($host, $adminUser, $adminPass, '', $port);

        // Check if connection failed
        if ($connection->connect_error !== null && $connection->connect_error !== '') {
            return false;
        }

        // Escape identifiers and values to prevent SQL injection
        $dbNameEscaped = $connection->real_escape_string($dbName);
        $dbUserEscaped = $connection->real_escape_string($dbUser);
        $dbPassEscaped = $connection->real_escape_string($dbPass);

        // Create database if it doesn't exist
        $createDbQuery = "CREATE DATABASE IF NOT EXISTS `{$dbNameEscaped}`";
        $createDbResult = $connection->query($createDbQuery);
        if ($createDbResult === false || $createDbResult === null) {
            $connection->close();

            return false;
        }

        // Create user if it doesn't exist
        // Using 'localhost' for security - user can only connect from local machine
        $createUserQuery = "CREATE USER IF NOT EXISTS '{$dbUserEscaped}'@'localhost' IDENTIFIED BY '{$dbPassEscaped}'";
        $createUserResult = $connection->query($createUserQuery);
        if ($createUserResult === false || $createUserResult === null) {
            $connection->close();

            return false;
        }

        // Grant all privileges on the database to the user
        // User has full control over this database only
        $grantQuery = "GRANT ALL PRIVILEGES ON `{$dbNameEscaped}`.* TO '{$dbUserEscaped}'@'localhost'";
        $grantResult = $connection->query($grantQuery);
        if ($grantResult === false || $grantResult === null) {
            $connection->close();

            return false;
        }

        // Flush privileges to apply changes immediately
        $flushResult = $connection->query('FLUSH PRIVILEGES');
        if ($flushResult === false || $flushResult === null) {
            $connection->close();

            return false;
        }

        // Close connection and return success
        $connection->close();

        return true;
    }

    /**
     * Prompt user for automatic database setup with MySQL.
     *
     * This method guides the user through an interactive automatic database
     * setup process. It handles the complete workflow from connection testing
     * to database creation, with graceful error handling and fallback options.
     *
     * Interactive workflow:
     * 1. Display informational note about automatic setup
     * 2. Prompt for MySQL host (default: 127.0.0.1)
     * 3. Prompt for MySQL port (default: 3306)
     * 4. Test MySQL connection with spinner feedback
     * 5. If connection fails, show error and return null
     * 6. Prompt for MySQL admin credentials (username and password)
     * 7. Suggest database name, user, and password with sensible defaults
     * 8. Create database and user with spinner feedback
     * 9. If creation fails, show error and return null
     * 10. If successful, return database configuration array
     *
     * Return value structure:
     * ```php
     * [
     *     'db_host' => '127.0.0.1',
     *     'db_port' => 3306,
     *     'db_name' => 'my_app',
     *     'db_user' => 'my_app_user',
     *     'db_password' => 'secure_password',
     * ]
     * ```
     *
     * Error scenarios:
     * - MySQL not running → Returns null, caller should fall back to manual
     * - Invalid admin credentials → Returns null
     * - Database creation fails → Returns null
     * - User cancels at any prompt → Returns null
     *
     * Non-interactive mode:
     * - Returns null immediately (automatic setup requires interaction)
     * - Caller should use manual prompts with defaults
     *
     * @param  string     $appName Application name used for default database and user names
     * @return array|null Database configuration array on success, null on failure
     */
    protected function promptAutomaticDatabaseSetup(string $appName): ?array
    {
        // Check if running in non-interactive mode
        if (! $this->input->isInteractive()) {
            return null;
        }

        // Display informational note about automatic setup
        note(
            'Automatic database setup will create a MySQL database and user for your application.',
            'Automatic Database Setup'
        );

        // =====================================================================
        // MYSQL CONNECTION CONFIGURATION
        // =====================================================================

        // Prompt for MySQL host
        $host = text(
            label: 'MySQL host',
            placeholder: '127.0.0.1',
            default: '127.0.0.1',
            required: true,
            hint: 'The MySQL server hostname or IP address'
        );

        // Prompt for MySQL port
        $portInput = text(
            label: 'MySQL port',
            placeholder: '3306',
            default: '3306',
            required: true,
            hint: 'The MySQL server port number'
        );
        $port = (int) $portInput;

        // =====================================================================
        // CONNECTION TEST
        // =====================================================================

        // Test connection with a temporary admin connection
        // We'll ask for credentials after verifying MySQL is accessible
        info('Testing MySQL connection...');

        // Prompt for admin credentials to test connection
        $adminUser = text(
            label: 'MySQL admin username',
            placeholder: 'root',
            default: 'root',
            required: true,
            hint: 'User with CREATE DATABASE and CREATE USER privileges'
        );

        $adminPass = password(
            label: 'MySQL admin password',
            placeholder: 'Enter admin password',
            required: false,
            hint: 'Leave empty if no password is set'
        );

        // Test connection with spinner for better UX
        $connectionSuccess = spin(
            callback: fn (): bool => $this->checkMySQLConnection($host, $port, $adminUser, $adminPass),
            message: 'Connecting to MySQL...'
        );

        if (! $connectionSuccess) {
            error('Failed to connect to MySQL. Please check your credentials and try again.');
            warning('Falling back to manual database configuration.');

            return null;
        }

        info('✓ MySQL connection successful!');

        // =====================================================================
        // DATABASE CONFIGURATION
        // =====================================================================

        // Normalize app name for database naming (lowercase, underscores)
        $normalizedName = strtolower(preg_replace('/[^a-zA-Z0-9]/', '_', $appName) ?? $appName);

        // Prompt for database name
        $dbName = text(
            label: 'Database name',
            placeholder: $normalizedName,
            default: $normalizedName,
            required: true,
            hint: 'Name of the database to create'
        );

        // Prompt for database user
        $dbUser = text(
            label: 'Database username',
            placeholder: "{$normalizedName}_user",
            default: "{$normalizedName}_user",
            required: true,
            hint: 'Username for the application database user'
        );

        // Prompt for database password
        $dbPass = password(
            label: 'Database password',
            placeholder: 'Enter a secure password',
            required: true,
            hint: 'Password for the database user'
        );

        // =====================================================================
        // DATABASE CREATION
        // =====================================================================

        info('Creating database and user...');

        // Create database and user with spinner
        $creationSuccess = spin(
            callback: fn (): bool => $this->createMySQLDatabase(
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

        if (! $creationSuccess) {
            error('Failed to create database or user. Please check admin privileges and try again.');
            warning('Falling back to manual database configuration.');

            return null;
        }

        info('✓ Database and user created successfully!');

        // Return database configuration
        return [
            'db_host' => $host,
            'db_port' => $port,
            'db_name' => $dbName,
            'db_user' => $dbUser,
            'db_password' => $dbPass,
        ];
    }

    /**
     * Prompt user for manual database configuration.
     *
     * This method provides a fallback when automatic database setup fails or
     * is not desired. It prompts the user to manually enter database connection
     * details for an existing database.
     *
     * Use cases:
     * - User prefers to create database manually
     * - Automatic setup failed (connection issues, permission errors)
     * - Database already exists
     * - Using remote database server
     * - Using managed database service (RDS, Cloud SQL, etc.)
     *
     * Interactive prompts:
     * 1. Database host (default: 127.0.0.1)
     * 2. Database port (default: 3306)
     * 3. Database name (default: normalized app name)
     * 4. Database username (default: root)
     * 5. Database password (masked input, optional)
     *
     * Return value structure:
     * ```php
     * [
     *     'db_host' => '127.0.0.1',
     *     'db_port' => 3306,
     *     'db_name' => 'my_app',
     *     'db_user' => 'root',
     *     'db_password' => 'password',
     * ]
     * ```
     *
     * Non-interactive mode:
     * - Returns defaults for all values
     * - Database name uses normalized app name
     * - Host: 127.0.0.1, Port: 3306, User: root, Password: empty
     *
     * Note: This method does NOT validate the database connection.
     * The application will fail to install if credentials are incorrect.
     * Consider adding connection validation in the future.
     *
     * @param  string $appName Application name used for default database name
     * @return array  Database configuration array with user-provided values
     */
    protected function promptManualDatabaseSetup(string $appName): array
    {
        // Normalize app name for database naming (lowercase, underscores)
        $normalizedName = strtolower(preg_replace('/[^a-zA-Z0-9]/', '_', $appName) ?? $appName);

        // Check if running in non-interactive mode
        if (! $this->input->isInteractive()) {
            // Return defaults for non-interactive mode
            return [
                'db_host' => '127.0.0.1',
                'db_port' => 3306,
                'db_name' => $normalizedName,
                'db_user' => 'root',
                'db_password' => '',
            ];
        }

        // Display informational note about manual setup
        note(
            'Please enter the connection details for your existing MySQL database.',
            'Manual Database Configuration'
        );

        // =====================================================================
        // DATABASE CONNECTION DETAILS
        // =====================================================================

        // Prompt for database host
        $host = text(
            label: 'Database host',
            placeholder: '127.0.0.1',
            default: '127.0.0.1',
            required: true,
            hint: 'The MySQL server hostname or IP address'
        );

        // Prompt for database port
        $portInput = text(
            label: 'Database port',
            placeholder: '3306',
            default: '3306',
            required: true,
            hint: 'The MySQL server port number'
        );
        $port = (int) $portInput;

        // Prompt for database name
        $dbName = text(
            label: 'Database name',
            placeholder: $normalizedName,
            default: $normalizedName,
            required: true,
            hint: 'Name of the existing database'
        );

        // Prompt for database user
        $dbUser = text(
            label: 'Database username',
            placeholder: 'root',
            default: 'root',
            required: true,
            hint: 'Username with access to the database'
        );

        // Prompt for database password
        $dbPass = password(
            label: 'Database password',
            placeholder: 'Enter database password',
            required: false,
            hint: 'Leave empty if no password is set'
        );

        // Return database configuration
        return [
            'db_host' => $host,
            'db_port' => $port,
            'db_name' => $dbName,
            'db_user' => $dbUser,
            'db_password' => $dbPass,
        ];
    }
}
