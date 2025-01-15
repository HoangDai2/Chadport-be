<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use JWTAuth;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle($request, Closure $next, ...$roles)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            if (!in_array($user->role_id, $roles)) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        } catch (\Exception $e) {
            return response()->json(['message' => 'Token is invalid'], 401);
        }

        return $next($request);
        }
}
