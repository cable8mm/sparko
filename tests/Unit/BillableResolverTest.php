<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\User;
use App\Sparko\BillableResolver;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class BillableResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolves_the_configured_billable_entity(): void
    {
        $user = User::factory()->create();

        $resolved = app(BillableResolver::class)->resolve($user, $user->getKey());

        $this->assertTrue($resolved->is($user));
    }

    public function test_denies_a_different_billable_entity(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->expectException(AuthorizationException::class);

        app(BillableResolver::class)->resolve($user, $other->getKey());
    }

    public function test_rejects_a_billable_model_without_the_contract(): void
    {
        config()->set('sparko.billable_model', \stdClass::class);

        $this->expectException(InvalidArgumentException::class);

        app(BillableResolver::class)->resolve(User::factory()->create(), 1);
    }
}
