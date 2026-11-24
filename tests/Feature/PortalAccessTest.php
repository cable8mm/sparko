<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\EnsureBillableAccess;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PortalAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        Route::get('/login', fn () => 'sign-in')->name('login');
        Route::middleware(['auth', EnsureBillableAccess::class])
            ->get('/billing/{billable}/subscriptions', fn () => response('subscriptions'));
    }

    public function test_unauthenticated_request_redirects_to_sign_in(): void
    {
        $user = User::factory()->create();

        $this->get(route('sparko.portal', $user))
            ->assertRedirect('/login');
    }

    public function test_authenticated_subscriber_can_open_the_configured_billable_entity(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('sparko.portal', $user))
            ->assertOk()
            ->assertSee((string) $user->getKey());
    }

    public function test_authenticated_subscriber_cannot_open_another_entity(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($user)
            ->get(route('sparko.portal', $other))
            ->assertForbidden();
    }

    public function test_cross_entity_access_is_denied_on_other_portal_surfaces(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($user)
            ->get('/billing/'.$other->getKey().'/subscriptions')
            ->assertForbidden();
    }
}
