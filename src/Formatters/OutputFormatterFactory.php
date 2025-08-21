<?php

namespace LaravelCircularDependencyDetector\Formatters;

use InvalidArgumentException;

class OutputFormatterFactory
{
    private array $formatters = [];

    public function __construct()
    {
        $this->registerDefaultFormatters();
    }

    public function create(string $format): FormatterInterface
    {
        if (!isset($this->formatters[$format])) {
            throw new InvalidArgumentException(
                sprintf('Unsupported format: %s. Available formats: %s', 
                    $format, 
                    implode(', ', array_keys($this->formatters))
                )
            );
        }

        $formatterClass = $this->formatters[$format];
        
        if (!class_exists($formatterClass)) {
            throw new InvalidArgumentException(
                sprintf('Formatter class %s does not exist', $formatterClass)
            );
        }

        $formatter = new $formatterClass();
        
        if (!$formatter instanceof FormatterInterface) {
            throw new InvalidArgumentException(
                sprintf('Formatter %s must implement FormatterInterface', $formatterClass)
            );
        }

        return $formatter;
    }

    public function register(string $format, string $formatterClass): void
    {
        if (!class_exists($formatterClass)) {
            throw new InvalidArgumentException(
                sprintf('Formatter class %s does not exist', $formatterClass)
            );
        }

        $reflection = new \ReflectionClass($formatterClass);
        
        if (!$reflection->implementsInterface(FormatterInterface::class)) {
            throw new InvalidArgumentException(
                sprintf('Formatter %s must implement FormatterInterface', $formatterClass)
            );
        }

        $this->formatters[$format] = $formatterClass;
    }

    public function getAvailableFormats(): array
    {
        return array_keys($this->formatters);
    }

    public function hasFormat(string $format): bool
    {
        return isset($this->formatters[$format]);
    }

    private function registerDefaultFormatters(): void
    {
        $this->formatters = [
            'console' => ConsoleFormatter::class,
            'json' => JsonFormatter::class,
            'dot' => DotFormatter::class,
            'mermaid' => MermaidFormatter::class,
        ];
    }
}
