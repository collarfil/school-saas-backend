<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\School;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;

class AuthController extends Controller
{
    public function me(Request $request)
    {
        $user = $request->user();
        $user->load(['school', 'school.activeSubscription']);
        
        return response()->json([
            'user' => $user,
            'has_active_subscription' => $user->hasActiveSubscription(),
            'school' => $user->school
        ]);
    }

    public function register(Request $request)
    {
        // Only super admin can register new users (school admins)
        if (!auth()->check() || !auth()->user()->isSuperAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'school_id' => 'required|exists:schools,id',
            'role' => 'required|in:admin,employee,student,parent',
            'phone' => 'nullable|string|max:20',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'school_id' => $validated['school_id'],
            'role' => $validated['role'],
            'phone' => $validated['phone'] ?? null,
        ]);

        return response()->json([
            'message' => 'User registered successfully',
            'user' => $user
        ], 201);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|unique:users,email,' . $user->id,
            'phone' => 'nullable|string|max:20',
            'current_password' => 'sometimes|required_with:password|current_password',
            'password' => 'sometimes|required|confirmed|min:8',
        ]);

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $user->update($validated);

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $user
        ]);
    }

    public function getUsers(Request $request)
    {
        $user = auth()->user();
        $query = User::with('school');

        if ($user->isSchoolAdmin()) {
            $query->where('school_id', $user->school_id);
        }

        $users = $query->latest()->paginate(25);

        return response()->json($users);
    }

    public function changePassword(Request $request)
{
    $user = auth()->user();

    $request->validate([
        'password' => 'required|string|min:6|confirmed'
    ]);

    $user->password = bcrypt($request->password);
    $user->must_change_password = false;
    $user->save();

    return response()->json(['message' => 'Password changed successfully']);
}

}