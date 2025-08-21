<?php

namespace LaravelCircularDependencyDetector\Formatters;

class ConsoleFormatter implements FormatterInterface
{
    public function format(array $cycles, array $dependencies, array $violations = []): string
    {
        $buffer = '';
        
        $buffer .= $this->formatSummary($cycles, $dependencies);
        $buffer .= "\n";
        
        if (!empty($cycles)) {
            $buffer .= $this->formatCycles($cycles);
            $buffer .= "\n";
        }
        
        $buffer .= $this->formatDependencyGraph($dependencies);
        
        return $buffer;
    }
    
    public function supportsOutput(): bool
    {
        return false;
    }
    
    public function getFileExtension(): string
    {
        return 'txt';
    }
    
    private function formatSummary(array $cycles, array $dependencies): string
    {
        $modulesCount = count($dependencies);
        $cyclesCount = count($cycles);
        $hasIssues = $cyclesCount > 0;
        
        $buffer = "=====================================\n";
        $buffer .= "  Circular Dependency Analysis Report\n";
        $buffer .= "=====================================\n\n";
        
        $buffer .= "Summary:\n";
        $buffer .= "--------\n";
        $buffer .= sprintf("  Modules analyzed: %d\n", $modulesCount);
        $buffer .= sprintf("  Circular dependencies found: %d\n", $cyclesCount);
        $buffer .= sprintf("  Status: %s\n", $hasIssues ? '❌ Issues Found' : '✅ Clean');
        
        return $buffer;
    }
    
    private function formatCycles(array $cycles): string
    {
        $buffer = "\nCircular Dependencies:\n";
        $buffer .= "---------------------\n";
        
        foreach ($cycles as $index => $cycle) {
            $buffer .= sprintf("\n%d. ", $index + 1);
            $buffer .= $this->getSeverityIcon($cycle['severity']);
            $buffer .= sprintf(" %s (Length: %d)\n", strtoupper($cycle['severity']), $cycle['length']);
            
            $buffer .= "   Cycle: " . implode(' → ', $cycle['cycle']) . "\n";
            
            if (!empty($cycle['affected_files'])) {
                $buffer .= "   Affected dependencies:\n";
                foreach ($cycle['affected_files'] as $file) {
                    $buffer .= sprintf("   - %s uses %s\n", $file['from'], $file['class']);
                }
            }
        }
        
        return $buffer;
    }
    
    private function formatDependencyGraph(array $dependencies): string
    {
        $buffer = "\nDependency Graph:\n";
        $buffer .= "----------------\n";
        
        foreach ($dependencies as $module => $deps) {
            $depCount = is_array($deps) || $deps instanceof \Countable ? count($deps) : 0;
            $buffer .= sprintf("\n%s (%d dependencies)\n", $module, $depCount);
            
            if ($depCount > 0) {
                $moduleGroups = $this->groupDependenciesByModule($deps);
                
                foreach ($moduleGroups as $targetModule => $classes) {
                    $buffer .= sprintf("  └─ %s\n", $targetModule);
                    
                    $classCount = count($classes);
                    $displayCount = min(3, $classCount);
                    
                    for ($i = 0; $i < $displayCount; $i++) {
                        $buffer .= sprintf("     - %s\n", $this->getShortClassName($classes[$i]));
                    }
                    
                    if ($classCount > $displayCount) {
                        $buffer .= sprintf("     ... and %d more\n", $classCount - $displayCount);
                    }
                }
            }
        }
        
        return $buffer;
    }
    
    private function groupDependenciesByModule($dependencies): array
    {
        $groups = [];
        
        if ($dependencies instanceof \Traversable) {
            $dependencies = iterator_to_array($dependencies);
        }
        
        if (!is_array($dependencies)) {
            return $groups;
        }
        
        foreach ($dependencies as $dependency) {
            if (preg_match('/^App\\\\Modules\\\\([^\\\\]+)\\\\/', $dependency, $matches)) {
                $module = $matches[1];
                if (!isset($groups[$module])) {
                    $groups[$module] = [];
                }
                $groups[$module][] = $dependency;
            }
        }
        
        return $groups;
    }
    
    private function getShortClassName(string $className): string
    {
        $parts = explode('\\', $className);
        return count($parts) > 2 ? '...' . implode('\\', array_slice($parts, -2)) : $className;
    }
    
    private function getSeverityIcon(string $severity): string
    {
        $icons = [
            'critical' => '🔴',
            'high' => '🟠',
            'medium' => '🟡',
            'low' => '🟢',
        ];
        
        return $icons[$severity] ?? '⚪';
    }
}
