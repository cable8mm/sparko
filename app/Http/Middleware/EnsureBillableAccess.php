<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Sparko\BillableResolver;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBillableAccess
{
    public function __construct(private readonly BillableResolver $resolver) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('login');
        }

        $route = $request->route();

        if ($route === null) {
            abort(403);
        }

        try {
            $billable = $this->resolver->resolve($user, $route->parameter('billable'));
        } catch (AuthorizationException|ModelNotFoundException) {
            abort(403);
        }

        $route->setParameter('billable', $billable);

        return $next($request);
    }
}
