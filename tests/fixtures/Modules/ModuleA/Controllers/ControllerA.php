<?php

namespace App\Modules\ModuleA\Controllers;

use App\Modules\ModuleA\Services\ServiceA;
use App\Modules\ModuleA\Repositories\RepositoryA;

class ControllerA
{
    private ServiceA $service;
    private RepositoryA $repository;

    public function __construct(ServiceA $service, RepositoryA $repository)
    {
        $this->service = $service;
        $this->repository = $repository;
    }

    public function index(): array
    {
        return [];
    }
}
