<?php

namespace LaravelCircularDependencyDetector\Tests\Unit;

use LaravelCircularDependencyDetector\Formatters\ConsoleFormatter;
use LaravelCircularDependencyDetector\Formatters\JsonFormatter;
use LaravelCircularDependencyDetector\Formatters\DotFormatter;
use LaravelCircularDependencyDetector\Formatters\MermaidFormatter;
use LaravelCircularDependencyDetector\Formatters\OutputFormatterFactory;
use LaravelCircularDependencyDetector\Tests\TestCase;

class FormatterTest extends TestCase
{
    private array $sampleCycles = [];
    private array $sampleDependencies = [];
    private array $sampleViolations = [];

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->sampleCycles = [
            [
                'cycle' => ['ModuleA', 'ModuleB', 'ModuleA'],
                'length' => 2,
                'severity' => 'critical',
                'modules' => ['ModuleA', 'ModuleB'],
                'affected_files' => [
                    [
                        'from' => 'ModuleA',
                        'to' => 'ModuleB',
                        'class' => 'App\\Modules\\ModuleB\\Services\\ServiceB',
                    ],
                ],
            ],
        ];
        
        $this->sampleDependencies = [
            'ModuleA' => ['App\\Modules\\ModuleB\\Services\\ServiceB'],
            'ModuleB' => ['App\\Modules\\ModuleA\\Services\\ServiceA'],
            'ModuleC' => [],
        ];
        
        $this->sampleViolations = [
            'ModuleA' => [
                [
                    'from_module' => 'ModuleA',
                    'from_layer' => 'Controllers',
                    'to_class' => 'App\\Modules\\ModuleB\\Repositories\\Repository',
                    'to_layer' => 'Repositories',
                    'allowed_layers' => ['Services', 'Requests'],
                    'message' => 'Controllers should not depend on Repositories',
                ],
            ],
        ];
    }

    public function test_console_formatter(): void
    {
        $formatter = new ConsoleFormatter();
        $output = $formatter->format($this->sampleCycles, $this->sampleDependencies, $this->sampleViolations);
        
        $this->assertIsString($output);
        $this->assertStringContainsString('Circular Dependency Analysis Report', $output);
        $this->assertStringContainsString('ModuleA', $output);
        $this->assertStringContainsString('ModuleB', $output);
        $this->assertStringContainsString('CRITICAL', $output);
    }

    public function test_json_formatter(): void
    {
        $formatter = new JsonFormatter();
        $output = $formatter->format($this->sampleCycles, $this->sampleDependencies, $this->sampleViolations);
        
        $this->assertIsString($output);
        
        $json = json_decode($output, true);
        $this->assertIsArray($json);
        $this->assertArrayHasKey('analysis_timestamp', $json);
        $this->assertArrayHasKey('summary', $json);
        $this->assertArrayHasKey('cycles', $json);
        $this->assertArrayHasKey('dependencies', $json);
        
        $this->assertEquals(3, $json['summary']['modules_count']);
        $this->assertEquals(1, $json['summary']['cycles_count']);
        $this->assertTrue($json['summary']['has_issues']);
    }

    public function test_dot_formatter(): void
    {
        $formatter = new DotFormatter();
        $output = $formatter->format($this->sampleCycles, $this->sampleDependencies, $this->sampleViolations);
        
        $this->assertIsString($output);
        $this->assertStringContainsString('digraph ModuleDependencies', $output);
        $this->assertStringContainsString('ModuleA', $output);
        $this->assertStringContainsString('ModuleB', $output);
        $this->assertStringContainsString('->', $output);
        $this->assertEquals('dot', $formatter->getFileExtension());
    }

    public function test_mermaid_formatter(): void
    {
        $formatter = new MermaidFormatter();
        $output = $formatter->format($this->sampleCycles, $this->sampleDependencies, $this->sampleViolations);
        
        $this->assertIsString($output);
        $this->assertStringContainsString('```mermaid', $output);
        $this->assertStringContainsString('graph TD', $output);
        $this->assertStringContainsString('ModuleA', $output);
        $this->assertStringContainsString('ModuleB', $output);
        $this->assertEquals('md', $formatter->getFileExtension());
    }

    public function test_formatter_factory(): void
    {
        $factory = new OutputFormatterFactory();
        
        // Test creating each formatter type
        $consoleFormatter = $factory->create('console');
        $this->assertInstanceOf(ConsoleFormatter::class, $consoleFormatter);
        
        $jsonFormatter = $factory->create('json');
        $this->assertInstanceOf(JsonFormatter::class, $jsonFormatter);
        
        $dotFormatter = $factory->create('dot');
        $this->assertInstanceOf(DotFormatter::class, $dotFormatter);
        
        $mermaidFormatter = $factory->create('mermaid');
        $this->assertInstanceOf(MermaidFormatter::class, $mermaidFormatter);
    }

    public function test_formatter_factory_throws_exception_for_invalid_format(): void
    {
        $factory = new OutputFormatterFactory();
        
        $this->expectException(\InvalidArgumentException::class);
        $factory->create('invalid_format');
    }

    public function test_formatter_factory_available_formats(): void
    {
        $factory = new OutputFormatterFactory();
        $formats = $factory->getAvailableFormats();
        
        $this->assertIsArray($formats);
        $this->assertContains('console', $formats);
        $this->assertContains('json', $formats);
        $this->assertContains('dot', $formats);
        $this->assertContains('mermaid', $formats);
    }

    public function test_formatter_factory_has_format(): void
    {
        $factory = new OutputFormatterFactory();
        
        $this->assertTrue($factory->hasFormat('console'));
        $this->assertTrue($factory->hasFormat('json'));
        $this->assertFalse($factory->hasFormat('nonexistent'));
    }
}
