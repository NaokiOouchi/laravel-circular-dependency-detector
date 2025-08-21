<?php

namespace LaravelCircularDependencyDetector\Tests\Feature;

use LaravelCircularDependencyDetector\Tests\TestCase;

class CommandsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Ensure test fixtures directory exists
        $fixturesPath = __DIR__ . '/../fixtures/Modules';
        if (!is_dir($fixturesPath)) {
            mkdir($fixturesPath, 0755, true);
        }
    }

    public function test_detect_circular_dependencies_command(): void
    {
        // Since we have test fixtures with circular dependencies,
        // the command will return FAILURE (1) when cycles are detected
        $this->artisan('modules:detect-circular')
            ->assertExitCode(1); // Expects failure due to circular dependencies in fixtures
    }

    public function test_detect_circular_dependencies_command_with_json_format(): void
    {
        $this->artisan('modules:detect-circular', ['--format' => 'json'])
            ->assertExitCode(1); // Expects failure due to circular dependencies in fixtures
    }

    public function test_generate_dependency_graph_command(): void
    {
        $this->artisan('modules:graph')
            ->assertSuccessful();
    }

    public function test_generate_dependency_graph_with_mermaid_format(): void
    {
        $this->artisan('modules:graph', ['--format' => 'mermaid'])
            ->assertSuccessful();
    }


    public function test_command_with_output_file(): void
    {
        $outputPath = sys_get_temp_dir() . '/test-output.json';
        
        // Clean up any existing file
        if (file_exists($outputPath)) {
            unlink($outputPath);
        }
        
        $this->artisan('modules:detect-circular', [
            '--format' => 'json',
            '--output' => $outputPath,
        ])->assertExitCode(1); // Expects failure due to circular dependencies
        
        // The file should be created even with failures
        $this->assertFileExists($outputPath);
        
        $content = file_get_contents($outputPath);
        $json = json_decode($content, true);
        
        $this->assertIsArray($json);
        $this->assertArrayHasKey('analysis_timestamp', $json);
        $this->assertArrayHasKey('summary', $json);
        $this->assertArrayHasKey('cycles', $json);
        $this->assertArrayHasKey('dependencies', $json);
        
        // Clean up
        unlink($outputPath);
    }
}
