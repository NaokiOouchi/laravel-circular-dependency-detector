<?php

namespace LaravelCircularDependencyDetector\Tests\Unit;

use LaravelCircularDependencyDetector\DependencyAnalyzer;
use LaravelCircularDependencyDetector\Tests\TestCase;

class DependencyAnalyzerTest extends TestCase
{
    private DependencyAnalyzer $analyzer;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->analyzer = new DependencyAnalyzer([
            'modules_path' => __DIR__ . '/../fixtures/Modules',
            'scan_patterns' => [
                'services' => 'Services',
                'controllers' => 'Controllers',
                'repositories' => 'Repositories',
            ],
            'ignore_patterns' => [
                '*/Tests/*',
            ],
            'allowed_dependencies' => [
                'Contracts',
                'Events',
                'Exceptions',
            ],
        ]);
    }

    public function test_analyzes_dependencies(): void
    {
        $dependencies = $this->analyzer->analyzeDependencies();
        
        $this->assertNotNull($dependencies);
        $this->assertGreaterThan(0, $dependencies->count());
        
        // Check that ModuleA and ModuleB are detected
        $this->assertTrue($dependencies->has('ModuleA'));
        $this->assertTrue($dependencies->has('ModuleB'));
    }

    public function test_analyzes_single_module(): void
    {
        $modulePath = __DIR__ . '/../fixtures/Modules/ModuleA';
        $dependencies = $this->analyzer->analyzeModule('ModuleA', $modulePath);
        
        $this->assertNotNull($dependencies);
        
        // ModuleA should depend on ModuleB
        $hasDependencyOnModuleB = $dependencies->contains(function ($dep) {
            return str_contains($dep, 'ModuleB');
        });
        
        $this->assertTrue($hasDependencyOnModuleB);
    }

    public function test_analyzes_single_file(): void
    {
        $filePath = __DIR__ . '/../fixtures/Modules/ModuleA/Services/ServiceA.php';
        $dependencies = $this->analyzer->analyzeFile($filePath, 'ModuleA');
        
        $this->assertNotNull($dependencies);
        
        // ServiceA should depend on ServiceB
        $hasDependencyOnServiceB = $dependencies->contains(function ($dep) {
            return str_contains($dep, 'ServiceB');
        });
        
        $this->assertTrue($hasDependencyOnServiceB);
    }

    public function test_ignores_files_matching_ignore_patterns(): void
    {
        // Create a test file that should be ignored
        $testDir = __DIR__ . '/../fixtures/Modules/ModuleA/Tests';
        if (!is_dir($testDir)) {
            mkdir($testDir, 0755, true);
        }
        
        file_put_contents(
            $testDir . '/TestFile.php',
            '<?php namespace App\Modules\ModuleA\Tests; use App\Modules\ModuleB\Services\ServiceB; class TestFile {}'
        );
        
        $dependencies = $this->analyzer->analyzeDependencies();
        
        // The test file dependency should not be included
        $moduleADeps = $dependencies->get('ModuleA');
        if ($moduleADeps) {
            $hasTestDependency = false;
            foreach ($moduleADeps as $dep) {
                if (str_contains($dep, 'Tests')) {
                    $hasTestDependency = true;
                    break;
                }
            }
            $this->assertFalse($hasTestDependency);
        }
        
        // Clean up
        unlink($testDir . '/TestFile.php');
        rmdir($testDir);
    }

    public function test_handles_non_existent_modules_path(): void
    {
        $analyzer = new DependencyAnalyzer([
            'modules_path' => '/non/existent/path',
        ]);
        
        $dependencies = $analyzer->analyzeDependencies();
        
        $this->assertNotNull($dependencies);
        $this->assertTrue($dependencies->isEmpty());
    }
}
