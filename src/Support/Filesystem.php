<?php

declare(strict_types=1);

namespace MonoPhp\Cli\Support;

use function array_diff;
use function array_filter;
use function array_merge;
use function array_values;
use function dirname;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function filemtime;
use function glob;
use function is_dir;
use function is_file;
use function is_link;
use function mkdir;
use function rmdir;

use RuntimeException;

use function scandir;
use function unlink;

/**
 * Filesystem Operations.
 *
 * Provides a clean abstraction over PHP filesystem functions with improved
 * error handling and type safety. This class makes filesystem operations
 * more testable and provides consistent error messages.
 *
 * All methods throw exceptions with descriptive messages on failure rather
 * than returning false, making error handling more explicit.
 *
 * Example usage:
 * ```php
 * $fs = Filesystem::make();
 * if ($fs->exists('/path/to/file')) {
 *     $content = $fs->read('/path/to/file');
 * }
 * ```
 */
final class Filesystem
{
    /**
     * Default directory permissions.
     */
    public const int DEFAULT_DIRECTORY_MODE = 0755;

    /**
     * Create a new Filesystem instance (static factory).
     */
    public static function make(): self
    {
        return new self();
    }

    /**
     * Check if a file or directory exists.
     *
     * @param  string $path Path to check
     * @return bool   True if exists, false otherwise
     */
    public function exists(string $path): bool
    {
        return file_exists($path);
    }

    /**
     * Check if path is a file.
     *
     * @param  string $path Path to check
     * @return bool   True if is a file, false otherwise
     */
    public function isFile(string $path): bool
    {
        return is_file($path);
    }

    /**
     * Check if path is a directory.
     *
     * @param  string $path Path to check
     * @return bool   True if is a directory, false otherwise
     */
    public function isDirectory(string $path): bool
    {
        return is_dir($path);
    }

    /**
     * Read entire file contents.
     *
     * @param  string $path Path to file
     * @return string File contents
     *
     * @throws RuntimeException If file cannot be read
     */
    public function read(string $path): string
    {
        if (! $this->exists($path)) {
            throw new RuntimeException("File not found: {$path}");
        }

        if (! $this->isFile($path)) {
            throw new RuntimeException("Path is not a file: {$path}");
        }

        $content = @file_get_contents($path);

        if ($content === false) {
            throw new RuntimeException("Failed to read file: {$path}");
        }

        return $content;
    }

    /**
     * Write content to file.
     *
     * Creates parent directories if they don't exist.
     *
     * @param string $path    Path to file
     * @param string $content Content to write
     *
     * @throws RuntimeException If file cannot be written
     */
    public function write(string $path, string $content): void
    {
        // Create parent directory if it doesn't exist
        $directory = dirname($path);
        if (! $this->exists($directory)) {
            $this->makeDirectory($directory, 0755, true);
        }

        $result = @file_put_contents($path, $content);

        if ($result === false) {
            throw new RuntimeException("Failed to write file: {$path}");
        }
    }

    /**
     * Create a directory.
     *
     * @param string $path      Path to directory
     * @param int    $mode      Permissions mode (default: 0755)
     * @param bool   $recursive Create parent directories (default: false)
     *
     * @throws RuntimeException If directory cannot be created
     */
    public function makeDirectory(string $path, int $mode = self::DEFAULT_DIRECTORY_MODE, bool $recursive = false): void
    {
        if ($this->exists($path)) {
            return;
        }

        $result = @mkdir($path, $mode, $recursive);

        if (! $result) {
            throw new RuntimeException("Failed to create directory: {$path}");
        }
    }

    /**
     * Delete a file.
     *
     * @param string $path Path to file
     *
     * @throws RuntimeException If file cannot be deleted
     */
    public function delete(string $path): void
    {
        if (! $this->exists($path)) {
            return;
        }

        if (! $this->isFile($path)) {
            throw new RuntimeException("Path is not a file: {$path}");
        }

        $result = @unlink($path);

        if (! $result) {
            throw new RuntimeException("Failed to delete file: {$path}");
        }
    }

    /**
     * Delete a directory recursively.
     *
     * @param string $path Path to directory
     *
     * @throws RuntimeException If directory cannot be deleted
     */
    public function deleteDirectory(string $path): void
    {
        if (! $this->exists($path)) {
            return;
        }

        if (! $this->isDirectory($path)) {
            throw new RuntimeException("Path is not a directory: {$path}");
        }

        $this->removeDirectoryRecursive($path);
    }

    /**
     * List files in a directory.
     *
     * @param  string             $path Path to directory
     * @return array<int, string> Array of filenames
     *
     * @throws RuntimeException If directory cannot be read
     */
    public function files(string $path): array
    {
        if (! $this->exists($path)) {
            throw new RuntimeException("Directory not found: {$path}");
        }

        if (! $this->isDirectory($path)) {
            throw new RuntimeException("Path is not a directory: {$path}");
        }

        $files = @scandir($path);

        if ($files === false) {
            throw new RuntimeException("Failed to read directory: {$path}");
        }

        // Filter out . and .. and return only files
        return array_values(array_filter($files, fn (string $file): bool => $file !== '.' && $file !== '..' && $this->isFile($path . DIRECTORY_SEPARATOR . $file)));
    }

    /**
     * List directories in a directory.
     *
     * @param  string             $path Path to directory
     * @return array<int, string> Array of directory names
     *
     * @throws RuntimeException If directory cannot be read
     */
    public function directories(string $path): array
    {
        if (! $this->exists($path)) {
            throw new RuntimeException("Directory not found: {$path}");
        }

        if (! $this->isDirectory($path)) {
            throw new RuntimeException("Path is not a directory: {$path}");
        }

        $items = @scandir($path);

        if ($items === false) {
            throw new RuntimeException("Failed to read directory: {$path}");
        }

        // Filter out . and .. and return only directories
        return array_values(array_filter($items, fn (string $item): bool => $item !== '.' && $item !== '..' && $this->isDirectory($path . DIRECTORY_SEPARATOR . $item)));
    }

    /**
     * Find pathnames matching a pattern.
     *
     * Wrapper around PHP's glob() function with error handling.
     *
     * @param  string             $pattern Pattern to match (e.g., "/path/*.txt")
     * @param  int                $flags   Optional flags (GLOB_* constants)
     * @return array<int, string> Array of matching paths
     *
     * @throws RuntimeException If glob fails
     */
    public function glob(string $pattern, int $flags = 0): array
    {
        $result = @glob($pattern, $flags);

        if ($result === false) {
            throw new RuntimeException("Failed to glob pattern: {$pattern}");
        }

        return $result;
    }

    /**
     * Get file modification time.
     *
     * @param  string $path Path to file
     * @return int    Unix timestamp of last modification
     *
     * @throws RuntimeException If file doesn't exist or time cannot be read
     */
    public function lastModified(string $path): int
    {
        if (! $this->exists($path)) {
            throw new RuntimeException("File not found: {$path}");
        }

        $mtime = @filemtime($path);

        if ($mtime === false) {
            throw new RuntimeException("Failed to get modification time: {$path}");
        }

        return $mtime;
    }

    /**
     * Get all files in a directory recursively.
     *
     * Returns relative paths from the given directory.
     *
     * @param  string             $directory Directory to scan
     * @return array<int, string> Array of relative file paths
     *
     * @throws RuntimeException If directory cannot be read
     */
    public function allFiles(string $directory): array
    {
        if (! $this->exists($directory)) {
            return [];
        }

        if (! $this->isDirectory($directory)) {
            throw new RuntimeException("Path is not a directory: {$directory}");
        }

        return $this->scanDirectoryRecursive($directory, '');
    }

    /**
     * Recursively remove directory and contents.
     *
     * Handles symlinks properly by unlinking them instead of recursing into them.
     * This prevents issues when vendor directories contain symlinked packages.
     *
     * @param string $path Path to directory
     */
    private function removeDirectoryRecursive(string $path): void
    {
        $items = @scandir($path);

        if ($items === false) {
            throw new RuntimeException("Failed to read directory: {$path}");
        }

        $files = array_diff($items, ['.', '..']);

        foreach ($files as $file) {
            $filePath = $path . DIRECTORY_SEPARATOR . $file;

            // Check if it's a symlink first - unlink it without recursing
            if (is_link($filePath)) {
                @unlink($filePath);

                continue;
            }

            // Handle regular directories and files
            if ($this->isDirectory($filePath)) {
                $this->removeDirectoryRecursive($filePath);
            } else {
                $this->delete($filePath);
            }
        }

        @rmdir($path);
    }

    /**
     * Recursively scan directory for files.
     *
     * @param  string             $basePath     Base directory path
     * @param  string             $relativePath Current relative path
     * @return array<int, string> Array of relative file paths
     */
    private function scanDirectoryRecursive(string $basePath, string $relativePath): array
    {
        $files = [];
        $currentPath = $basePath . ($relativePath !== '' && $relativePath !== '0' ? DIRECTORY_SEPARATOR . $relativePath : '');

        $items = @scandir($currentPath);
        if ($items === false) {
            return $files;
        }

        foreach ($items as $item) {
            if ($item === '.') {
                continue;
            }
            if ($item === '..') {
                continue;
            }
            $itemPath = $currentPath . DIRECTORY_SEPARATOR . $item;
            $itemRelativePath = $relativePath !== '' && $relativePath !== '0' ? $relativePath . DIRECTORY_SEPARATOR . $item : $item;

            // Skip symlinks to avoid infinite loops
            if (is_link($itemPath)) {
                continue;
            }

            if ($this->isDirectory($itemPath)) {
                // Recursively scan subdirectory
                $subFiles = $this->scanDirectoryRecursive($basePath, $itemRelativePath);
                $files = array_merge($files, $subFiles);
            } else {
                // Add file to list
                $files[] = $itemRelativePath;
            }
        }

        return $files;
    }
}
