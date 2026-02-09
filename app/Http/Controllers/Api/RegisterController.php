<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class RegisterController extends Controller
{
    // Register Super Admin (no authentication required)
    public function registerSuperAdmin(Request $request)
    {
        Log::info('Super admin registration attempt', $request->all());

        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|min:6|confirmed',
                'phone' => 'nullable|string|max:20'
            ]);

            if ($validator->fails()) {
                Log::error('Validation failed', $validator->errors()->toArray());
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Check if super admin already exists
            $existingSuperAdmin = User::where('role', 'super_admin')->first();
            if ($existingSuperAdmin) {
                Log::warning('Super admin already exists');
                return response()->json([
                    'message' => 'Super admin already exists. Please login instead.'
                ], 400);
            }

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => 'super_admin',
                'phone' => $request->phone,
                'is_active' => true,
                'school_id' => null
            ]);

            Log::info('Super admin registered successfully', ['user_id' => $user->id]);

            // Generate JWT token
            $token = JWTAuth::fromUser($user);

            return response()->json([
                'message' => 'Super admin successfully registered',
                'access_token' => $token,
                'token_type' => 'bearer',
                'expires_in' => auth()->factory()->getTTL() * 60,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'phone' => $user->phone
                ]
            ], 201);

        } catch (\Exception $e) {
            Log::error('Super admin registration failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Registration failed: ' . $e->getMessage()
            ], 500);
        }
    }

    // Register School Users (requires super admin authentication)
    public function registerSchoolUser(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6|confirmed',
            'school_id' => 'required|exists:schools,id',
            'role' => 'required|in:admin,employee,student,parent',
            'phone' => 'nullable|string|max:20'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check if user is authorized to register (only super_admin)
        $currentUser = auth()->user();
        if (!$currentUser) {
            return response()->json([
                'message' => 'Unauthenticated'
            ], 401);
        }

        // Only super_admin can register new users
        if (!$currentUser->isSuperAdmin()) {
            return response()->json([
                'message' => 'Only Super Admin can register new users'
            ], 403);
        }

        try {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'school_id' => $request->school_id,
                'role' => $request->role,
                'phone' => $request->phone,
                'is_active' => true
            ]);

            Log::info('School user registered', [
                'user_id' => $user->id,
                'registered_by' => $currentUser->id,
                'school_id' => $request->school_id
            ]);

            return response()->json([
                'message' => 'User successfully registered',
                'user' => $user->makeHidden(['password'])
            ], 201);

        } catch (\Exception $e) {
            Log::error('User registration failed', [
                'error' => $e->getMessage(),
                'data' => $request->all()
            ]);

            return response()->json([
                'message' => 'Registration failed. Please try again.'
            ], 500);
        }
    }

    // Check if super admin exists
    public function checkSuperAdmin()
    {
        try {
            $superAdminExists = User::where('role', 'super_admin')->exists();
            
            return response()->json([
                'super_admin_exists' => $superAdminExists
            ]);
        } catch (\Exception $e) {
            Log::error('Check super admin failed', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'super_admin_exists' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
     public function registerSchoolUserByAdmin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6|confirmed',
            'role' => 'required|in:employee,student,parent',
            'phone' => 'nullable|string|max:20'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $currentUser = auth()->user();
        
        // School admins can only register users for their own school
        if (!$currentUser->school_id) {
            return response()->json([
                'message' => 'No school associated with your account'
            ], 403);
        }

        try {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'school_id' => $currentUser->school_id, // Use current user's school
                'role' => $request->role,
                'phone' => $request->phone,
                'is_active' => true
            ]);

            Log::info('School user registered by admin', [
                'user_id' => $user->id,
                'registered_by' => $currentUser->id,
                'school_id' => $currentUser->school_id
            ]);

            return response()->json([
                'message' => 'User successfully registered',
                'user' => $user->makeHidden(['password'])
            ], 201);

        } catch (\Exception $e) {
            Log::error('User registration failed', [
                'error' => $e->getMessage(),
                'data' => $request->all()
            ]);

            return response()->json([
                'message' => 'Registration failed. Please try again.'
            ], 500);
        }
    }
}