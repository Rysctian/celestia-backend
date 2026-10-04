<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMenuAccess
{
    public function handle(Request $request, Closure $next, string $code, string $action): Response
    {
        abort_unless($request->user()?->hasMenuAccess($code, $action), 403);

        return $next($request);
    }
}
