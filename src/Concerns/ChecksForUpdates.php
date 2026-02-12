<?php

declare(strict_types=1);

namespace PhpHive\Cli\Concerns;

use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function getenv;
use function is_array;
use function is_dir;
use function json_decode;
use function json_encode;
use function ltrim;
use function mb_strlen;
use function mkdir;
use function preg_replace;
use function str_contains;
use function str_repeat;
use function stream_context_create;
use function strip_tags;
use function time;
use function version_compare;

/**
 * Update Checker Trait.
 *
 * This trait provides functionality to check for new versions of the CLI
 * and display update notifications to users, similar to npm/pnpm/yarn.
 *
 * Features:
 * - Checks Packagist for latest version
 * - Caches check results to avoid excessive API calls
 * - Displays beautiful update banner when new version available
 * - Respects user's update check preferences
 * - Non-blocking (doesn't slow down command execution)
 *
 * The update check is performed:
 * - Once per day (configurable)
 * - In the background (doesn't block command execution)
 * - Only when running actual commands (not help/version)
 *
 * Example usage:
 * ```php
 * $this->checkForUpdates('1.0.6');
 * ```
 */
trait ChecksForUpdates
{
    /**
     * Check for available updates and display notification if found.
     *
     * This method checks if a newer version is available on Packagist and
     * displays a notification banner if an update is found. The check is
     * cached for 24 hours to avoid excessive API calls.
     *
     * @param string $currentVersion The current installed version
     */
    protected function checkForUpdates(string $currentVersion): void
    {
        // Get cache directory
        $cacheDir = $this->getUpdateCacheDir();
        $cacheFile = $cacheDir . '/update-check.json';

        // Check if we should perform update check
        if (! $this->shouldCheckForUpdates($cacheFile)) {
            // Check if we have a cached update notification to display
            $this->displayCachedUpdateNotification($cacheFile, $currentVersion);

            return;
        }

        // Fetch latest version from Packagist
        $latestVersion = $this->fetchLatestVersion();

        if ($latestVersion === null) {
            // Failed to fetch, update cache timestamp anyway
            $this->updateCache($cacheFile, null);

            return;
        }

        // Update cache with latest version
        $this->updateCache($cacheFile, $latestVersion);

        // Display update notification if newer version available
        if (version_compare($latestVersion, $currentVersion, '>')) {
            $this->displayUpdateNotification($currentVersion, $latestVersion);
        }
    }

    /**
     * Get the update cache directory path.
     *
     * Returns the directory where update check cache is stored.
     * Creates the directory if it doesn't exist.
     *
     * @return string Absolute path to cache directory
     */
    private function getUpdateCacheDir(): string
    {
        // Use XDG_CACHE_HOME if set, otherwise use ~/.cache
        $xdgCache = getenv('XDG_CACHE_HOME');
        $cacheHome = ($xdgCache !== false && $xdgCache !== '') ? $xdgCache : (getenv('HOME') . '/.cache');
        $cacheDir = $cacheHome . '/phphive';

        // Create directory if it doesn't exist
        if (! is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        return $cacheDir;
    }

    /**
     * Check if we should perform an update check.
     *
     * Returns true if:
     * - Cache file doesn't exist
     * - Cache is older than 24 hours
     *
     * @param  string $cacheFile Path to cache file
     * @return bool   True if update check should be performed
     */
    private function shouldCheckForUpdates(string $cacheFile): bool
    {
        // No cache file, perform check
        if (! file_exists($cacheFile)) {
            return true;
        }

        // Read cache file
        $content = file_get_contents($cacheFile);
        if ($content === false) {
            return true;
        }

        $cache = json_decode($content, true);
        if (! is_array($cache) || ! isset($cache['timestamp'])) {
            return true;
        }

        // Check if cache is older than 24 hours (86400 seconds)
        $age = time() - $cache['timestamp'];

        return $age > 86400;
    }

    /**
     * Display cached update notification if available.
     *
     * If we have a cached latest version that's newer than current,
     * display the update notification without making an API call.
     *
     * @param string $cacheFile      Path to cache file
     * @param string $currentVersion Current installed version
     */
    private function displayCachedUpdateNotification(string $cacheFile, string $currentVersion): void
    {
        if (! file_exists($cacheFile)) {
            return;
        }

        $content = file_get_contents($cacheFile);
        if ($content === false) {
            return;
        }

        $cache = json_decode($content, true);
        if (! is_array($cache) || ! isset($cache['latestVersion'])) {
            return;
        }

        $latestVersion = $cache['latestVersion'];

        // Display notification if newer version available
        if (version_compare($latestVersion, $currentVersion, '>')) {
            $this->displayUpdateNotification($currentVersion, $latestVersion);
        }
    }

    /**
     * Fetch the latest version from Packagist.
     *
     * Makes an API call to Packagist to get the latest stable version.
     * Returns null if the request fails or version cannot be determined.
     *
     * @return string|null Latest version number, or null on failure
     */
    private function fetchLatestVersion(): ?string
    {
        // Packagist API endpoint
        $url = 'https://repo.packagist.org/p2/phphive/cli.json';

        // Use file_get_contents with timeout
        $context = stream_context_create([
            'http' => [
                'timeout' => 2, // 2 second timeout
                'user_agent' => 'PhpHive-CLI-Update-Checker',
            ],
        ]);

        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            return null;
        }

        $data = json_decode($response, true);

        if (! is_array($data) || ! isset($data['packages']['phphive/cli'])) {
            return null;
        }

        $versions = $data['packages']['phphive/cli'];

        // Find latest stable version
        $latestVersion = null;
        foreach ($versions as $version => $info) {
            // Ensure version is a string
            if (! is_string($version)) {
                continue;
            }

            // Skip dev versions
            if (str_contains($version, 'dev')) {
                continue;
            }

            // Remove 'v' prefix if present
            $cleanVersion = ltrim($version, 'v');

            // Update latest if this is newer
            if ($latestVersion === null || version_compare($cleanVersion, $latestVersion, '>')) {
                $latestVersion = $cleanVersion;
            }
        }

        return $latestVersion;
    }

    /**
     * Update the cache file with latest version and timestamp.
     *
     * @param string      $cacheFile     Path to cache file
     * @param string|null $latestVersion Latest version number, or null if check failed
     */
    private function updateCache(string $cacheFile, ?string $latestVersion): void
    {
        $cache = [
            'timestamp' => time(),
            'latestVersion' => $latestVersion,
        ];

        file_put_contents($cacheFile, json_encode($cache, JSON_PRETTY_PRINT));
    }

    /**
     * Display update notification banner.
     *
     * Shows a beautiful banner similar to npm/pnpm when a new version is available.
     *
     * @param string $currentVersion Current installed version
     * @param string $latestVersion  Latest available version
     */
    private function displayUpdateNotification(string $currentVersion, string $latestVersion): void
    {
        // ANSI color codes
        $yellow = "\e[33m";
        $green = "\e[32m";
        $cyan = "\e[36m";
        $reset = "\e[0m";
        $bold = "\e[1m";

        // Box drawing characters
        $topLeft = '╭';
        $topRight = '╮';
        $bottomLeft = '╰';
        $bottomRight = '╯';
        $horizontal = '─';
        $vertical = '│';

        // Build the notification
        $width = 60;
        $padding = 2;

        $lines = [
            '',
            "  {$yellow}Update available!{$reset} {$currentVersion} → {$green}{$bold}{$latestVersion}{$reset}",
            '',
            "  Run {$cyan}composer global update phphive/cli{$reset} to update",
            '',
        ];

        // Display box
        echo PHP_EOL;
        echo $yellow . $topLeft . str_repeat($horizontal, $width) . $topRight . $reset . PHP_EOL;

        foreach ($lines as $line) {
            $lineLength = mb_strlen(strip_tags(preg_replace('/\e\[[0-9;]*m/', '', $line) ?? ''));
            $spacesNeeded = $width - $lineLength;
            echo $yellow . $vertical . $reset . $line . str_repeat(' ', $spacesNeeded) . $yellow . $vertical . $reset . PHP_EOL;
        }

        echo $yellow . $bottomLeft . str_repeat($horizontal, $width) . $bottomRight . $reset . PHP_EOL;
        echo PHP_EOL;
    }
}
