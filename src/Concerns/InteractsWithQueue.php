<?php

declare(strict_types=1);

namespace PhpHive\Cli\Concerns;

use PhpHive\Cli\Contracts\AppTypeInterface;
use PhpHive\Cli\Support\Filesystem;
use PhpHive\Cli\Support\Process;
use RuntimeException;

/**
 * Queue Interaction Trait.
 *
 * This trait provides comprehensive queue setup functionality for application
 * types that require message queue configuration. It supports multiple queue
 * backends with both Docker-based and local setups.
 *
 * Supported queue backends:
 * - Redis: Lightweight, fast, good for simple queues
 * - RabbitMQ: Full-featured message broker with routing, exchanges
 * - Amazon SQS: Managed cloud queue service (requires AWS credentials)
 *
 * Key features:
 * - Docker-first approach for Redis and RabbitMQ
 * - Multiple queue backend support
 * - Automatic Docker Compose integration
 * - Container health checking
 * - Secure credential generation
 * - Local fallback for non-Docker setups
 * - AWS SQS configuration support
 *
 * Docker-first workflow:
 * 1. Prompt user to select queue backend
 * 2. If Docker available, offer Docker setup
 * 3. Generate docker-compose.yml section
 * 4. Start containers
 * 5. Wait for health check
 * 6. Return connection details
 * 7. If Docker unavailable, fall back to local/cloud setup
 *
 * Example usage:
 * ```php
 * use PhpHive\Cli\Concerns\InteractsWithQueue;
 *
 * class MyAppType extends AbstractAppType
 * {
 *     use InteractsWithQueue;
 *
 *     public function collectConfiguration($input, $output): array
 *     {
 *         $queueConfig = $this->setupQueue('my-app', '/path/to/app');
 *         return $queueConfig;
 *     }
 * }
 * ```
 *
 * @see AbstractAppType
 * @see InteractsWithDocker
 * @see InteractsWithPrompts
 */
trait InteractsWithQueue
{
    /**
     * Get the Process service instance.
     */
    abstract protected function process(): Process;

    /**
     * Get the Filesystem service instance.
     */
    abstract protected function filesystem(): Filesystem;

    /**
     * Orchestrate queue setup with Docker-first approach.
     *
     * This is the main entry point for queue setup. It prompts the user
     * to select a queue backend and sets it up accordingly.
     *
     * Supported backends:
     * - none: No queue (synchronous processing)
     * - redis: Redis-based queue (lightweight, fast)
     * - rabbitmq: RabbitMQ message broker (full-featured)
     * - sqs: Amazon SQS (managed cloud service)
     *
     * Return value structure:
     * ```php
     * [
     *     'queue_driver' => 'rabbitmq',
     *     'queue_host' => 'localhost',
     *     'queue_port' => 5672,
     *     'queue_user' => 'guest',
     *     'queue_password' => '********',
     *     'queue_vhost' => '/',
     *     'using_docker' => true,
     * ]
     * ```
     *
     * @param  string $appName Application name for defaults
     * @param  string $appPath Absolute path to application directory
     * @return array  Queue configuration array
     */
    protected function setupQueue(string $appName, string $appPath): array
    {
        // Prompt for queue backend selection
        $queueDriver = $this->select(
            label: 'Queue backend',
            options: [
                'none' => 'None (Synchronous processing)',
                'redis' => 'Redis (Lightweight, fast)',
                'rabbitmq' => 'RabbitMQ (Full-featured message broker)',
                'sqs' => 'Amazon SQS (Managed cloud service)',
            ],
            default: 'none'
        );

        // If no queue selected, return empty config
        if ($queueDriver === 'none') {
            return ['queue_driver' => 'sync'];
        }

        // Setup based on selected driver
        return match ($queueDriver) {
            'redis' => $this->setupRedisQueue($appName, $appPath),
            'rabbitmq' => $this->setupRabbitMQQueue($appName, $appPath),
            'sqs' => $this->setupSQSQueue($appName),
            default => ['queue_driver' => 'sync'],
        };
    }

    /**
     * Set up Redis as queue backend.
     *
     * If Redis is already configured for caching, reuses the same instance.
     * Otherwise, sets up a new Redis instance specifically for queues.
     *
     * @param  string $appName Application name
     * @param  string $appPath Application directory path
     * @return array  Queue configuration
     */
    protected function setupRedisQueue(string $appName, string $appPath): array
    {
        $this->info('Setting up Redis for queue...');

        // Check if Redis is already configured (from cache setup)
        // If so, we can reuse it for queues
        $useExisting = $this->confirm(
            label: 'Redis is already configured. Use it for queues too?',
            default: true
        );

        if ($useExisting) {
            return [
                'queue_driver' => 'redis',
                'queue_connection' => 'default',
            ];
        }

        // Setup new Redis instance for queues
        if ($this->isDockerAvailable()) {
            $useDocker = $this->confirm(
                label: 'Use Docker for Redis queue?',
                default: true
            );

            if ($useDocker) {
                return $this->setupDockerRedisQueue($appName, $appPath);
            }
        }

        // Local Redis setup
        return $this->setupLocalRedisQueue($appName);
    }

    /**
     * Set up RabbitMQ as queue backend.
     *
     * RabbitMQ is a full-featured message broker with support for:
     * - Multiple exchanges and queues
     * - Message routing
     * - Dead letter queues
     * - Message persistence
     * - Management UI
     *
     * @param  string $appName Application name
     * @param  string $appPath Application directory path
     * @return array  Queue configuration
     */
    protected function setupRabbitMQQueue(string $appName, string $appPath): array
    {
        $this->info('Setting up RabbitMQ...');

        // Check if Docker is available
        if ($this->isDockerAvailable()) {
            $useDocker = $this->confirm(
                label: 'Use Docker for RabbitMQ?',
                default: true
            );

            if ($useDocker) {
                return $this->setupDockerRabbitMQ($appName, $appPath);
            }
        }

        // Local RabbitMQ setup
        return $this->setupLocalRabbitMQ($appName);
    }

    /**
     * Set up Amazon SQS as queue backend.
     *
     * SQS is a managed cloud queue service that requires AWS credentials.
     * No Docker setup needed as it's a cloud service.
     *
     * @param  string $appName Application name
     * @return array  Queue configuration
     */
    protected function setupSQSQueue(string $appName): array
    {
        $this->info('Setting up Amazon SQS...');

        $this->note(
            'Amazon SQS requires AWS credentials. You can configure them in your .env file.',
            'AWS Configuration'
        );

        // Prompt for AWS configuration
        $region = $this->text(
            label: 'AWS Region',
            placeholder: 'us-east-1',
            default: 'us-east-1',
            required: true,
            hint: 'AWS region where your SQS queues are located'
        );

        $queuePrefix = $this->text(
            label: 'Queue prefix (optional)',
            placeholder: $appName,
            default: $appName,
            required: false,
            hint: 'Prefix for queue names'
        );

        $this->note(
            "Configure AWS credentials in your .env file:\n\n" .
            "AWS_ACCESS_KEY_ID=your-access-key\n" .
            "AWS_SECRET_ACCESS_KEY=your-secret-key\n" .
            "AWS_DEFAULT_REGION={$region}\n" .
            "SQS_PREFIX={$queuePrefix}",
            'Environment Variables'
        );

        return [
            'queue_driver' => 'sqs',
            'queue_region' => $region,
            'queue_prefix' => $queuePrefix,
            AppTypeInterface::CONFIG_USING_DOCKER => false,
        ];
    }

    /**
     * Set up RabbitMQ using Docker.
     *
     * Creates a Docker Compose configuration with RabbitMQ server
     * including the management UI.
     *
     * RabbitMQ configuration:
     * - Image: rabbitmq:3-management-alpine
     * - AMQP Port: 5672 (message broker)
     * - Management UI Port: 15672 (web interface)
     * - Default credentials: guest/guest (configurable)
     *
     * @param  string $appName Application name
     * @param  string $appPath Application directory path
     * @return array  Queue configuration
     */
    protected function setupDockerRabbitMQ(string $appName, string $appPath): array
    {
        // Generate credentials
        $user = $this->text(
            label: 'RabbitMQ username',
            default: 'guest',
            required: true
        );

        $password = $this->password(
            label: 'RabbitMQ password',
            required: true,
            hint: 'Enter a secure password'
        );

        $vhost = $this->text(
            label: 'RabbitMQ virtual host',
            default: '/',
            required: true,
            hint: 'Virtual host for isolation'
        );

        // Generate docker-compose.yml
        $this->info('Generating docker-compose.yml for RabbitMQ...');

        $composeGenerated = $this->generateRabbitMQDockerComposeFile(
            $appPath,
            $appName,
            $user,
            $password,
            $vhost
        );

        if (! $composeGenerated) {
            $this->error('Failed to generate docker-compose.yml');

            return $this->setupLocalRabbitMQ($appName);
        }

        // Start containers
        $this->info('Starting RabbitMQ container...');

        $started = $this->spin(
            callback: fn (): bool => $this->startDockerContainers($appPath),
            message: 'Starting RabbitMQ...'
        );

        if (! $started) {
            $this->error('Failed to start RabbitMQ container');

            return $this->setupLocalRabbitMQ($appName);
        }

        // Wait for RabbitMQ to be ready
        $this->info('Waiting for RabbitMQ to be ready...');

        $ready = $this->spin(
            callback: fn (): bool => $this->waitForDockerService($appPath, 'rabbitmq', 30),
            message: 'Waiting for RabbitMQ...'
        );

        if (! $ready) {
            $this->warning('RabbitMQ may not be fully ready. You may need to wait a moment.');
        } else {
            $this->info('✓ RabbitMQ is ready!');
        }

        $this->info('✓ RabbitMQ setup complete!');
        $this->info('Management UI: http://localhost:15672');
        $this->info("Login with username: {$user}");

        return [
            'queue_driver' => 'rabbitmq',
            'queue_host' => 'localhost',
            'queue_port' => 5672,
            'queue_user' => $user,
            'queue_password' => $password,
            'queue_vhost' => $vhost,
            'rabbitmq_management_port' => 15672,
            AppTypeInterface::CONFIG_USING_DOCKER => true,
        ];
    }

    /**
     * Generate docker-compose.yml file for RabbitMQ.
     *
     * @param  string $appPath  Application directory path
     * @param  string $appName  Application name
     * @param  string $user     RabbitMQ username
     * @param  string $password RabbitMQ password
     * @param  string $vhost    RabbitMQ virtual host
     * @return bool   True on success
     */
    protected function generateRabbitMQDockerComposeFile(
        string $appPath,
        string $appName,
        string $user,
        string $password,
        string $vhost
    ): bool {
        $template = $this->getRabbitMQDockerComposeTemplate();

        // Normalize app name
        $normalizedName = strtolower(preg_replace('/[^a-zA-Z0-9]/', '-', $appName) ?? $appName);

        // Replace placeholders
        $replacements = [
            '{{CONTAINER_PREFIX}}' => "phphive-{$normalizedName}",
            '{{VOLUME_PREFIX}}' => "phphive-{$normalizedName}",
            '{{NETWORK_NAME}}' => "phphive-{$normalizedName}",
            '{{RABBITMQ_USER}}' => $user,
            '{{RABBITMQ_PASSWORD}}' => $password,
            '{{RABBITMQ_VHOST}}' => $vhost,
            '{{RABBITMQ_PORT}}' => '5672',
            '{{RABBITMQ_MANAGEMENT_PORT}}' => '15672',
        ];

        $content = str_replace(array_keys($replacements), array_values($replacements), $template);

        // Write or append to docker-compose.yml
        $outputPath = $appPath . '/docker-compose.yml';

        try {
            if ($this->filesystem()->exists($outputPath)) {
                $existingContent = $this->filesystem()->read($outputPath);

                if (str_contains($existingContent, 'rabbitmq:')) {
                    $this->warning('RabbitMQ service already exists in docker-compose.yml');

                    return true;
                }

                $content = $existingContent . "\n" . $content;
            }

            $this->filesystem()->write($outputPath, $content);

            return true;
        } catch (RuntimeException) {
            return false;
        }
    }

    /**
     * Get RabbitMQ docker-compose template.
     */
    protected function getRabbitMQDockerComposeTemplate(): string
    {
        // Try to read from stub file first
        $templatePath = dirname(__DIR__, 2) . '/stubs/docker/rabbitmq.yml';

        if ($this->filesystem()->exists($templatePath)) {
            try {
                return $this->filesystem()->read($templatePath);
            } catch (RuntimeException) {
                // Fall through to inline template
            }
        }

        // Fallback to inline template
        return <<<'YAML'
  # RabbitMQ Message Broker
  rabbitmq:
    image: rabbitmq:3-management-alpine
    container_name: {{CONTAINER_PREFIX}}-rabbitmq
    ports:
      - "{{RABBITMQ_PORT}}:5672"           # AMQP port
      - "{{RABBITMQ_MANAGEMENT_PORT}}:15672"  # Management UI
    environment:
      RABBITMQ_DEFAULT_USER: {{RABBITMQ_USER}}
      RABBITMQ_DEFAULT_PASS: {{RABBITMQ_PASSWORD}}
      RABBITMQ_DEFAULT_VHOST: {{RABBITMQ_VHOST}}
    volumes:
      - {{VOLUME_PREFIX}}-rabbitmq-data:/var/lib/rabbitmq
    networks:
      - {{NETWORK_NAME}}
    healthcheck:
      test: ["CMD", "rabbitmq-diagnostics", "ping"]
      interval: 10s
      timeout: 5s
      retries: 5

volumes:
  {{VOLUME_PREFIX}}-rabbitmq-data:
    driver: local

networks:
  {{NETWORK_NAME}}:
    driver: bridge

YAML;
    }

    /**
     * Set up Docker Redis for queue.
     */
    protected function setupDockerRedisQueue(string $appName, string $appPath): array
    {
        // Reuse Redis setup from InteractsWithRedis trait
        $redisConfig = $this->setupRedis($appName, $appPath);

        return [
            'queue_driver' => 'redis',
            'queue_connection' => 'default',
            'redis_host' => $redisConfig['redis_host'] ?? 'localhost',
            'redis_port' => $redisConfig['redis_port'] ?? 6379,
            'redis_password' => $redisConfig['redis_password'] ?? '',
            AppTypeInterface::CONFIG_USING_DOCKER => $redisConfig[AppTypeInterface::CONFIG_USING_DOCKER] ?? false,
        ];
    }

    /**
     * Set up local Redis for queue.
     */
    protected function setupLocalRedisQueue(string $appName): array
    {
        $this->note(
            'Using local Redis for queue. Ensure Redis is installed and running.',
            'Local Redis Queue'
        );

        return [
            'queue_driver' => 'redis',
            'queue_connection' => 'default',
            'redis_host' => 'localhost',
            'redis_port' => 6379,
            'redis_password' => '',
            AppTypeInterface::CONFIG_USING_DOCKER => false,
        ];
    }

    /**
     * Set up local RabbitMQ.
     */
    protected function setupLocalRabbitMQ(string $appName): array
    {
        $this->note(
            'Setting up local RabbitMQ. Ensure RabbitMQ is installed and running.',
            'Local RabbitMQ Setup'
        );

        $host = $this->text(
            label: 'RabbitMQ host',
            default: 'localhost',
            required: true
        );

        $port = (int) $this->text(
            label: 'RabbitMQ port',
            default: '5672',
            required: true
        );

        $user = $this->text(
            label: 'RabbitMQ username',
            default: 'guest',
            required: true
        );

        $password = $this->password(
            label: 'RabbitMQ password',
            required: true
        );

        $vhost = $this->text(
            label: 'RabbitMQ virtual host',
            default: '/',
            required: true
        );

        return [
            'queue_driver' => 'rabbitmq',
            'queue_host' => $host,
            'queue_port' => $port,
            'queue_user' => $user,
            'queue_password' => $password,
            'queue_vhost' => $vhost,
            AppTypeInterface::CONFIG_USING_DOCKER => false,
        ];
    }
}
