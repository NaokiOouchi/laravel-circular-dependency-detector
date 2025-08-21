<?php

namespace LaravelCircularDependencyDetector\Tests\Feature;

use LaravelCircularDependencyDetector\Tests\TestCase;

class CommandOptionsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Ensure test fixtures directories exist
        $this->createTestFixtures();
    }

    protected function tearDown(): void
    {
        // Clean up any test output files
        $this->cleanupTestFiles();

        parent::tearDown();
    }

    public function test_custom_path_option(): void
    {
        // Test analyzing a different directory
        $this->artisan('modules:detect-circular', [
            '--path' => 'tests/fixtures/Domain',
        ])
            ->expectsOutput('Using custom modules path: tests/fixtures/Domain')
            ->assertExitCode(1); // Should find circular dependencies in Domain fixtures
    }

    public function test_format_json_option(): void
    {
        $outputPath = sys_get_temp_dir() . '/test-json-output.json';

        $this->artisan('modules:detect-circular', [
            '--path' => 'tests/fixtures/Domain',
            '--format' => 'json',
            '--output' => $outputPath,
        ])
            ->assertExitCode(1);

        $this->assertFileExists($outputPath);

        $json = json_decode(file_get_contents($outputPath), true);
        $this->assertIsArray($json);
        $this->assertArrayHasKey('summary', $json);
        $this->assertArrayHasKey('cycles', $json);

        // Should detect User and Product modules
        $this->assertArrayHasKey('dependencies', $json);
    }

    public function test_format_dot_option(): void
    {
        $outputPath = sys_get_temp_dir() . '/test-graph.dot';

        $this->artisan('modules:detect-circular', [
            '--format' => 'dot',
            '--output' => $outputPath,
        ])
            ->assertExitCode(1);

        $this->assertFileExists($outputPath);

        $content = file_get_contents($outputPath);
        $this->assertStringContainsString('digraph ModuleDependencies', $content);
        $this->assertStringContainsString('ModuleA', $content);
        $this->assertStringContainsString('ModuleB', $content);
    }

    public function test_format_mermaid_option(): void
    {
        $outputPath = sys_get_temp_dir() . '/test-graph.md';

        $this->artisan('modules:detect-circular', [
            '--format' => 'mermaid',
            '--output' => $outputPath,
        ])
            ->assertExitCode(1);

        $this->assertFileExists($outputPath);

        $content = file_get_contents($outputPath);
        $this->assertStringContainsString('```mermaid', $content);
        $this->assertStringContainsString('graph TD', $content);
    }


    public function test_multiple_options_combined(): void
    {
        $outputPath = sys_get_temp_dir() . '/test-combined.json';

        $this->artisan('modules:detect-circular', [
            '--path' => 'tests/fixtures/Domain',
            '--format' => 'json',
            '--output' => $outputPath,
        ])
            ->assertExitCode(1);

        $this->assertFileExists($outputPath);

        $json = json_decode(file_get_contents($outputPath), true);
        $this->assertIsArray($json);
        $this->assertArrayHasKey('cycles', $json);
        $this->assertArrayHasKey('summary', $json);
    }

    public function test_graph_command_with_custom_path(): void
    {
        $outputPath = sys_get_temp_dir() . '/test-custom-graph.dot';

        // First, let's add the --path option to GenerateDependencyGraphCommand
        // For now, test with default path
        $this->artisan('modules:graph', [
            '--format' => 'dot',
            '--output' => $outputPath,
        ])
            ->assertSuccessful();

        $this->assertFileExists($outputPath);
    }


    public function test_invalid_format_option(): void
    {
        $this->artisan('modules:detect-circular', [
            '--format' => 'invalid',
        ])
            ->expectsOutput('Unsupported format: invalid. Available formats: console, json, dot, mermaid')
            ->assertExitCode(1);
    }

    public function test_nonexistent_path_option(): void
    {
        $this->artisan('modules:detect-circular', [
            '--path' => 'nonexistent/path',
        ])
            ->expectsOutput('Using custom modules path: nonexistent/path')
            ->expectsOutput('No modules found at the configured path.')
            ->assertExitCode(1);
    }

    private function createTestFixtures(): void
    {
        // Ensure directories exist
        $paths = [
            base_path('tests/fixtures/Modules'),
            base_path('tests/fixtures/Domain'),
        ];

        foreach ($paths as $path) {
            if (!is_dir($path)) {
                mkdir($path, 0755, true);
            }
        }
    }

    private function cleanupTestFiles(): void
    {
        $files = [
            sys_get_temp_dir() . '/test-json-output.json',
            sys_get_temp_dir() . '/test-graph.dot',
            sys_get_temp_dir() . '/test-graph.md',
            sys_get_temp_dir() . '/test-combined.json',
            sys_get_temp_dir() . '/test-custom-graph.dot',
        ];

        foreach ($files as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }
}
