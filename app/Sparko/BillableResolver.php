<?php

declare(strict_types=1);

namespace App\Sparko;

use App\Sparko\Contracts\BillableEntity;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;

class BillableResolver
{
    /**
     * Resolve the configured billable entity and authorize access to it.
     *
     * @param  int|string  $identifier
     */
    public function resolve(Authenticatable $user, $identifier): Model
    {
        $modelClass = config('sparko.billable_model');

        if (! is_string($modelClass) || ! is_subclass_of($modelClass, Model::class) || ! is_subclass_of($modelClass, BillableEntity::class)) {
            throw new InvalidArgumentException('sparko.billable_model must be an Eloquent model implementing BillableEntity.');
        }

        $entity = $modelClass::query()->find($identifier);

        if (! $entity instanceof Model) {
            throw new ModelNotFoundException;
        }

        if (! $entity->isAccessibleBy($user)) {
            throw new AuthorizationException;
        }

        return $entity;
    }
}
