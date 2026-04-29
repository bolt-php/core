<?php

use framework\components\Config;
use framework\components\PathManager;
use framework\components\Validator;
use framework\tests\TestApplication;
use framework\tests\TestDependencyContainer;

function createApp(array $config = [])
{
    $base_config = [
        'paths' => [
            'base_dir' => __DIR__,
            'root' => __DIR__,
            'runtime' => __DIR__ . '/runtime',
            'assets' => __DIR__ . '/app/resources',
        ],
        'TEST_KEY' => 'tests',
    ];

    $base_config = array_merge($base_config, $config);

    $app = TestApplication::getInstance();

    $app->registerComponent('config', new Config());
    $app->registerComponent('path', new PathManager());
    $app->registerComponent('di', new TestDependencyContainer());
    $app->registerComponent('validator', new Validator());

    foreach ($base_config as $key => $value) {
        $app->config->set($key, $value);
    }

    $app->init();

    return $app;
}

function app()
{
    return TestApplication::getInstance();
}

/**
 * @return framework\db\QueryBuilder|null
 */
function db()
{
    return app()->db;
}

function config($key = null, $default = null)
{
    if ($key === null) {
        return app()->config;
    }

    return app()->config->get($key, $default);
}

function env($key, $default = null)
{
    if ($key === null) {
        return $_ENV;
    }
    return $_ENV[$key] ?? $default;
}

function logs()
{
    return app()->logger;
}