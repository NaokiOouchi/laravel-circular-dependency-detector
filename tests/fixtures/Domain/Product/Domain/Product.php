<?php

namespace App\Modules\Product\Domain;

use App\Modules\User\Application\UserService;

class Product
{
    public function assignToUser(): void
    {
        $service = new UserService();
        $service->createUserWithProduct();
    }
}
