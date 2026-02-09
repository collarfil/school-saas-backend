<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TeachingStaffMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();
        
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        if ($user->role !== 'employee' || $user->employee_type !== 'teaching') {
            return response()->json([
                'message' => 'Unauthorized. Teaching staff access required.',
                'user_role' => $user->role,
                'employee_type' => $user->employee_type
            ], 403);
        }

        return $next($request);
    }
}