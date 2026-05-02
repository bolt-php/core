<?php

namespace framework\tests\Integration;

use framework\utils\helpers\DotenvHelper;
use PHPUnit\Framework\TestCase;

class DotenvHelperTest extends TestCase
{
    private string $envFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->envFile = __DIR__ . '/.env.test';
        $content = "TINY_TEST_VAR=hello_world\n";
        $content .= "TINY_ANOTHER_VAR=123\n";
        $content .= "# TINY_COMMENTED_VAR=hidden\n";
        file_put_contents($this->envFile, $content);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->envFile)) {
            unlink($this->envFile);
        }

        // Clean up environment variables
        unset($_ENV['TINY_TEST_VAR']);
        unset($_ENV['TINY_ANOTHER_VAR']);
        putenv("TINY_TEST_VAR");
        putenv("TINY_ANOTHER_VAR");

        parent::tearDown();
    }

    /**
     * Test loading a .env file and confirming variables are in the environment
     */
    public function testLoadEnvFile(): void
    {
        DotenvHelper::load($this->envFile);

        $this->assertEquals('hello_world', $_ENV['TINY_TEST_VAR']);
        $this->assertEquals('123', $_ENV['TINY_ANOTHER_VAR']);
        $this->assertArrayNotHasKey('TINY_COMMENTED_VAR', $_ENV);

        $this->assertEquals('hello_world', getenv('TINY_TEST_VAR'));
        $this->assertEquals('123', getenv('TINY_ANOTHER_VAR'));
    }

    /**
     * Test that DotenvHelper throws exception when file is not found
     */
    public function testLoadFileNotFound(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Dotenv file not found");
        DotenvHelper::load(__DIR__ . '/.nonexistent_env');
    }
}
