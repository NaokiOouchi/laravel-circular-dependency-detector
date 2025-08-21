<?php

namespace App\Modules\ModuleB\Services;

use App\Modules\ModuleA\Services\ServiceA;

class ServiceB
{
    public function process(): void
    {
        $serviceA = new ServiceA($this);
        $serviceA->doSomething();
    }
}
