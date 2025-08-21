<?php

namespace LaravelCircularDependencyDetector\Console\Commands;

use Illuminate\Console\Command;
use LaravelCircularDependencyDetector\DependencyAnalyzer;
use LaravelCircularDependencyDetector\Formatters\OutputFormatterFactory;

class GenerateDependencyGraphCommand extends Command
{
    protected $signature = 'modules:graph 
                           {--format=dot : Graph format (dot, mermaid)}
                           {--output= : Output file path}';

    protected $description = 'Generate a dependency graph for modules';

    private DependencyAnalyzer $analyzer;
    private OutputFormatterFactory $formatterFactory;

    public function __construct(
        DependencyAnalyzer $analyzer,
        OutputFormatterFactory $formatterFactory
    ) {
        parent::__construct();
        $this->analyzer = $analyzer;
        $this->formatterFactory = $formatterFactory;
    }

    public function handle(): int
    {
        $format = $this->option('format');
        
        if (!in_array($format, ['dot', 'mermaid'])) {
            $this->error('Invalid format. Supported formats: dot, mermaid');
            return self::FAILURE;
        }
        
        $this->info('Generating dependency graph...');
        
        $startTime = microtime(true);
        
        $dependencies = $this->analyzer->analyzeDependencies();
        
        if ($dependencies->isEmpty()) {
            $this->error('No modules found at the configured path.');
            $this->line('Please check your configuration at: config/circular-dependency-detector.php');
            return self::FAILURE;
        }
        
        $this->info(sprintf('Found %d modules.', $dependencies->count()));
        
        $formatter = $this->formatterFactory->create($format);
        $output = $formatter->format([], $dependencies->toArray(), []);
        
        $outputPath = $this->option('output');
        
        if ($outputPath) {
            $this->writeToFile($outputPath, $output, $formatter->getFileExtension());
            $this->info(sprintf('Graph written to: %s', $outputPath));
            
            if ($format === 'dot') {
                $this->info('You can generate a PNG image using:');
                $this->line(sprintf('  dot -Tpng %s -o graph.png', $outputPath));
            }
        } else {
            $this->line($output);
            $this->newLine();
            $this->warn('Consider using --output option to save the graph to a file.');
        }
        
        $elapsedTime = round(microtime(true) - $startTime, 2);
        $this->info(sprintf('Graph generation completed in %s seconds.', $elapsedTime));
        
        $this->displayStatistics($dependencies->toArray());
        
        return self::SUCCESS;
    }

    private function writeToFile(string $path, string $content, string $extension): void
    {
        if (!str_contains($path, '.')) {
            $path .= '.' . $extension;
        }
        
        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        
        file_put_contents($path, $content);
    }

    private function displayStatistics(array $dependencies): void
    {
        $this->newLine();
        $this->info('Graph Statistics:');
        $this->line('----------------');
        
        $totalDependencies = 0;
        $maxDependencies = 0;
        $maxModule = '';
        
        foreach ($dependencies as $module => $deps) {
            $count = is_array($deps) || $deps instanceof \Countable ? count($deps) : 0;
            $totalDependencies += $count;
            
            if ($count > $maxDependencies) {
                $maxDependencies = $count;
                $maxModule = $module;
            }
        }
        
        $avgDependencies = count($dependencies) > 0 ? round($totalDependencies / count($dependencies), 2) : 0;
        
        $this->line(sprintf('  Total modules: %d', count($dependencies)));
        $this->line(sprintf('  Total dependencies: %d', $totalDependencies));
        $this->line(sprintf('  Average dependencies per module: %.2f', $avgDependencies));
        
        if ($maxModule) {
            $this->line(sprintf('  Module with most dependencies: %s (%d)', $maxModule, $maxDependencies));
        }
        
        $isolatedModules = $this->findIsolatedModules($dependencies);
        if (!empty($isolatedModules)) {
            $this->line(sprintf('  Isolated modules (no dependencies): %s', implode(', ', $isolatedModules)));
        }
    }

    private function findIsolatedModules(array $dependencies): array
    {
        $isolated = [];
        
        foreach ($dependencies as $module => $deps) {
            $count = is_array($deps) || $deps instanceof \Countable ? count($deps) : 0;
            if ($count === 0) {
                $isolated[] = $module;
            }
        }
        
        return $isolated;
    }
}
