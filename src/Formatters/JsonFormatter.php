<?php

namespace LaravelCircularDependencyDetector\Formatters;

class JsonFormatter implements FormatterInterface
{
    public function format(array $cycles, array $dependencies, array $violations = []): string
    {
        $data = [
            'analysis_timestamp' => now()->toIso8601String(),
            'summary' => $this->generateSummary($cycles, $dependencies),
            'cycles' => $this->formatCycles($cycles),
            'dependencies' => $this->formatDependencies($dependencies),
        ];
        
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
    
    public function supportsOutput(): bool
    {
        return true;
    }
    
    public function getFileExtension(): string
    {
        return 'json';
    }
    
    private function generateSummary(array $cycles, array $dependencies): array
    {
        return [
            'modules_count' => count($dependencies),
            'cycles_count' => count($cycles),
            'has_issues' => count($cycles) > 0,
        ];
    }
    
    private function formatCycles(array $cycles): array
    {
        return array_map(function ($cycle) {
            return [
                'cycle' => $cycle['cycle'],
                'length' => $cycle['length'],
                'severity' => $cycle['severity'],
                'modules' => $cycle['modules'] ?? [],
                'affected_files' => array_map(function ($file) {
                    return [
                        'from_module' => $file['from'],
                        'to_module' => $file['to'],
                        'dependency_class' => $file['class'],
                    ];
                }, $cycle['affected_files'] ?? []),
            ];
        }, $cycles);
    }
    
    private function formatDependencies(array $dependencies): array
    {
        $formatted = [];
        
        foreach ($dependencies as $module => $deps) {
            $depsArray = $this->convertToArray($deps);
            
            $formatted[$module] = [
                'count' => count($depsArray),
                'dependencies' => $this->groupDependenciesByModule($depsArray),
            ];
        }
        
        return $formatted;
    }
    
    private function groupDependenciesByModule(array $dependencies): array
    {
        $groups = [];
        
        foreach ($dependencies as $dependency) {
            if (preg_match('/^App\\\\Modules\\\\([^\\\\]+)\\\\/', $dependency, $matches)) {
                $module = $matches[1];
                if (!isset($groups[$module])) {
                    $groups[$module] = [];
                }
                $groups[$module][] = $dependency;
            } else {
                if (!isset($groups['external'])) {
                    $groups['external'] = [];
                }
                $groups['external'][] = $dependency;
            }
        }
        
        return $groups;
    }
    
    private function convertToArray($data): array
    {
        if (is_array($data)) {
            return $data;
        }
        
        if ($data instanceof \Traversable) {
            return iterator_to_array($data);
        }
        
        return [];
    }
}
