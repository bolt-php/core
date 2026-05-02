<?php

use framework\tests\TestApplication;
use PHPUnit\Framework\TestCase;

class PathTest extends TestCase
{
    private function resolvePath($path)
    {
        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        $segments = explode(DIRECTORY_SEPARATOR, $path);
        $result = [];
        foreach ($segments as $segment) {
            if ($segment === '..') {
                array_pop($result);
            } elseif ($segment !== '.' && $segment !== '') {
                $result[] = $segment;
            }
        }
        $prefix = (strpos($path, DIRECTORY_SEPARATOR) === 0) ? DIRECTORY_SEPARATOR : '';
        return $prefix . implode(DIRECTORY_SEPARATOR, $result);
    }

    public function testResolve()
    {
        $app = createApp();

        $this->assertEquals($this->resolvePath(__DIR__ . '/../'), $app->path->resolve('@root'));
        $this->assertEquals($this->resolvePath(__DIR__ . '/../runtime'), $app->path->resolve('@runtime/'));
        $this->assertEquals($this->resolvePath(__DIR__ . '/../runtime/logs'), $app->path->resolve('@runtime/logs'));
        $this->assertEquals($this->resolvePath(__DIR__ . '/../Unit'), $app->path->resolve('/Unit'));
        $this->assertEquals('home', $app->path->resolve('logs/../home'));
    }

    public function testNormalize()
    {
        $app = createApp();

        // 1. Basic normalization
        $this->assertEquals($this->resolvePath('path/to/dir'), $app->path->normalize('path/to/dir/'));
        $this->assertEquals($this->resolvePath('path/to/dir'), $app->path->normalize('path//to//dir'));

        // 2. Relative segments
        $this->assertEquals($this->resolvePath('path/to/../dir'), $app->path->normalize('path/to/../dir'));
        $this->assertEquals($this->resolvePath('path/to'), $app->path->normalize('path/to/./'));
        $this->assertEquals('..' . DIRECTORY_SEPARATOR . 'file', $app->path->normalize('../file'));

        // 3. Absolute paths
        $this->assertEquals($this->resolvePath('/path/to'), $app->path->normalize('/path/to/'));
        $this->assertEquals($this->resolvePath('C:/path/to'), $app->path->normalize('C:\\path/to/'));

        // 4. Edge Case (No Errors)
        $this->assertEquals('', $app->path->normalize(''));
    }

    public function testResolveWithDefault()
    {
        $app = createApp();

        $this->assertEquals($this->resolvePath(__DIR__ . '/../Unit'), $app->path->resolveWithDefault('Unit', '@root'));
        $this->assertEquals($this->resolvePath(__DIR__ . '/../runtime/logs'), $app->path->resolveWithDefault('@runtime/logs', '@root'));
    }

    public function testReplaceDots()
    {
        $app = createApp();

        $this->assertEquals('views/home/index', $app->path->replaceDots('views.home.index'));
        $this->assertEquals('views/home/index.html.php', $app->path->replaceDots('views.home.index\.html\.php'));
    }

    public function testRoot()
    {
        $app = createApp();

        $this->assertEquals($this->resolvePath(__DIR__ . '/../'), $app->path->root());
        $this->assertEquals($this->resolvePath(__DIR__ . '/../runtime'), $app->path->root('runtime'));
    }

    public function testApp()
    {
        $app = createApp();

        $this->assertEquals($this->resolvePath(__DIR__ . '/../app'), $app->path->app());
        $this->assertEquals($this->resolvePath(__DIR__ . '/../app/runtime'), $app->path->app('runtime'));
    }

    public function testConfig()
    {
        $app = createApp();

        $this->assertEquals($this->resolvePath(__DIR__ . '/../app/config'), $app->path->config());
        $this->assertEquals($this->resolvePath(__DIR__ . '/../app/config/tmp'), $app->path->config('tmp'));
    }

    public function testPublic()
    {
        $app = createApp();

        $this->assertEquals($this->resolvePath(__DIR__ . '/../public'), $app->path->public());
        $this->assertEquals($this->resolvePath(__DIR__ . '/../public/assets'), $app->path->public('assets'));
    }

    public function testStorage()
    {
        $app = createApp();

        $this->assertEquals($this->resolvePath(__DIR__ . '/../app/storage'), $app->path->storage());
        $this->assertEquals($this->resolvePath(__DIR__ . '/../app/storage/tmp'), $app->path->storage('tmp'));
    }

    public function testResources()
    {
        $app = createApp();

        $this->assertEquals($this->resolvePath(__DIR__ . '/../app/resources'), $app->path->resources());
        $this->assertEquals($this->resolvePath(__DIR__ . '/../app/resources/tmp'), $app->path->resources('tmp'));
    }

    public function testExists()
    {
        $app = createApp();

        $this->assertTrue($app->path->exists('@root/Unit'));
        $this->assertFalse($app->path->exists('@root/NonExistent'));

        // Test files
        $this->assertTrue($app->path->exists('@root/Unit/PathTest.php'));
        $this->assertFalse($app->path->exists('@root/Unit/NonExistent.php'));
    }

    public function testLinux()
    {
        $app = createApp([
            'paths' => [
                'base_dir' => '/home/vol1/path/to/htdocs',
                'root' => '/home/vol1/path/to/htdocs',
                'runtime' => '/home/vol1/path/to/htdocs/runtime',
                'assets' => '/home/vol1/path/to/htdocs/app/resources',
            ]
        ]);

        defined('DS') or define('DS', DIRECTORY_SEPARATOR);

        $this->assertEquals(DS . 'home' . DS . 'vol1' . DS . 'path' . DS . 'to' . DS . 'htdocs' . DS . 'runtime' . DS . 'views', $app->path->resolve('@runtime/views'));
    }
}
