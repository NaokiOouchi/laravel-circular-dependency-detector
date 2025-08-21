<?php

namespace LaravelCircularDependencyDetector\Tests\Unit;

use LaravelCircularDependencyDetector\CircularDependencyDetector;
use LaravelCircularDependencyDetector\DependencyAnalyzer;
use LaravelCircularDependencyDetector\Tests\TestCase;

class CircularDependencyDetectorTest extends TestCase
{
    private CircularDependencyDetector $detector;

    protected function setUp(): void
    {
        parent::setUp();
        
        $analyzer = $this->createMock(DependencyAnalyzer::class);
        $this->detector = new CircularDependencyDetector($analyzer);
    }

    public function test_detects_simple_circular_dependency(): void
    {
        $dependencies = [
            'ModuleA' => ['App\\Modules\\ModuleB\\Services\\ServiceB'],
            'ModuleB' => ['App\\Modules\\ModuleA\\Services\\ServiceA'],
        ];

        $cycles = $this->detector->detectWithDependencies($dependencies);

        $this->assertCount(1, $cycles);
        $this->assertEquals(['ModuleA', 'ModuleB', 'ModuleA'], $cycles[0]['cycle']);
        $this->assertEquals(2, $cycles[0]['length']);
        $this->assertEquals('critical', $cycles[0]['severity']);
    }

    public function test_detects_complex_circular_dependency(): void
    {
        $dependencies = [
            'ModuleA' => ['App\\Modules\\ModuleB\\Services\\ServiceB'],
            'ModuleB' => ['App\\Modules\\ModuleC\\Services\\ServiceC'],
            'ModuleC' => ['App\\Modules\\ModuleA\\Services\\ServiceA'],
        ];

        $cycles = $this->detector->detectWithDependencies($dependencies);

        $this->assertCount(1, $cycles);
        $this->assertEquals(3, $cycles[0]['length']);
        $this->assertEquals('high', $cycles[0]['severity']);
    }

    public function test_no_circular_dependency(): void
    {
        $dependencies = [
            'ModuleA' => ['App\\Modules\\ModuleB\\Services\\ServiceB'],
            'ModuleB' => ['App\\Modules\\ModuleC\\Services\\ServiceC'],
            'ModuleC' => [],
        ];

        $cycles = $this->detector->detectWithDependencies($dependencies);

        $this->assertCount(0, $cycles);
    }

}
