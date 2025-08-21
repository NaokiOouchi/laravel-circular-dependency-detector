<?php

namespace App\Modules\ModuleA\Services;

use App\Modules\ModuleB\Services\ServiceB;

class ServiceA
{
    private ServiceB $serviceB;

    public function __construct(ServiceB $serviceB)
    {
        $this->serviceB = $serviceB;
    }

    public function doSomething(): void
    {
        $this->serviceB->process();
    }
}
