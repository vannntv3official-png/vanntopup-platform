<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Authentication required.'], 401);
        }

        if (! $user->isAdmin() || ! $user->hasPermission($permission)) {
            return response()->json(['message' => 'Forbidden: insufficient permissions.'], 403);
        }

        return $next($request);
    }
}
