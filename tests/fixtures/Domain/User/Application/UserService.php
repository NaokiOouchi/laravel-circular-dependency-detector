<?php

namespace App\Modules\User\Application;

use App\Modules\Product\Domain\Product;

class UserService
{
    public function createUserWithProduct(): void
    {
        $product = new Product();
    }
}
