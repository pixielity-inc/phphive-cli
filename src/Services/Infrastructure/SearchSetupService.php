<?php

declare(strict_types=1);

namespace PhpHive\Cli\Services\Infrastructure;

use Exception;
use PhpHive\Cli\DTOs\Infrastructure\SearchConfig;
use PhpHive\Cli\Enums\SearchEngine;
use PhpHive\Cli\Support\Docker;
use PhpHive\Cli\Support\Filesystem;
use PhpHive\Cli\Support\Process;
use Pixielity\StubGenerator\Exceptions\StubNotFoundException;
use Pixielity\StubGenerator\Facades\Stub;

/**
 * Search Engine Setup Service.
 *
 * Handles setup and configuration for various search engines including
 * Elasticsearch, Meilisearch, and OpenSearch. Supports both Docker-based
 * and local installations.
 */
final readonly class SearchSetupService
{
    /**
     * Create a new search setup service instance.
     *
     * @param Process $process Process service
     */
    /**
     * Create a new search setup service instance.
     *
     * @param Docker     $docker     Docker service
     * @param Process    $process    Process service
     * @param Filesystem $filesystem Filesystem service
     */
    public function __construct(
        private Docker $docker,
        private Process $process,
        private Filesystem $filesystem,
    ) {}

    /**
     * Setup search engine with Docker-first approach.
     *
     * @param  SearchConfig $searchConfig Search configuration
     * @param  string       $appPath      Application path
     * @return SearchConfig Updated configuration
     */
    public function setup(SearchConfig $searchConfig, string $appPath): SearchConfig
    {
        if ($searchConfig->usingDocker) {
            $dockerConfig = $this->setupDocker($searchConfig, $appPath);
            if ($dockerConfig instanceof SearchConfig) {
                return $dockerConfig;
            }
        }

        return $this->setupLocal($searchConfig);
    }

    /**
     * Create a new instance using static factory pattern.
     *
     * @param Docker     $docker     Docker service
     * @param Process    $process    Process service
     * @param Filesystem $filesystem Filesystem service
     */
    /**
     * Create a new instance using static factory pattern.
     *
     * @return self A new SearchSetupService instance with dependencies
     */
    public static function make(): self
    {
        return new self(
            docker: Docker::make(),
            process: Process::make(),
            filesystem: Filesystem::make(),
        );
    }

    /**
     * Setup search engine using Docker.
     *
     * @param  SearchConfig      $searchConfig Search configuration
     * @param  string            $appPath      Application path
     * @return SearchConfig|null Configuration on success, null on failure
     */
    public function setupDocker(SearchConfig $searchConfig, string $appPath): ?SearchConfig
    {
        return match ($searchConfig->engine) {
            SearchEngine::ELASTICSEARCH => $this->setupDockerElasticsearch($searchConfig, $appPath),
            SearchEngine::MEILISEARCH => $this->setupDockerMeilisearch($searchConfig, $appPath),
            SearchEngine::OPENSEARCH => $this->setupDockerOpenSearch($searchConfig, $appPath),
            default => null,
        };
    }

    /**
     * Setup search engine locally.
     *
     * @param  SearchConfig $searchConfig Search configuration
     * @return SearchConfig Updated configuration
     */
    public function setupLocal(SearchConfig $searchConfig): SearchConfig
    {
        // For local setup, return the config as-is
        // The calling code should have already prompted for connection details
        return $searchConfig;
    }

    /**
     * Setup Elasticsearch using Docker.
     *
     * @param SearchConfig $searchConfig Search configuration
     * @param string       $appPath      Application path
     */
    private function setupDockerElasticsearch(SearchConfig $searchConfig, string $appPath): ?SearchConfig
    {
        try {
            $this->generateElasticsearchDockerCompose($searchConfig, $appPath);
            $this->startDockerContainers($appPath);

            return $searchConfig;
        } catch (Exception) {
            return null;
        }
    }

    /**
     * Setup Meilisearch using Docker.
     *
     * @param SearchConfig $searchConfig Search configuration
     * @param string       $appPath      Application path
     */
    private function setupDockerMeilisearch(SearchConfig $searchConfig, string $appPath): ?SearchConfig
    {
        try {
            $this->generateMeilisearchDockerCompose($searchConfig, $appPath);
            $this->startDockerContainers($appPath);

            return $searchConfig;
        } catch (Exception) {
            return null;
        }
    }

    /**
     * Setup OpenSearch using Docker.
     *
     * @param SearchConfig $searchConfig Search configuration
     * @param string       $appPath      Application path
     */
    private function setupDockerOpenSearch(SearchConfig $searchConfig, string $appPath): ?SearchConfig
    {
        try {
            $this->generateOpenSearchDockerCompose($searchConfig, $appPath);
            $this->startDockerContainers($appPath);

            return $searchConfig;
        } catch (Exception) {
            return null;
        }
    }

    /**
     * Generate Elasticsearch docker-compose configuration.
     *
     * @param SearchConfig $searchConfig Search configuration
     * @param string       $appPath      Application path
     */
    private function generateElasticsearchDockerCompose(SearchConfig $searchConfig, string $appPath): bool
    {
        try {
            Stub::setBasePath(dirname(__DIR__, 2) . '/stubs');

            Stub::create('docker/elasticsearch.yml', [
                'container_prefix' => 'phphive',
                'volume_prefix' => 'phphive',
                'network_name' => 'phphive',
                'elasticsearch_password' => $searchConfig->apiKey ?? '',
                'elasticsearch_port' => (string) $searchConfig->port,
                'kibana_port' => '5601',
            ])->saveTo($appPath, 'docker-compose.yml');

            return true;
        } catch (StubNotFoundException) {
            return false;
        }
    }

    /**
     * Generate Meilisearch docker-compose configuration.
     *
     * @param SearchConfig $searchConfig Search configuration
     * @param string       $appPath      Application path
     */
    private function generateMeilisearchDockerCompose(SearchConfig $searchConfig, string $appPath): bool
    {
        try {
            Stub::setBasePath(dirname(__DIR__, 2) . '/stubs');

            Stub::create('docker/meilisearch.yml', [
                'container_prefix' => 'phphive',
                'volume_prefix' => 'phphive',
                'network_name' => 'phphive',
                'meilisearch_master_key' => $searchConfig->apiKey ?? '',
                'meilisearch_port' => (string) $searchConfig->port,
            ])->saveTo($appPath, 'docker-compose.yml');

            return true;
        } catch (StubNotFoundException) {
            return false;
        }
    }

    /**
     * Generate OpenSearch docker-compose configuration.
     *
     * @param SearchConfig $searchConfig Search configuration
     * @param string       $appPath      Application path
     */
    private function generateOpenSearchDockerCompose(SearchConfig $searchConfig, string $appPath): bool
    {
        try {
            Stub::setBasePath(dirname(__DIR__, 2) . '/stubs');

            Stub::create('docker/opensearch.yml', [
                'container_prefix' => 'phphive',
                'volume_prefix' => 'phphive',
                'network_name' => 'phphive',
                'opensearch_password' => $searchConfig->apiKey ?? '',
                'opensearch_port' => (string) $searchConfig->port,
            ])->saveTo($appPath, 'docker-compose.yml');

            return true;
        } catch (StubNotFoundException) {
            return false;
        }
    }

    /**
     * Start Docker containers.
     *
     * @param string $appPath Application path
     */
    private function startDockerContainers(string $appPath): bool
    {
        return $this->process->succeeds(['docker', 'compose', 'up', '-d'], $appPath);
    }
}
