<?php

namespace LaravelCircularDependencyDetector;

use Illuminate\Support\Collection;

class CircularDependencyDetector
{
    private DependencyAnalyzer $analyzer;
    private array $config;
    private array $visited = [];
    private array $recursionStack = [];
    private array $cycles = [];
    private array $dependencies = [];

    public function __construct(DependencyAnalyzer $analyzer, array $config)
    {
        $this->analyzer = $analyzer;
        $this->config = $config;
    }

    public function detect(): array
    {
        $this->dependencies = $this->analyzer->analyzeDependencies()->toArray();
        $this->cycles = [];
        $this->visited = [];
        $this->recursionStack = [];

        foreach (array_keys($this->dependencies) as $module) {
            if (!isset($this->visited[$module])) {
                $this->detectCyclesFromModule($module, []);
            }
        }

        return $this->formatCycles();
    }

    public function detectWithDependencies(array $dependencies): array
    {
        $this->dependencies = $dependencies;
        $this->cycles = [];
        $this->visited = [];
        $this->recursionStack = [];

        foreach (array_keys($this->dependencies) as $module) {
            if (!isset($this->visited[$module])) {
                $this->detectCyclesFromModule($module, []);
            }
        }

        return $this->formatCycles();
    }

    private function detectCyclesFromModule(string $module, array $path): void
    {
        $this->visited[$module] = true;
        $this->recursionStack[$module] = true;
        $path[] = $module;

        $moduleDependencies = $this->getModuleDependencies($module);

        foreach ($moduleDependencies as $dependency) {
            $dependencyModule = $this->extractModuleName($dependency);
            
            if (!$dependencyModule || $dependencyModule === $module) {
                continue;
            }

            if (isset($this->recursionStack[$dependencyModule]) && $this->recursionStack[$dependencyModule]) {
                $cycleStartIndex = array_search($dependencyModule, $path);
                if ($cycleStartIndex !== false) {
                    $cycle = array_slice($path, $cycleStartIndex);
                    $cycle[] = $dependencyModule;
                    $this->addCycle($cycle);
                }
            } elseif (!isset($this->visited[$dependencyModule])) {
                $this->detectCyclesFromModule($dependencyModule, $path);
            }
        }

        $this->recursionStack[$module] = false;
    }

    private function getModuleDependencies(string $module): array
    {
        if (!isset($this->dependencies[$module])) {
            return [];
        }

        $dependencies = $this->dependencies[$module];
        
        if ($dependencies instanceof Collection) {
            return $dependencies->toArray();
        }
        
        return is_array($dependencies) ? $dependencies : [];
    }

    private function extractModuleName(string $className): ?string
    {
        $namespacePatterns = $this->config['namespace_patterns'] ?? ['App\\Modules\\{MODULE}'];
        
        foreach ($namespacePatterns as $pattern) {
            // Convert pattern to regex by escaping backslashes and replacing {MODULE} with capture group
            $regexPattern = str_replace('\\', '\\\\', $pattern);
            $regexPattern = str_replace('{MODULE}', '([^\\\\]+)', $regexPattern);
            $regexPattern = '/^' . $regexPattern . '\\\\/';
            
            if (preg_match($regexPattern, $className, $matches)) {
                return $matches[1];
            }
        }
        
        return null;
    }

    private function addCycle(array $cycle): void
    {
        $normalizedCycle = $this->normalizeCycle($cycle);
        $cycleKey = implode('->', $normalizedCycle);
        
        if (!isset($this->cycles[$cycleKey])) {
            $this->cycles[$cycleKey] = $normalizedCycle;
        }
    }

    private function normalizeCycle(array $cycle): array
    {
        $minIndex = 0;
        $minValue = $cycle[0];
        
        for ($i = 1; $i < count($cycle) - 1; $i++) {
            if ($cycle[$i] < $minValue) {
                $minValue = $cycle[$i];
                $minIndex = $i;
            }
        }
        
        $normalized = array_merge(
            array_slice($cycle, $minIndex, count($cycle) - $minIndex - 1),
            array_slice($cycle, 0, $minIndex)
        );
        
        $normalized[] = $normalized[0];
        
        return $normalized;
    }

    private function formatCycles(): array
    {
        $formattedCycles = [];
        
        foreach ($this->cycles as $cycle) {
            $severity = $this->calculateSeverity($cycle);
            $affectedFiles = $this->getAffectedFiles($cycle);
            
            $formattedCycles[] = [
                'cycle' => $cycle,
                'length' => count($cycle) - 1,
                'severity' => $severity,
                'affected_files' => $affectedFiles,
                'modules' => array_unique(array_slice($cycle, 0, -1)),
            ];
        }
        
        usort($formattedCycles, function ($a, $b) {
            if ($a['severity'] === $b['severity']) {
                return $a['length'] - $b['length'];
            }
            return $this->getSeverityWeight($b['severity']) - $this->getSeverityWeight($a['severity']);
        });
        
        return $formattedCycles;
    }

    private function calculateSeverity(array $cycle): string
    {
        $length = count($cycle) - 1;
        
        if ($length === 2) {
            return 'critical';
        } elseif ($length === 3) {
            return 'high';
        } elseif ($length <= 5) {
            return 'medium';
        } else {
            return 'low';
        }
    }

    private function getSeverityWeight(string $severity): int
    {
        $weights = [
            'critical' => 4,
            'high' => 3,
            'medium' => 2,
            'low' => 1,
        ];
        
        return $weights[$severity] ?? 0;
    }

    private function getAffectedFiles(array $cycle): array
    {
        $affectedFiles = [];
        
        for ($i = 0; $i < count($cycle) - 1; $i++) {
            $fromModule = $cycle[$i];
            $toModule = $cycle[$i + 1];
            
            $dependencies = $this->getModuleDependencies($fromModule);
            
            foreach ($dependencies as $dependency) {
                if ($this->extractModuleName($dependency) === $toModule) {
                    $affectedFiles[] = [
                        'from' => $fromModule,
                        'to' => $toModule,
                        'class' => $dependency,
                    ];
                }
            }
        }
        
        return $affectedFiles;
    }

}
