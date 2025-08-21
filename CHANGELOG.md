# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - 2025-01-21

### Added
- Initial release
- Circular dependency detection using depth-first search algorithm
- Multiple output formats support:
  - Console output with colored formatting
  - JSON format for programmatic processing
  - DOT format for Graphviz visualization
  - Mermaid diagram format for documentation
- Support for multiple module paths
- Configurable scan patterns for different project structures
- Ignore patterns for excluding test files and other non-production code
- Allowed dependencies configuration for shared contracts and DTOs
- Laravel auto-discovery support
- Artisan commands:
  - `modules:detect-circular` - Detect circular dependencies
  - `modules:graph` - Generate dependency graphs
- Support for Laravel 9.x, 10.x, and 11.x
- Support for PHP 8.1+
- Comprehensive test suite with 37 tests

### Documentation
- English README with detailed usage examples
- Japanese README (README.ja.md)
- Publishing guide for Composer/Packagist
- Configuration examples for different architectures:
  - Traditional MVC
  - Domain-Driven Design (DDD)
  - Hexagonal Architecture
  - Microservices monorepo