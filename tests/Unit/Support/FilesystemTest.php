<?php

declare(strict_types=1);

namespace MonoPhp\Cli\Tests\Unit\Support;

use function file_get_contents;
use function file_put_contents;
use function is_dir;
use function mkdir;

use MonoPhp\Cli\Support\Filesystem;
use MonoPhp\Cli\Tests\TestCase;
use RuntimeException;

use function sys_get_temp_dir;
use function touch;
use function uniqid;

/**
 * Filesystem Test.
 *
 * Tests for the Filesystem utility class that provides a clean abstraction
 * over PHP's filesystem functions. Verifies file operations, directory management,
 * error handling, and recursive operations.
 */
final class FilesystemTest extends TestCase
{
    /**
     * The filesystem instance being tested.
     */
    private Filesystem $fs;

    /**
     * Temporary directory for test files.
     */
    private string $tempDir;

    /**
     * Set up the test environment before each test.
     *
     * Creates a filesystem instance and a unique temporary directory
     * for isolated test file operations.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Create filesystem instance
        $this->fs = Filesystem::make();

        // Create unique temporary directory for this test
        $this->tempDir = sys_get_temp_dir() . '/cli-test-' . uniqid();
        mkdir($this->tempDir);
    }

    /**
     * Clean up the test environment after each test.
     *
     * Removes the temporary directory and all its contents to ensure
     * tests don't leave artifacts on the filesystem.
     */
    protected function tearDown(): void
    {
        // Clean up temporary directory if it exists
        if (is_dir($this->tempDir)) {
            $this->fs->deleteDirectory($this->tempDir);
        }

        parent::tearDown();
    }

    /**
     * Test that exists() correctly checks file and directory existence.
     *
     * Verifies that the method returns false for non-existent paths
     * and true after the file is created.
     */
    public function test_exists_checks_file_existence(): void
    {
        $file = $this->tempDir . '/test.txt';

        // Assert file doesn't exist initially
        $this->assertFalse($this->fs->exists($file));

        // Create the file
        touch($file);

        // Assert file now exists
        $this->assertTrue($this->fs->exists($file));
    }

    /**
     * Test that isFile() correctly identifies files.
     *
     * Verifies that the method returns true for files and false for directories.
     */
    public function test_is_file_checks_if_path_is_file(): void
    {
        $file = $this->tempDir . '/test.txt';
        touch($file);

        // Assert file is identified as a file
        $this->assertTrue($this->fs->isFile($file));

        // Assert directory is not identified as a file
        $this->assertFalse($this->fs->isFile($this->tempDir));
    }

    /**
     * Test that isDirectory() correctly identifies directories.
     *
     * Verifies that the method returns true for directories and false for files.
     */
    public function test_is_directory_checks_if_path_is_directory(): void
    {
        $dir = $this->tempDir . '/subdir';
        mkdir($dir);

        // Assert directory is identified as a directory
        $this->assertTrue($this->fs->isDirectory($dir));

        // Assert non-existent file is not identified as a directory
        $this->assertFalse($this->fs->isDirectory($this->tempDir . '/test.txt'));
    }

    /**
     * Test that read() returns file contents.
     *
     * Verifies that the method correctly reads and returns file content.
     */
    public function test_read_returns_file_contents(): void
    {
        $file = $this->tempDir . '/test.txt';
        file_put_contents($file, 'test content');

        // Read the file
        $content = $this->fs->read($file);

        // Assert content matches what was written
        $this->assertEquals('test content', $content);
    }

    /**
     * Test that read() throws exception for non-existent files.
     *
     * Verifies that attempting to read a non-existent file throws
     * a RuntimeException with appropriate message.
     */
    public function test_read_throws_exception_for_nonexistent_file(): void
    {
        // Expect RuntimeException
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('File not found');

        // Attempt to read non-existent file
        $this->fs->read($this->tempDir . '/nonexistent.txt');
    }

    /**
     * Test that write() creates a file with content.
     *
     * Verifies that the method creates a new file and writes content to it.
     */
    public function test_write_creates_file_with_content(): void
    {
        $file = $this->tempDir . '/test.txt';

        // Write content to file
        $this->fs->write($file, 'test content');

        // Assert file exists
        $this->assertTrue($this->fs->exists($file));

        // Assert content was written correctly
        $this->assertEquals('test content', file_get_contents($file));
    }

    /**
     * Test that write() creates parent directories automatically.
     *
     * Verifies that the method creates any missing parent directories
     * when writing to a nested path.
     */
    public function test_write_creates_parent_directories(): void
    {
        $file = $this->tempDir . '/sub/dir/test.txt';

        // Write to nested path
        $this->fs->write($file, 'test content');

        // Assert file was created
        $this->assertTrue($this->fs->exists($file));

        // Assert parent directories were created
        $this->assertTrue($this->fs->isDirectory($this->tempDir . '/sub/dir'));
    }

    /**
     * Test that makeDirectory() creates a directory.
     *
     * Verifies that the method creates a new directory with default permissions.
     */
    public function test_make_directory_creates_directory(): void
    {
        $dir = $this->tempDir . '/newdir';

        // Create directory
        $this->fs->makeDirectory($dir);

        // Assert directory was created
        $this->assertTrue($this->fs->isDirectory($dir));
    }

    /**
     * Test that makeDirectory() with recursive flag creates nested directories.
     *
     * Verifies that the method can create multiple levels of directories
     * when the recursive flag is enabled.
     */
    public function test_make_directory_with_recursive_creates_nested_directories(): void
    {
        $dir = $this->tempDir . '/a/b/c';

        // Create nested directories recursively
        $this->fs->makeDirectory($dir, 0755, true);

        // Assert all levels were created
        $this->assertTrue($this->fs->isDirectory($dir));
    }

    /**
     * Test that delete() removes a file.
     *
     * Verifies that the method successfully deletes an existing file.
     */
    public function test_delete_removes_file(): void
    {
        $file = $this->tempDir . '/test.txt';
        touch($file);

        // Delete the file
        $this->fs->delete($file);

        // Assert file no longer exists
        $this->assertFalse($this->fs->exists($file));
    }

    /**
     * Test that deleteDirectory() removes a directory recursively.
     *
     * Verifies that the method removes a directory and all its contents,
     * including nested files and subdirectories.
     */
    public function test_delete_directory_removes_directory_recursively(): void
    {
        $dir = $this->tempDir . '/subdir';
        mkdir($dir);
        touch($dir . '/file.txt');

        // Delete directory recursively
        $this->fs->deleteDirectory($dir);

        // Assert directory no longer exists
        $this->assertFalse($this->fs->exists($dir));
    }

    /**
     * Test that files() lists only files in a directory.
     *
     * Verifies that the method returns an array of filenames,
     * excluding directories and special entries (. and ..).
     */
    public function test_files_lists_files_in_directory(): void
    {
        // Create test files and directory
        touch($this->tempDir . '/file1.txt');
        touch($this->tempDir . '/file2.txt');
        mkdir($this->tempDir . '/subdir');

        // Get list of files
        $files = $this->fs->files($this->tempDir);

        // Assert correct number of files
        $this->assertCount(2, $files);

        // Assert files are in the list
        $this->assertContains('file1.txt', $files);
        $this->assertContains('file2.txt', $files);
    }

    /**
     * Test that directories() lists only directories.
     *
     * Verifies that the method returns an array of directory names,
     * excluding files and special entries (. and ..).
     */
    public function test_directories_lists_directories(): void
    {
        // Create test directories and file
        mkdir($this->tempDir . '/dir1');
        mkdir($this->tempDir . '/dir2');
        touch($this->tempDir . '/file.txt');

        // Get list of directories
        $dirs = $this->fs->directories($this->tempDir);

        // Assert correct number of directories
        $this->assertCount(2, $dirs);

        // Assert directories are in the list
        $this->assertContains('dir1', $dirs);
        $this->assertContains('dir2', $dirs);
    }

    /**
     * Test that glob() finds files matching a pattern.
     *
     * Verifies that the method returns files matching the specified
     * glob pattern.
     */
    public function test_glob_finds_matching_files(): void
    {
        // Create test files with different extensions
        touch($this->tempDir . '/test1.txt');
        touch($this->tempDir . '/test2.txt');
        touch($this->tempDir . '/other.md');

        // Find all .txt files
        $files = $this->fs->glob($this->tempDir . '/*.txt');

        // Assert only .txt files are returned
        $this->assertCount(2, $files);
    }

    /**
     * Test that lastModified() returns file modification time.
     *
     * Verifies that the method returns a valid Unix timestamp
     * representing the file's last modification time.
     */
    public function test_last_modified_returns_modification_time(): void
    {
        $file = $this->tempDir . '/test.txt';
        touch($file);

        // Get modification time
        $mtime = $this->fs->lastModified($file);

        // Assert it's a valid timestamp
        $this->assertIsInt($mtime);
        $this->assertGreaterThan(0, $mtime);
    }

    /**
     * Test that allFiles() returns all files recursively.
     *
     * Verifies that the method returns all files in a directory tree,
     * including files in subdirectories.
     */
    public function test_all_files_returns_all_files_recursively(): void
    {
        // Create nested file structure
        mkdir($this->tempDir . '/sub');
        touch($this->tempDir . '/file1.txt');
        touch($this->tempDir . '/sub/file2.txt');

        // Get all files recursively
        $files = $this->fs->allFiles($this->tempDir);

        // Assert both files are found
        $this->assertCount(2, $files);
    }
}
