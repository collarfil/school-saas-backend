<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    /**
     * Handle Login Request
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|string', // Changed to accept username or email
            'password' => 'required|string',
        ]);

        // Check if login is by username (contains @school.local) or email
        $loginField = filter_var($request->email, FILTER_VALIDATE_EMAIL) ? 'email' : 'email';
        
        // If it's a username (not a real email), search for the username pattern
        if (!filter_var($request->email, FILTER_VALIDATE_EMAIL)) {
            $credentials['email'] = $request->email . '@school.local';
        } else {
            $credentials['email'] = $request->email;
        }

        if (!$token = Auth::guard('api')->attempt($credentials)) {
            return response()->json(['error' => 'Invalid credentials'], 401);
        }

        return $this->respondWithToken($token);
    }

    /**
     * Structure the response with token + user payload + dashboard + permissions
     */
    protected function respondWithToken($token)
    {
        $user = auth('api')->user();

        // Build user payload
        $userPayload = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'phone' => $user->phone,
            'must_change_password' => (bool) $user->must_change_password,
            'school' => $user->school ? [
                'id' => $user->school->id,
                'name' => $user->school->name,
                'is_unlocked' => (bool) $user->school->is_unlocked
            ] : null,
            'permissions' => $this->getUserPermissions($user),
        ];

        // Determine default dashboard route based on role/permissions
        $defaultDashboard = $this->getDashboardRoute($user);

        // Return token + user payload + dashboard route
        return response()->json([
            'access_token'      => $token,
            'token_type'        => 'bearer',
            'expires_in'        => auth('api')->factory()->getTTL() * 60,
            'user'              => $userPayload,
            'default_dashboard' => $defaultDashboard
        ]);
    }

    /**
     * Map role/permissions to default dashboard route
     */
    protected function getDashboardRoute($user): string
    {
        if ($user->isSuperAdmin()) {
            return '/admin/dashboard';
        }

        if ($user->role === 'employee' && $user->employee_type === 'teaching') {
            return '/employee/dashboard';
        }

        if ($user->role === 'employee' && $user->employee_type === 'account') {
            return '/account/dashboard';
        }

        if ($user->role === 'student') {
            return '/student/dashboard';
        }

        if ($user->role === 'parent') {
            return '/parent/dashboard';
        }

        if ($user->school_id) {
            return '/school/dashboard';
        }

        return '/dashboard'; // fallback
    }

    /**
     * Get user permissions
     * Uses your existing CheckPermission logic
     */
    protected function getUserPermissions($user): array
    {
        if (method_exists($user, 'getPermissions')) {
            return $user->getPermissions(); // returns array of permissions
        }

        // fallback empty
        return [];
    }
}
