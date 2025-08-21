<?php

namespace LaravelCircularDependencyDetector\Formatters;

class DotFormatter implements FormatterInterface
{
    public function format(array $cycles, array $dependencies, array $violations = []): string
    {
        $dot = "digraph ModuleDependencies {\n";
        $dot .= "    rankdir=LR;\n";
        $dot .= "    node [shape=box, style=rounded];\n";
        $dot .= "    edge [color=gray50];\n\n";
        
        $cycleEdges = $this->extractCycleEdges($cycles);
        $violationEdges = $this->extractViolationEdges($violations);
        $moduleColors = $this->determineModuleColors($cycles, $violations, $dependencies);
        
        foreach ($moduleColors as $module => $color) {
            $dot .= sprintf("    \"%s\" [fillcolor=%s, style=\"rounded,filled\"];\n", $module, $color);
        }
        
        $dot .= "\n";
        
        $edges = [];
        foreach ($dependencies as $module => $deps) {
            $targetModules = $this->extractTargetModules($deps);
            
            foreach ($targetModules as $target) {
                $edgeKey = $module . '->' . $target;
                
                if (!isset($edges[$edgeKey])) {
                    $edgeStyle = $this->determineEdgeStyle($module, $target, $cycleEdges, $violationEdges);
                    $edges[$edgeKey] = sprintf("    \"%s\" -> \"%s\" %s;\n", $module, $target, $edgeStyle);
                }
            }
        }
        
        foreach ($edges as $edge) {
            $dot .= $edge;
        }
        
        $dot .= "\n    subgraph cluster_legend {\n";
        $dot .= "        label=\"Legend\";\n";
        $dot .= "        style=dashed;\n";
        $dot .= "        node [shape=plaintext];\n";
        $dot .= "        edge [style=invis];\n";
        $dot .= "        \"No Issues\" [fillcolor=lightgreen, style=filled];\n";
        $dot .= "        \"In Cycle\" [fillcolor=lightcoral, style=filled];\n";
        $dot .= "        \"Has Violations\" [fillcolor=lightyellow, style=filled];\n";
        $dot .= "        \"No Issues\" -> \"In Cycle\" -> \"Has Violations\";\n";
        $dot .= "    }\n";
        
        $dot .= "}\n";
        
        return $dot;
    }
    
    public function supportsOutput(): bool
    {
        return true;
    }
    
    public function getFileExtension(): string
    {
        return 'dot';
    }
    
    private function extractCycleEdges(array $cycles): array
    {
        $edges = [];
        
        foreach ($cycles as $cycle) {
            $path = $cycle['cycle'];
            for ($i = 0; $i < count($path) - 1; $i++) {
                $edges[$path[$i] . '->' . $path[$i + 1]] = true;
            }
        }
        
        return $edges;
    }
    
    private function extractViolationEdges(array $violations): array
    {
        $edges = [];
        
        foreach ($violations as $module => $moduleViolations) {
            foreach ($moduleViolations as $violation) {
                if (preg_match('/^App\\\\Modules\\\\([^\\\\]+)\\\\/', $violation['to_class'], $matches)) {
                    $edges[$module . '->' . $matches[1]] = true;
                }
            }
        }
        
        return $edges;
    }
    
    private function determineModuleColors(array $cycles, array $violations, array $dependencies): array
    {
        $colors = [];
        $modulesInCycles = [];
        
        foreach ($cycles as $cycle) {
            foreach ($cycle['modules'] as $module) {
                $modulesInCycles[$module] = true;
            }
        }
        
        // Get all modules from cycles, violations, and dependencies
        $allModules = [];
        
        // Add modules from cycles
        foreach ($cycles as $cycle) {
            foreach ($cycle['modules'] as $module) {
                $allModules[$module] = true;
            }
        }
        
        // Add modules from violations
        foreach (array_keys($violations) as $module) {
            $allModules[$module] = true;
        }
        
        // Add all modules from dependencies
        foreach (array_keys($dependencies) as $module) {
            $allModules[$module] = true;
        }
        
        // Add target modules from dependencies
        foreach ($dependencies as $module => $deps) {
            $targetModules = $this->extractTargetModules($deps);
            foreach ($targetModules as $target) {
                $allModules[$target] = true;
            }
        }
        
        foreach (array_keys($allModules) as $module) {
            if (isset($modulesInCycles[$module])) {
                $colors[$module] = 'lightcoral';
            } elseif (isset($violations[$module]) && count($violations[$module]) > 0) {
                $colors[$module] = 'lightyellow';
            } else {
                $colors[$module] = 'lightgreen';
            }
        }
        
        return $colors;
    }
    
    private function extractTargetModules($dependencies): array
    {
        $modules = [];
        $deps = $this->convertToArray($dependencies);
        
        foreach ($deps as $dependency) {
            if (preg_match('/^App\\\\Modules\\\\([^\\\\]+)\\\\/', $dependency, $matches)) {
                $modules[$matches[1]] = true;
            }
        }
        
        return array_keys($modules);
    }
    
    private function determineEdgeStyle(string $from, string $to, array $cycleEdges, array $violationEdges): string
    {
        $edgeKey = $from . '->' . $to;
        
        if (isset($cycleEdges[$edgeKey])) {
            return '[color=red, penwidth=2, label="cycle"]';
        }
        
        if (isset($violationEdges[$edgeKey])) {
            return '[color=orange, style=dashed, label="violation"]';
        }
        
        return '';
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
