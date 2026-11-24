<?php

declare(strict_types=1);

namespace App\Sparko\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

interface BillableEntity
{
    public function isAccessibleBy(Authenticatable $user): bool;
}
