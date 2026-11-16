<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDemoMode
{
    /**
     * Handle an incoming request.
     * Enforces that demo functionality is strictly and explicitly enabled via configuration.
     * Local environment or APP_DEBUG=true alone is insufficient.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('nightlife.demo_enabled', false)) {
            abort(404);
        }

        return $next($request);
    }
}
