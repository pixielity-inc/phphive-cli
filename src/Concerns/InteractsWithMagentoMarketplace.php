<?php

declare(strict_types=1);

namespace PhpHive\Cli\Concerns;

use RuntimeException;

/**
 * Trait InteractsWithMagentoMarketplace.
 *
 * Provides unified logic for handling Magento Marketplace authentication.
 * This trait manages Magento repository configuration and authentication keys
 * required to access packages from repo.magento.com.
 *
 * This trait is designed for use in AppType classes (not PackageType classes)
 * as it requires access to input/output and prompt methods.
 *
 * Features:
 * - Configures Magento Composer repository
 * - Handles authentication keys (public/private)
 * - Checks for environment variables
 * - Prompts for credentials when needed
 * - Validates credentials format
 *
 * Environment Variables:
 * - COMPOSER_AUTH_MAGENTO_PUBLIC_KEY: Magento public key (username)
 * - COMPOSER_AUTH_MAGENTO_PRIVATE_KEY: Magento private key (password)
 *
 * Usage:
 * ```php
 * $keys = $this->getMagentoAuthKeys();
 * $this->configureMagentoRepository('/path/to/app');
 * ```
 */
trait InteractsWithMagentoMarketplace
{
    /**
     * Get Magento authentication keys.
     *
     * Retrieves Magento Marketplace authentication keys from:
     * 1. Command-line options (--magento-public-key, --magento-private-key)
     * 2. Environment variables (COMPOSER_AUTH_MAGENTO_PUBLIC_KEY, COMPOSER_AUTH_MAGENTO_PRIVATE_KEY)
     * 3. Interactive prompts (if not in non-interactive mode)
     *
     * @param  bool                  $required Whether keys are required (throws exception if missing)
     * @return array<string, string> Array with 'public_key' and 'private_key'
     *
     * @throws RuntimeException If keys are required but not provided
     */
    protected function getMagentoAuthKeys(bool $required = true): array
    {
        // Check command-line options first
        $publicKey = $this->input->getOption('magento-public-key');
        $privateKey = $this->input->getOption('magento-private-key');

        // If not provided via options, check environment variables
        if ($publicKey === null) {
            $envPublicKey = getenv('COMPOSER_AUTH_MAGENTO_PUBLIC_KEY');
            $publicKey = $envPublicKey !== false ? $envPublicKey : null;
        }

        if ($privateKey === null) {
            $envPrivateKey = getenv('COMPOSER_AUTH_MAGENTO_PRIVATE_KEY');
            $privateKey = $envPrivateKey !== false ? $envPrivateKey : null;
        }

        // If still not provided and interactive mode, prompt user
        $noInteraction = $this->input->getOption('no-interaction');
        if (($publicKey === null || $privateKey === null) && $noInteraction !== true) {
            $this->note(
                'Get your authentication keys from: https://marketplace.magento.com/customer/accessKeys/',
                'Magento Marketplace Authentication'
            );

            if ($publicKey === null) {
                $publicKey = $this->text(
                    label: 'Magento Public Key (username)',
                    placeholder: 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
                    required: $required
                );
            }

            if ($privateKey === null) {
                $privateKey = $this->password(
                    label: 'Magento Private Key (password)',
                    placeholder: 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
                    required: $required
                );
            }
        }

        // Validate that keys are provided if required
        if ($required && ($publicKey === null || $publicKey === '' || $privateKey === null || $privateKey === '')) {
            throw new RuntimeException(
                'Magento authentication keys are required. ' .
                'Get your keys from: https://marketplace.magento.com/customer/accessKeys/ ' .
                'or set COMPOSER_AUTH_MAGENTO_PUBLIC_KEY and COMPOSER_AUTH_MAGENTO_PRIVATE_KEY environment variables.'
            );
        }

        return [
            'public_key' => $publicKey ?? '',
            'private_key' => $privateKey ?? '',
        ];
    }

    /**
     * Configure Magento Composer repository.
     *
     * Adds the Magento Composer repository to the project's composer.json.
     * This is required to install Magento packages from repo.magento.com.
     *
     * @param string $directory Working directory containing composer.json
     */
    protected function configureMagentoRepository(string $directory): void
    {
        // Add Magento repository using Composer service
        $this->composer()->run(
            $directory,
            ['config', 'repositories.magento', 'composer', 'https://repo.magento.com/']
        );
    }

    /**
     * Set Magento authentication in Composer.
     *
     * Configures Composer authentication for repo.magento.com using the
     * provided public and private keys. This stores credentials in auth.json.
     *
     * @param string $directory  Working directory containing composer.json
     * @param string $publicKey  Magento public key (username)
     * @param string $privateKey Magento private key (password)
     */
    protected function setMagentoAuth(string $directory, string $publicKey, string $privateKey): void
    {
        // Set authentication for repo.magento.com using Composer service
        $this->composer()->run(
            $directory,
            ['config', 'http-basic.repo.magento.com', $publicKey, $privateKey]
        );
    }

    /**
     * Get COMPOSER_AUTH environment variable for Magento.
     *
     * Generates a COMPOSER_AUTH JSON string that can be used as an environment
     * variable for Composer commands. This is useful for CI/CD pipelines and
     * non-interactive installations.
     *
     * @param  string $publicKey  Magento public key (username)
     * @param  string $privateKey Magento private key (password)
     * @return string JSON string for COMPOSER_AUTH
     *
     * @throws RuntimeException If JSON encoding fails
     */
    protected function getMagentoComposerAuth(string $publicKey, string $privateKey): string
    {
        $json = json_encode([
            'http-basic' => [
                'repo.magento.com' => [
                    'username' => $publicKey,
                    'password' => $privateKey,
                ],
            ],
        ], JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            throw new RuntimeException('Failed to encode Magento authentication to JSON');
        }

        return $json;
    }

    /**
     * Setup Magento Marketplace authentication.
     *
     * Complete setup process for Magento Marketplace authentication:
     * 1. Get authentication keys (from options, env vars, or prompts)
     * 2. Configure Magento repository
     * 3. Set authentication credentials
     *
     * @param  string                $directory Working directory containing composer.json
     * @param  bool                  $required  Whether keys are required
     * @return array<string, string> Array with 'public_key' and 'private_key'
     */
    protected function setupMagentoMarketplace(string $directory, bool $required = true): array
    {
        // Get authentication keys
        $keys = $this->getMagentoAuthKeys($required);

        // Only configure if keys are provided
        if ($keys['public_key'] !== '' && $keys['private_key'] !== '') {
            // Configure repository
            $this->configureMagentoRepository($directory);

            // Set authentication
            $this->setMagentoAuth($directory, $keys['public_key'], $keys['private_key']);
        }

        return $keys;
    }
}
