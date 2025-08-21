<?php

namespace LaravelCircularDependencyDetector\Console\Commands;

use Illuminate\Console\Command;
use LaravelCircularDependencyDetector\CircularDependencyDetector;
use LaravelCircularDependencyDetector\DependencyAnalyzer;
use LaravelCircularDependencyDetector\Formatters\OutputFormatterFactory;

class DetectCircularDependenciesCommand extends Command
{
    protected $signature = 'modules:detect-circular 
                           {--path= : Override modules path (e.g., app/Domain, src/Services)}
                           {--format=console : Output format (console, json, dot, mermaid)}
                           {--output= : Output file path}';

    protected $description = 'Detect circular dependencies between modules';

    private CircularDependencyDetector $detector;
    private DependencyAnalyzer $analyzer;
    private OutputFormatterFactory $formatterFactory;

    public function __construct(
        CircularDependencyDetector $detector,
        DependencyAnalyzer $analyzer,
        OutputFormatterFactory $formatterFactory
    ) {
        parent::__construct();
        $this->detector = $detector;
        $this->analyzer = $analyzer;
        $this->formatterFactory = $formatterFactory;
    }

    public function handle(): int
    {
        $this->info('Analyzing module dependencies...');
        
        // Override configuration with command options
        $this->applyCommandOptions();
        
        $startTime = microtime(true);
        
        $dependencies = $this->analyzer->analyzeDependencies();
        
        if ($dependencies->isEmpty()) {
            $this->error('No modules found at the configured path.');
            $this->line('Please check your configuration at: config/circular-dependency-detector.php');
            
            // Still generate output file if requested, even with no modules
            if ($this->option('output')) {
                $this->formatAndOutput([], [], []);
            }
            
            return self::FAILURE;
        }
        
        $this->info(sprintf('Found %d modules to analyze.', $dependencies->count()));
        
        $cycles = $this->detector->detectWithDependencies($dependencies->toArray());
        
        $violations = [];
        
        $elapsedTime = round(microtime(true) - $startTime, 2);
        
        $this->formatAndOutput($cycles, $dependencies->toArray(), $violations);
        
        $this->newLine();
        $this->info(sprintf('Analysis completed in %s seconds.', $elapsedTime));
        
        if (count($cycles) > 0) {
            $this->error(sprintf('Found %d circular dependencies!', count($cycles)));
            return self::FAILURE;
        }
        
        $this->info('No circular dependencies found.');
        return self::SUCCESS;
    }

    private function formatAndOutput(array $cycles, array $dependencies, array $violations): void
    {
        $format = $this->option('format');
        $outputPath = $this->option('output');
        
        try {
            $formatter = $this->formatterFactory->create($format);
            $output = $formatter->format($cycles, $dependencies, $violations);
            
            if ($outputPath) {
                $this->writeToFile($outputPath, $output, $formatter->getFileExtension());
                $this->info(sprintf('Results written to: %s', $outputPath));
            } else {
                if ($format === 'console') {
                    $this->line($output);
                } else {
                    $this->line($output);
                    $this->newLine();
                    $this->warn('Consider using --output option to save non-console formats to a file.');
                }
            }
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());
            $this->line('Available formats: console, json, dot, mermaid');
        }
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

    private function applyCommandOptions(): void
    {
        // Override modules_path if provided
        if ($path = $this->option('path')) {
            // Check if the path is absolute or relative
            $fullPath = str_starts_with($path, '/') ? $path : base_path($path);
            
            // If path doesn't exist with base_path, try as relative to package root
            if (!is_dir($fullPath) && is_dir(__DIR__ . '/../../../' . $path)) {
                $fullPath = realpath(__DIR__ . '/../../../' . $path);
            }
            
            config(['circular-dependency-detector.modules_path' => $fullPath]);
            $this->info("Using custom modules path: {$path}");
        }
        
        // Recreate analyzer with new config
        $this->analyzer = new DependencyAnalyzer(config('circular-dependency-detector'));
        $this->detector = new CircularDependencyDetector($this->analyzer);
    }
}
