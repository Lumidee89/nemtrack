<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireModule
{
    public function handle(Request $request, Closure $next, string $module): Response
    {
        if ($request->user()?->role === 'super_admin') {
            return $next($request);
        }

        if (! $request->user()?->organization?->hasModule($module)) {
            return response()->json(['success' => false, 'message' => 'This module is not enabled.', 'code' => 'MODULE_NOT_ENABLED', 'errors' => null], 403);
        }

        return $next($request);
    }
}
