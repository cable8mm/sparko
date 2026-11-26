<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Sparko\Contracts\BillableEntity;
use App\Sparko\Plans\EntitlementService;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveSubscription
{
    public function __construct(private readonly EntitlementService $entitlements) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof Model) {
            return redirect('/login');
        }

        $modelClass = config('sparko.billable_model');

        if (! is_string($modelClass) || ! class_exists($modelClass)) {
            abort(500);
        }

        $billable = $modelClass::query()->find($user->getKey());

        if (! $billable instanceof BillableEntity) {
            abort(403);
        }

        if ($this->entitlements->isActive($billable)) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->ajax()) {
            abort(402);
        }

        return redirect()->route('sparko.portal', $billable);
    }
}
