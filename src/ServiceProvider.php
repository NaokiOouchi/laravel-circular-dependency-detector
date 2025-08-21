<?php

namespace LaravelCircularDependencyDetector;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use LaravelCircularDependencyDetector\Console\Commands\DetectCircularDependenciesCommand;
use LaravelCircularDependencyDetector\Console\Commands\GenerateDependencyGraphCommand;
use LaravelCircularDependencyDetector\Formatters\OutputFormatterFactory;

class ServiceProvider extends BaseServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/circular-dependency-detector.php',
            'circular-dependency-detector'
        );

        $this->app->singleton(DependencyAnalyzer::class, function ($app) {
            return new DependencyAnalyzer(
                $app['config']->get('circular-dependency-detector')
            );
        });

        $this->app->singleton(CircularDependencyDetector::class, function ($app) {
            return new CircularDependencyDetector(
                $app->make(DependencyAnalyzer::class)
            );
        });

        $this->app->singleton(OutputFormatterFactory::class, function ($app) {
            return new OutputFormatterFactory();
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            // 設定ファイルを公開できるようにする
            $this->publishes([
                __DIR__ . '/../config/circular-dependency-detector.php' => config_path('circular-dependency-detector.php'),
            ], 'circular-dependency-detector-config');
            
            // または 'config' タグでも公開
            $this->publishes([
                __DIR__ . '/../config/circular-dependency-detector.php' => config_path('circular-dependency-detector.php'),
            ], 'config');

            $this->commands([
                DetectCircularDependenciesCommand::class,
                GenerateDependencyGraphCommand::class,
            ]);
        }
    }

    public function provides(): array
    {
        return [
            DependencyAnalyzer::class,
            CircularDependencyDetector::class,
        ];
    }
}
