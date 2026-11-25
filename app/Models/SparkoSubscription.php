<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SparkoSubscription extends Model
{
    protected $table = 'sparko_subscriptions';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime',
            'next_charge_at' => 'datetime',
            'terms_accepted_at' => 'datetime',
        ];
    }
}
