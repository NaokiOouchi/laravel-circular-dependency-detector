<?php

namespace LaravelCircularDependencyDetector\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use LaravelCircularDependencyDetector\ServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            ServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('circular-dependency-detector.modules_path', __DIR__ . '/fixtures/Modules');
        $app['config']->set('circular-dependency-detector.scan_patterns', [
            'controllers' => 'Controllers',
            'services' => 'Services',
            'repositories' => 'Repositories',
            'domain' => 'Domain',
            'application' => 'Application',
        ]);
    }
}
