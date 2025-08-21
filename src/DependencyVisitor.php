<?php

namespace LaravelCircularDependencyDetector;

use Illuminate\Support\Collection;
use PhpParser\Node;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Use_;
use PhpParser\NodeVisitorAbstract;

class DependencyVisitor extends NodeVisitorAbstract
{
    private string $currentModule;
    private array $config;
    private Collection $dependencies;
    private array $uses = [];
    private ?string $currentNamespace = null;

    public function __construct(string $currentModule, array $config)
    {
        $this->currentModule = $currentModule;
        $this->config = $config;
        $this->dependencies = collect();
    }

    public function enterNode(Node $node)
    {
        if ($node instanceof Node\Stmt\Namespace_) {
            $this->currentNamespace = $node->name ? $node->name->toString() : null;
        }

        if ($node instanceof Use_) {
            $this->handleUseStatement($node);
        }

        if ($node instanceof New_) {
            $this->handleNewExpression($node);
        }

        if ($node instanceof StaticCall) {
            $this->handleStaticCall($node);
        }

        if ($node instanceof ClassConstFetch) {
            $this->handleClassConstFetch($node);
        }

        if ($node instanceof MethodCall) {
            $this->handleMethodCall($node);
        }

        if ($node instanceof Class_) {
            $this->handleClassExtends($node);
            $this->handleClassImplements($node);
        }

        return null;
    }

    public function getDependencies(): Collection
    {
        return $this->dependencies;
    }

    private function handleUseStatement(Use_ $node): void
    {
        foreach ($node->uses as $use) {
            $className = $use->name->toString();
            $alias = $use->alias ? $use->alias->name : null;
            
            $this->uses[$alias ?? $this->getShortName($className)] = $className;
            $this->addDependency($className);
        }
    }

    private function handleNewExpression(New_ $node): void
    {
        if ($node->class instanceof Name) {
            $className = $this->resolveClassName($node->class);
            $this->addDependency($className);
        }
    }

    private function handleStaticCall(StaticCall $node): void
    {
        if ($node->class instanceof Name) {
            $className = $this->resolveClassName($node->class);
            $this->addDependency($className);
            
            if ($this->isFacade($className)) {
                $this->handleFacadeCall($className, $node);
            }
        }
    }

    private function handleClassConstFetch(ClassConstFetch $node): void
    {
        if ($node->class instanceof Name && $node->class->toString() !== 'self' && $node->class->toString() !== 'parent') {
            $className = $this->resolveClassName($node->class);
            $this->addDependency($className);
        }
    }

    private function handleMethodCall(MethodCall $node): void
    {
        if ($node->var instanceof Node\Expr\Variable && 
            $node->var->name === 'this' && 
            $node->name instanceof Node\Identifier) {
            
            $methodName = $node->name->name;
            
            if (in_array($methodName, ['app', 'make', 'resolve'])) {
                $this->handleServiceContainerCall($node);
            }
        }

        if ($node->var instanceof Node\Expr\FuncCall && 
            $node->var->name instanceof Name &&
            in_array($node->var->name->toString(), ['app', 'resolve'])) {
            
            $this->handleServiceContainerCall($node);
        }
    }

    private function handleServiceContainerCall(MethodCall $node): void
    {
        if (!empty($node->args) && $node->args[0]->value instanceof Node\Scalar\String_) {
            $serviceName = $node->args[0]->value->value;
            
            if (class_exists($serviceName) || interface_exists($serviceName)) {
                $this->addDependency($serviceName);
            }
        }

        if (!empty($node->args) && $node->args[0]->value instanceof ClassConstFetch) {
            $classConstFetch = $node->args[0]->value;
            if ($classConstFetch->class instanceof Name) {
                $className = $this->resolveClassName($classConstFetch->class);
                $this->addDependency($className);
            }
        }
    }

    private function handleClassExtends(Class_ $node): void
    {
        if ($node->extends) {
            $className = $this->resolveClassName($node->extends);
            $this->addDependency($className);
        }
    }

    private function handleClassImplements(Class_ $node): void
    {
        foreach ($node->implements as $interface) {
            $className = $this->resolveClassName($interface);
            $this->addDependency($className);
        }
    }

    private function handleFacadeCall(string $facadeClass, StaticCall $node): void
    {
        $facadeMapping = [
            'Illuminate\\Support\\Facades\\DB' => 'Illuminate\\Database\\DatabaseManager',
            'Illuminate\\Support\\Facades\\Auth' => 'Illuminate\\Auth\\AuthManager',
            'Illuminate\\Support\\Facades\\Cache' => 'Illuminate\\Cache\\CacheManager',
            'Illuminate\\Support\\Facades\\Event' => 'Illuminate\\Events\\Dispatcher',
            'Illuminate\\Support\\Facades\\Log' => 'Illuminate\\Log\\LogManager',
            'Illuminate\\Support\\Facades\\Mail' => 'Illuminate\\Mail\\Mailer',
            'Illuminate\\Support\\Facades\\Queue' => 'Illuminate\\Queue\\QueueManager',
            'Illuminate\\Support\\Facades\\Redis' => 'Illuminate\\Redis\\RedisManager',
            'Illuminate\\Support\\Facades\\Storage' => 'Illuminate\\Filesystem\\FilesystemManager',
            'Illuminate\\Support\\Facades\\Validator' => 'Illuminate\\Validation\\Factory',
        ];

        if (isset($facadeMapping[$facadeClass])) {
            $this->addDependency($facadeMapping[$facadeClass]);
        }
    }

    private function isFacade(string $className): bool
    {
        return str_starts_with($className, 'Illuminate\\Support\\Facades\\');
    }

    private function resolveClassName(Name $name): string
    {
        $className = $name->toString();
        
        if ($name->isFullyQualified()) {
            return $className;
        }
        
        if (isset($this->uses[$className])) {
            return $this->uses[$className];
        }
        
        $parts = explode('\\', $className);
        $firstPart = $parts[0];
        
        if (isset($this->uses[$firstPart])) {
            $parts[0] = $this->uses[$firstPart];
            return implode('\\', $parts);
        }
        
        if ($this->currentNamespace && !str_starts_with($className, '\\')) {
            return $this->currentNamespace . '\\' . $className;
        }
        
        return $className;
    }

    private function getShortName(string $className): string
    {
        $parts = explode('\\', $className);
        return end($parts);
    }

    private function addDependency(string $className): void
    {
        $className = ltrim($className, '\\');
        
        if (empty($className)) {
            return;
        }
        
        if ($this->isBuiltinClass($className)) {
            return;
        }
        
        if ($this->isCurrentModuleClass($className)) {
            return;
        }
        
        if (!$this->dependencies->contains($className)) {
            $this->dependencies->push($className);
        }
    }

    private function isBuiltinClass(string $className): bool
    {
        $builtinClasses = [
            'Exception',
            'stdClass',
            'DateTime',
            'DateTimeImmutable',
            'DateTimeZone',
            'DateInterval',
            'DatePeriod',
            'Closure',
            'Generator',
            'Throwable',
            'Error',
            'ErrorException',
        ];
        
        return in_array($className, $builtinClasses);
    }

    private function isCurrentModuleClass(string $className): bool
    {
        $modulePrefix = 'App\\Modules\\' . $this->currentModule . '\\';
        return str_starts_with($className, $modulePrefix);
    }
}
