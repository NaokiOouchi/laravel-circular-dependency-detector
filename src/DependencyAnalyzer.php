<?php

namespace LaravelCircularDependencyDetector;

use Illuminate\Support\Collection;
use PhpParser\Error;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class DependencyAnalyzer
{
    private array $config;
    private $parser;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);
    }

    public function analyzeDependencies(): Collection
    {
        $modulesPath = $this->config['modules_path'] ?? app_path('Modules');
        
        // Handle multiple paths
        if (is_array($modulesPath)) {
            $dependencies = collect();
            foreach ($modulesPath as $path) {
                $pathDependencies = $this->analyzePathDependencies($path);
                $dependencies = $dependencies->merge($pathDependencies);
            }
            return $dependencies;
        }
        
        return $this->analyzePathDependencies($modulesPath);
    }
    
    private function analyzePathDependencies(string $modulesPath): Collection
    {
        if (!is_dir($modulesPath)) {
            return collect();
        }

        $modules = $this->getModules($modulesPath);
        $dependencies = collect();

        foreach ($modules as $moduleName => $modulePath) {
            $moduleDependencies = $this->analyzeModule($moduleName, $modulePath);
            $dependencies->put($moduleName, $moduleDependencies);
        }

        return $dependencies;
    }

    public function analyzeModule(string $moduleName, string $modulePath): Collection
    {
        $dependencies = collect();
        $scanPatterns = $this->config['scan_patterns'] ?? [];
        $ignorePatterns = $this->config['ignore_patterns'] ?? [];

        foreach ($scanPatterns as $key => $pattern) {
            $directoryPath = $modulePath . DIRECTORY_SEPARATOR . $pattern;
            
            if (!is_dir($directoryPath)) {
                continue;
            }

            $files = $this->getPhpFiles($directoryPath, $ignorePatterns);
            
            foreach ($files as $file) {
                $fileDependencies = $this->analyzeFile($file->getPathname(), $moduleName);
                
                foreach ($fileDependencies as $dependency) {
                    if (!$dependencies->contains($dependency)) {
                        $dependencies->push($dependency);
                    }
                }
            }
        }

        return $this->filterInternalDependencies($dependencies, $moduleName);
    }

    public function analyzeFile(string $filePath, string $currentModule): Collection
    {
        $dependencies = collect();

        try {
            $code = file_get_contents($filePath);
            $ast = $this->parser->parse($code);

            if ($ast === null) {
                return $dependencies;
            }

            $visitor = new DependencyVisitor($currentModule, $this->config);
            $traverser = new NodeTraverser();
            $traverser->addVisitor($visitor);
            $traverser->traverse($ast);

            $dependencies = $visitor->getDependencies();
        } catch (Error $e) {
            return $dependencies;
        }

        return $dependencies;
    }

    private function getModules(string $modulesPath): array
    {
        $modules = [];
        $iterator = new \DirectoryIterator($modulesPath);

        foreach ($iterator as $fileInfo) {
            if ($fileInfo->isDot() || !$fileInfo->isDir()) {
                continue;
            }

            $modules[$fileInfo->getFilename()] = $fileInfo->getPathname();
        }

        return $modules;
    }

    private function getPhpFiles(string $directoryPath, array $ignorePatterns): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directoryPath)
        );

        foreach ($iterator as $file) {
            if (!$file instanceof SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }

            $relativePath = str_replace($directoryPath, '', $file->getPathname());
            
            if ($this->shouldIgnoreFile($relativePath, $ignorePatterns)) {
                continue;
            }

            $files[] = $file;
        }

        return $files;
    }

    private function shouldIgnoreFile(string $filePath, array $ignorePatterns): bool
    {
        foreach ($ignorePatterns as $pattern) {
            $pattern = str_replace('*', '.*', $pattern);
            $pattern = str_replace('/', '\/', $pattern);
            
            if (preg_match('/' . $pattern . '/', $filePath)) {
                return true;
            }
        }

        return false;
    }

    private function filterInternalDependencies(Collection $dependencies, string $currentModule): Collection
    {
        $allowedDependencies = $this->config['allowed_dependencies'] ?? [];
        
        return $dependencies->filter(function ($dependency) use ($currentModule, $allowedDependencies) {
            if (str_starts_with($dependency, $currentModule . '\\')) {
                return false;
            }

            foreach ($allowedDependencies as $allowed) {
                if (str_ends_with($dependency, '\\' . $allowed) || str_contains($dependency, '\\' . $allowed . '\\')) {
                    return false;
                }
            }

            return $this->isModuleDependency($dependency);
        })->unique()->values();
    }

    private function isModuleDependency(string $dependency): bool
    {
        $modulesPath = $this->config['modules_path'] ?? app_path('Modules');
        $modules = $this->getModules($modulesPath);

        foreach (array_keys($modules) as $moduleName) {
            if (str_starts_with($dependency, 'App\\Modules\\' . $moduleName . '\\')) {
                return true;
            }
        }

        return false;
    }
}
