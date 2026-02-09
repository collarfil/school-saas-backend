<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    public function handle(Request $request, Closure $next, $permission): Response
    {
        $user = auth()->user();
        
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Super admin has all permissions
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        if (!$user->hasPermission($permission)) {
            return response()->json([
                'message' => 'Insufficient permissions. Required: ' . $permission,
                'required_permission' => $permission,
                'user_permissions' => $user->getPermissions()
            ], 403);
        }

        return $next($request);
    }
}