<?php

namespace LaravelCircularDependencyDetector\Formatters;

interface FormatterInterface
{
    public function format(array $cycles, array $dependencies, array $violations = []): string;
    
    public function supportsOutput(): bool;
    
    public function getFileExtension(): string;
}
