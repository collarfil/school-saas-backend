<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class SchoolController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        if ($user->isSuperAdmin()) {
            return School::with(['activeSubscription'])
                ->latest()
                ->paginate(25);
        }

        // Regular school users
        if (!$user->school_id) {
            return response()->json(['message' => 'No school associated'], 403);
        }

        return School::where('id', $user->school_id)
            ->with(['activeSubscription'])
            ->paginate(25);
    }

    public function store(Request $request)
    {
        // Only super admin can create schools
        if (!auth()->user() || !auth()->user()->isSuperAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'owner' => 'required|string|max:255',
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:schools,email|unique:users,email',
            'phone' => 'required|string|max:20',
            'address' => 'nullable|string',
            'logo' => 'nullable|file|image|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Handle logo upload if present
            $logoPath = null;
            if ($request->hasFile('logo')) {
                $path = $request->file('logo')->store('school_logos', 'public');
                $logoPath = '/storage/' . $path;
            }

            // Create school (locked by default)
            $school = School::create([
                'owner' => $request->owner,
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'address' => $request->address,
                'logo' => $logoPath,
                'is_unlocked' => false,
            ]);

            // Create the school admin user
            // Use phone as temporary password
            $temporaryPassword = $request->phone;
            
            $adminUser = User::create([
                'name' => $request->owner,
                'email' => $request->email,
                'password' => Hash::make($temporaryPassword),
                'role' => 'admin',
                'school_id' => $school->id,
                'phone' => $request->phone,
                'is_active' => true,
                'must_change_password' => true,
            ]);

            // Prepare credentials for immediate display
            $credentials = [
                'email' => $request->email,
                'password' => $temporaryPassword, // Show phone as password
                'schoolName' => $school->name,
                'owner' => $school->owner,
                'note' => 'Use your phone number as temporary password. You will be forced to change it on first login.'
            ];

            // Send credentials email (non-blocking)
            try {
                $frontendChangePasswordUrl = config('app.frontend_url') 
                    ? rtrim(config('app.frontend_url'), '/') . '/change-password'
                    : url('/change-password');

                Mail::to($adminUser->email)->send(new \App\Mail\SchoolCredentialsMail(
                    $school, 
                    $adminUser->email, 
                    $temporaryPassword, 
                    $frontendChangePasswordUrl
                ));
            } catch (\Exception $e) {
                Log::error('Failed to send SchoolCredentialsMail: ' . $e->getMessage());
                // Don't fail the request if email fails
            }

            return response()->json([
                'message' => 'School created successfully',
                'school' => $school,
                'admin_credentials' => $credentials, // This will be used for immediate display
                'login_credentials' => [
                    'username' => $adminUser->email,
                    'password' => $temporaryPassword // Include password in response
                ]
            ], 201);

        } catch (\Exception $e) {
            Log::error('School creation failed: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'School creation failed: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        $school = School::with(['subscriptions', 'users', 'activeSubscription'])
            ->findOrFail($id);

        $user = auth()->user();

        // School admin can only view their own school
        if ($user->isSchoolAdmin() && $school->id !== $user->school_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($school);
    }

    public function update(Request $request, $id)
    {
        $school = School::findOrFail($id);

        $user = auth()->user();

        if ($user->isSchoolAdmin() && $school->id !== $user->school_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'owner' => 'sometimes|required|string|max:255',
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|unique:schools,email,'. $id,
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'logo' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $school->update($validated);

        return response()->json([
            'message' => 'School updated successfully',
            'school'  => $school
        ]);
    }

    public function destroy($id)
    {
        if (!auth()->user()->isSuperAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $school = School::findOrFail($id);
        $school->delete();

        return response()->json(['message' => 'School deleted successfully']);
    }

    public function getSubscriptionStatus($id)
    {
        $school = School::with(['activeSubscription'])->findOrFail($id);

        $user = auth()->user();

        if ($user->isSchoolAdmin() && $school->id !== $user->school_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'has_active_subscription' => $school->hasActiveSubscription(),
            'active_subscription'     => $school->activeSubscription,
            'subscription_history'    => $school->subscriptions()->latest()->get()
        ]);
    }
}