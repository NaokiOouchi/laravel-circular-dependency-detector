<?php

namespace LaravelCircularDependencyDetector\Tests\Unit;

use LaravelCircularDependencyDetector\DependencyVisitor;
use LaravelCircularDependencyDetector\Tests\TestCase;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;

class DependencyVisitorTest extends TestCase
{
    private $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);
    }

    public function test_detects_use_statements(): void
    {
        $code = '<?php
            namespace App\Modules\ModuleA\Services;
            use App\Modules\ModuleB\Services\ServiceB;
            use App\Modules\ModuleC\Repositories\RepositoryC;
            
            class ServiceA {
            }
        ';

        $ast = $this->parser->parse($code);
        $visitor = new DependencyVisitor('ModuleA', []);
        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $dependencies = $visitor->getDependencies();
        
        $this->assertCount(2, $dependencies);
        $this->assertTrue($dependencies->contains('App\Modules\ModuleB\Services\ServiceB'));
        $this->assertTrue($dependencies->contains('App\Modules\ModuleC\Repositories\RepositoryC'));
    }

    public function test_detects_new_instantiation(): void
    {
        $code = '<?php
            namespace App\Modules\ModuleA\Services;
            
            class ServiceA {
                public function test() {
                    $obj = new \App\Modules\ModuleB\Services\ServiceB();
                }
            }
        ';

        $ast = $this->parser->parse($code);
        $visitor = new DependencyVisitor('ModuleA', []);
        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $dependencies = $visitor->getDependencies();
        
        $this->assertCount(1, $dependencies);
        $this->assertTrue($dependencies->contains('App\Modules\ModuleB\Services\ServiceB'));
    }

    public function test_detects_static_method_calls(): void
    {
        $code = '<?php
            namespace App\Modules\ModuleA\Services;
            use App\Modules\ModuleB\Helpers\Helper;
            
            class ServiceA {
                public function test() {
                    Helper::doSomething();
                }
            }
        ';

        $ast = $this->parser->parse($code);
        $visitor = new DependencyVisitor('ModuleA', []);
        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $dependencies = $visitor->getDependencies();
        
        $this->assertTrue($dependencies->contains('App\Modules\ModuleB\Helpers\Helper'));
    }

    public function test_ignores_same_module_dependencies(): void
    {
        $code = '<?php
            namespace App\Modules\ModuleA\Services;
            use App\Modules\ModuleA\Repositories\RepositoryA;
            use App\Modules\ModuleB\Services\ServiceB;
            
            class ServiceA {
                public function test() {
                    $repo = new RepositoryA();
                    $service = new ServiceB();
                }
            }
        ';

        $ast = $this->parser->parse($code);
        $visitor = new DependencyVisitor('ModuleA', []);
        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $dependencies = $visitor->getDependencies();
        
        // Should only have ModuleB dependency, not ModuleA
        $this->assertCount(1, $dependencies);
        $this->assertTrue($dependencies->contains('App\Modules\ModuleB\Services\ServiceB'));
        $this->assertFalse($dependencies->contains('App\Modules\ModuleA\Repositories\RepositoryA'));
    }

    public function test_detects_class_extends(): void
    {
        $code = '<?php
            namespace App\Modules\ModuleA\Services;
            use App\Modules\ModuleB\Services\BaseService;
            
            class ServiceA extends BaseService {
            }
        ';

        $ast = $this->parser->parse($code);
        $visitor = new DependencyVisitor('ModuleA', []);
        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $dependencies = $visitor->getDependencies();
        
        $this->assertTrue($dependencies->contains('App\Modules\ModuleB\Services\BaseService'));
    }

    public function test_detects_interface_implementation(): void
    {
        $code = '<?php
            namespace App\Modules\ModuleA\Services;
            use App\Modules\ModuleB\Contracts\ServiceInterface;
            
            class ServiceA implements ServiceInterface {
            }
        ';

        $ast = $this->parser->parse($code);
        $visitor = new DependencyVisitor('ModuleA', []);
        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $dependencies = $visitor->getDependencies();
        
        $this->assertTrue($dependencies->contains('App\Modules\ModuleB\Contracts\ServiceInterface'));
    }

    public function test_ignores_builtin_classes(): void
    {
        $code = '<?php
            namespace App\Modules\ModuleA\Services;
            
            class ServiceA {
                public function test() {
                    $date = new \DateTime();
                    throw new \Exception("test");
                }
            }
        ';

        $ast = $this->parser->parse($code);
        $visitor = new DependencyVisitor('ModuleA', []);
        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $dependencies = $visitor->getDependencies();
        
        // Should not include built-in PHP classes
        $this->assertCount(0, $dependencies);
    }

    public function test_detects_facade_usage(): void
    {
        $code = '<?php
            namespace App\Modules\ModuleA\Services;
            use Illuminate\Support\Facades\DB;
            
            class ServiceA {
                public function test() {
                    DB::table("users")->get();
                }
            }
        ';

        $ast = $this->parser->parse($code);
        $visitor = new DependencyVisitor('ModuleA', []);
        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $dependencies = $visitor->getDependencies();
        
        // Should detect facade and possibly its underlying service
        $this->assertTrue($dependencies->contains('Illuminate\Support\Facades\DB') || 
                       $dependencies->contains('Illuminate\Database\DatabaseManager'));
    }
}
