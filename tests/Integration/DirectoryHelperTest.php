<?php

namespace framework\tests\Integration;

use framework\utils\helpers\DirectoryHelper;
use PHPUnit\Framework\TestCase;

class DirectoryHelperTest extends TestCase
{
    private string $testDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testDir = __DIR__ . '/directory_test_tmp';
        if (!is_dir($this->testDir)) {
            mkdir($this->testDir, 0777, true);
        }

        // Create some files
        file_put_contents($this->testDir . '/file1.php', 'content');
        file_put_contents($this->testDir . '/file2.txt', 'content');
        file_put_contents($this->testDir . '/exclude.txt', 'content');

        // Create a subdirectory (should be ignored by listFiles)
        if (!is_dir($this->testDir . '/subdir')) {
            mkdir($this->testDir . '/subdir');
        }
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->testDir);
        parent::tearDown();
    }

    /**
     * Recursively delete a directory
     */
    private function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }

    /**
     * Test basic file listing
     */
    public function testListFiles(): void
    {
        $files = DirectoryHelper::listFiles($this->testDir);

        $this->assertIsArray($files);
        $this->assertCount(3, $files);
        $this->assertContains('file1.php', $files);
        $this->assertContains('file2.txt', $files);
        $this->assertContains('exclude.txt', $files);
        $this->assertNotContains('subdir', $files);
        $this->assertNotContains('.', $files);
        $this->assertNotContains('..', $files);
    }

    /**
     * Test listing with an excluded file
     */
    public function testListFilesWithExclude(): void
    {
        $files = DirectoryHelper::listFiles($this->testDir, 'exclude.txt');

        $this->assertCount(2, $files);
        $this->assertContains('file1.php', $files);
        $this->assertContains('file2.txt', $files);
        $this->assertNotContains('exclude.txt', $files);
    }

    /**
     * Test listing on a non-existent directory
     */
    public function testListFilesWithNonExistentDir(): void
    {
        $files = DirectoryHelper::listFiles($this->testDir . '/does_not_exist');
        $this->assertIsArray($files);
        $this->assertEmpty($files);
    }

    /**
     * Test listing on a file path instead of a directory
     */
    public function testListFilesOnFilePath(): void
    {
        $filePath = $this->testDir . '/file1.php';
        $files = DirectoryHelper::listFiles($filePath);
        $this->assertIsArray($files);
        $this->assertEmpty($files);
    }
}
