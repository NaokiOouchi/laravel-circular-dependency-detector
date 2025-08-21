<?php

namespace LaravelCircularDependencyDetector\Formatters;

class MermaidFormatter implements FormatterInterface
{
    public function format(array $cycles, array $dependencies, array $violations = []): string
    {
        $mermaid = "```mermaid\n";
        $mermaid .= "graph TD\n";
        
        $cycleEdges = $this->extractCycleEdges($cycles);
        $violationEdges = $this->extractViolationEdges($violations);
        $moduleStyles = $this->determineModuleStyles($cycles, $violations);
        
        $nodeIndex = 0;
        $nodeMap = [];
        
        foreach (array_keys($dependencies) as $module) {
            $nodeId = 'M' . $nodeIndex++;
            $nodeMap[$module] = $nodeId;
            $mermaid .= sprintf("    %s[\"%s\"]\n", $nodeId, $module);
        }
        
        $mermaid .= "\n";
        
        $edges = [];
        foreach ($dependencies as $module => $deps) {
            $targetModules = $this->extractTargetModules($deps);
            
            foreach ($targetModules as $target) {
                if (!isset($nodeMap[$module]) || !isset($nodeMap[$target])) {
                    continue;
                }
                
                $edgeKey = $module . '->' . $target;
                
                if (!isset($edges[$edgeKey])) {
                    $fromNode = $nodeMap[$module];
                    $toNode = $nodeMap[$target];
                    
                    if (isset($cycleEdges[$edgeKey])) {
                        $edges[$edgeKey] = sprintf("    %s -->|cycle| %s\n", $fromNode, $toNode);
                    } elseif (isset($violationEdges[$edgeKey])) {
                        $edges[$edgeKey] = sprintf("    %s -.->|violation| %s\n", $fromNode, $toNode);
                    } else {
                        $edges[$edgeKey] = sprintf("    %s --> %s\n", $fromNode, $toNode);
                    }
                }
            }
        }
        
        foreach ($edges as $edge) {
            $mermaid .= $edge;
        }
        
        $mermaid .= "\n";
        
        foreach ($moduleStyles as $module => $style) {
            if (isset($nodeMap[$module])) {
                $mermaid .= sprintf("    classDef %s %s\n", $style['class'], $style['style']);
                $mermaid .= sprintf("    class %s %s\n", $nodeMap[$module], $style['class']);
            }
        }
        
        $mermaid .= "\n";
        $mermaid .= "    classDef cycleNode fill:#ffcccc,stroke:#ff0000,stroke-width:2px\n";
        $mermaid .= "    classDef violationNode fill:#fff4cc,stroke:#ff9900,stroke-width:2px\n";
        $mermaid .= "    classDef cleanNode fill:#ccffcc,stroke:#00cc00,stroke-width:2px\n";
        
        $mermaid .= "```\n";
        
        return $mermaid;
    }
    
    public function supportsOutput(): bool
    {
        return true;
    }
    
    public function getFileExtension(): string
    {
        return 'md';
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
    
    private function determineModuleStyles(array $cycles, array $violations): array
    {
        $styles = [];
        $modulesInCycles = [];
        
        foreach ($cycles as $cycle) {
            foreach ($cycle['modules'] as $module) {
                $modulesInCycles[$module] = true;
            }
        }
        
        $allModules = [];
        foreach ($cycles as $cycle) {
            foreach ($cycle['modules'] as $module) {
                $allModules[$module] = true;
            }
        }
        
        foreach (array_keys($violations) as $module) {
            $allModules[$module] = true;
        }
        
        foreach (array_keys($allModules) as $module) {
            if (isset($modulesInCycles[$module])) {
                $styles[$module] = [
                    'class' => 'cycleNode',
                    'style' => 'fill:#ffcccc,stroke:#ff0000,stroke-width:2px',
                ];
            } elseif (isset($violations[$module]) && count($violations[$module]) > 0) {
                $styles[$module] = [
                    'class' => 'violationNode',
                    'style' => 'fill:#fff4cc,stroke:#ff9900,stroke-width:2px',
                ];
            } else {
                $styles[$module] = [
                    'class' => 'cleanNode',
                    'style' => 'fill:#ccffcc,stroke:#00cc00,stroke-width:2px',
                ];
            }
        }
        
        return $styles;
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
