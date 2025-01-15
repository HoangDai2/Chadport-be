<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckUserRole
{
    public function handle(Request $request, Closure $next)
    {
        if (Auth::check()) {
            $user = Auth::user();
            $adminRoles = [1, 2];
            if (in_array($user->role_id, $adminRoles)) {
                return $next($request);
            } else {
                return response()->json(['error' => 'Unauthorized access to admin'], 403);
            }
        }
        
        return response()->json(['error' => 'Unauthorized'], 401);
    }
}
