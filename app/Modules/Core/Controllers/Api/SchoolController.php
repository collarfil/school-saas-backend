<?php
// app/Modules/Core/Controllers/Api/SchoolController.php

namespace App\Modules\Core\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\School;
use App\Modules\Core\Models\User;
use App\Modules\Core\Models\Subscription;
use App\Modules\Core\Models\SubscriptionPricing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class SchoolController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        if ($user->isSuperAdmin()) {
            return School::with(['activeSubscription', 'currentSubscription'])
                ->latest()
                ->paginate(25);
        }

        if (!$user->school_id) {
            return response()->json(['message' => 'No school associated'], 403);
        }

        return School::where('id', $user->school_id)
            ->with(['activeSubscription', 'currentSubscription'])
            ->paginate(25);
    }

    public function store(Request $request)
    {
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
            // Subscription fields for Super Admin
            'has_free_subscription' => 'boolean',
            'subscription_type' => 'nullable|in:free,termly,yearly',
            'subscription_duration_days' => 'nullable|integer|min:1',
            'student_capacity' => 'nullable|integer|min:1|max:10000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $validated = $validator->validated();

            // Handle logo upload if present
            $logoPath = null;
            if ($request->hasFile('logo')) {
                $path = $request->file('logo')->store('school_logos', 'public');
                $logoPath = '/storage/' . $path;
            }

            // Check subscription settings
            $hasFreeSubscription = $validated['has_free_subscription'] ?? false;
            $subscriptionType = $validated['subscription_type'] ?? ($hasFreeSubscription ? 'free' : 'termly');

            // Calculate subscription expiry
            $subscriptionExpiresAt = null;
            if (!$hasFreeSubscription && isset($validated['subscription_duration_days'])) {
                $subscriptionExpiresAt = now()->addDays((int)$validated['subscription_duration_days']);
            }

            // Create school
            $school = School::create([
                'owner' => $validated['owner'],
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'address' => $validated['address'],
                'logo' => $logoPath,
                'is_unlocked' => true, // Unlock immediately
                'has_free_subscription' => $hasFreeSubscription,
                'subscription_type' => $subscriptionType,
                'subscription_expires_at' => $subscriptionExpiresAt,
            ]);

            // Create subscription record
            $subscription = $this->createSubscription($school, $validated);
            
            // Link subscription to school
            if ($subscription) {
                $school->subscription_id = $subscription->id;
                $school->save();
            }

            // Create the school admin user
            $temporaryPassword = Str::random(10);

            $adminUser = User::create([
                'name' => $validated['owner'],
                'email' => $validated['email'],
                'password' => Hash::make($temporaryPassword),
                'role' => 'admin',
                'school_id' => $school->id,
                'phone' => $validated['phone'],
                'is_active' => true,
                'must_change_password' => true,
            ]);

            $credentials = [
                'email' => $validated['email'],
                'password' => $temporaryPassword,
                'schoolName' => $school->name,
                'owner' => $school->owner,
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
            }

            return response()->json([
                'message' => 'School created successfully',
                'school' => $school,
                'subscription' => $subscription,
                'admin_credentials' => $credentials,
                'login_credentials' => [
                    'username' => $adminUser->email,
                    'password' => $temporaryPassword
                ]
            ], 201);

        } catch (\Exception $e) {
            Log::error('School creation failed: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'School creation failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create subscription for a school
     */
    private function createSubscription(School $school, array $data): ?Subscription
    {
        $hasFreeSubscription = $data['has_free_subscription'] ?? false;
        $subscriptionType = $data['subscription_type'] ?? ($hasFreeSubscription ? 'free' : 'termly');
        
        if ($hasFreeSubscription) {
            // Create free subscription
            return Subscription::create([
                'school_id' => $school->id,
                'plan_type' => 'free',
                'term' => $this->getCurrentTerm(),
                'school_session' => date('Y') . '/' . (date('Y') + 1),
                'student_capacity' => 1000,
                'amount' => 0,
                'payment_reference' => 'FREE-' . strtoupper(Str::random(10)),
                'payment_status' => 'paid',
                'payment_gateway' => 'system',
                'payment_date' => now(),
                'valid_from' => now(),
                'valid_until' => null, // Never expires for free
                'status' => 'active',
            ]);
        }

        // Create paid subscription
        $studentCapacity = $data['student_capacity'] ?? 100;
        $durationDays = $data['subscription_duration_days'] ?? ($subscriptionType === 'termly' ? 120 : 365);
        $validUntil = now()->addDays($durationDays);

        // Get pricing
        $pricing = SubscriptionPricing::where('plan_type', $subscriptionType)
            ->where('is_active', true)
            ->first();

        $basePrice = $pricing->base_price ?? ($subscriptionType === 'termly' ? 20000 : 50000);
        $perStudentPrice = $pricing->per_student_price ?? ($subscriptionType === 'termly' ? 2000 : 5000);
        $amount = $basePrice + ($studentCapacity * $perStudentPrice);

        return Subscription::create([
            'school_id' => $school->id,
            'plan_type' => $subscriptionType,
            'term' => $this->getCurrentTerm(),
            'school_session' => date('Y') . '/' . (date('Y') + 1),
            'student_capacity' => $studentCapacity,
            'amount' => $amount,
            'payment_reference' => 'MANUAL-' . strtoupper(Str::random(10)),
            'payment_status' => 'paid',
            'payment_gateway' => 'manual',
            'payment_date' => now(),
            'valid_from' => now(),
            'valid_until' => $validUntil,
            'status' => 'active',
        ]);
    }

    private function getCurrentTerm(): string
    {
        $month = now()->month;
        
        if ($month >= 1 && $month <= 4) {
            return 'First Term';
        } elseif ($month >= 5 && $month <= 8) {
            return 'Second Term';
        } else {
            return 'Third Term';
        }
    }

    public function show($id)
    {
        $school = School::with(['subscriptions', 'users', 'activeSubscription', 'currentSubscription'])
            ->findOrFail($id);

        $user = auth()->user();

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
            'school' => $school
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

    /**
     * Get school subscription status
     */
    public function getSubscriptionStatus($id)
    {
        $school = School::with(['activeSubscription', 'currentSubscription'])->findOrFail($id);

        $user = auth()->user();

        if ($user->isSchoolAdmin() && $school->id !== $user->school_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'has_active_subscription' => $school->hasActiveSubscription(),
            'active_subscription' => $school->activeSubscription,
            'current_subscription' => $school->currentSubscription,
            'subscription_status' => $school->getSubscriptionStatus(),
            'remaining_days' => $school->getRemainingDays(),
            'student_capacity' => $school->getStudentCapacity(),
            'remaining_capacity' => $school->getRemainingStudentCapacity(),
            'is_unlocked' => $school->is_unlocked,
            'has_free' => $school->has_free_subscription,
            'subscription_history' => $school->subscriptions()->latest()->get()
        ]);
    }

    /**
     * Update school subscription (Super Admin only)
     */
    public function updateSubscription(Request $request, $id)
    {
        if (!auth()->user()->isSuperAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'has_free_subscription' => 'boolean',
            'subscription_type' => 'nullable|in:free,termly,yearly',
            'subscription_duration_days' => 'nullable|integer|min:1',
            'student_capacity' => 'nullable|integer|min:1|max:10000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $school = School::findOrFail($id);
            $validated = $validator->validated();

            $hasFreeSubscription = $validated['has_free_subscription'] ?? $school->has_free_subscription;
            $subscriptionType = $validated['subscription_type'] ?? $school->subscription_type;
            
            $subscriptionExpiresAt = null;
            if (!$hasFreeSubscription && isset($validated['subscription_duration_days'])) {
                $subscriptionExpiresAt = now()->addDays((int)$validated['subscription_duration_days']);
            }

            // Update school
            $school->update([
                'has_free_subscription' => $hasFreeSubscription,
                'subscription_type' => $subscriptionType,
                'subscription_expires_at' => $subscriptionExpiresAt,
                'is_unlocked' => $hasFreeSubscription || ($subscriptionExpiresAt && $subscriptionExpiresAt->isFuture()),
            ]);

            // Create new subscription record
            if ($hasFreeSubscription) {
                $subscription = Subscription::create([
                    'school_id' => $school->id,
                    'plan_type' => 'free',
                    'student_capacity' => 1000,
                    'amount' => 0,
                    'payment_reference' => 'FREE-' . strtoupper(Str::random(10)),
                    'payment_status' => 'paid',
                    'payment_gateway' => 'system',
                    'payment_date' => now(),
                    'valid_from' => now(),
                    'valid_until' => null,
                    'status' => 'active',
                ]);
            } elseif (isset($validated['student_capacity'])) {
                $studentCapacity = $validated['student_capacity'];
                $amount = $this->calculateAmount($subscriptionType, $studentCapacity);

                $subscription = Subscription::create([
                    'school_id' => $school->id,
                    'plan_type' => $subscriptionType,
                    'student_capacity' => $studentCapacity,
                    'amount' => $amount,
                    'payment_reference' => 'MANUAL-' . strtoupper(Str::random(10)),
                    'payment_status' => 'paid',
                    'payment_gateway' => 'manual',
                    'payment_date' => now(),
                    'valid_from' => now(),
                    'valid_until' => $subscriptionExpiresAt,
                    'status' => 'active',
                ]);
            }

            $school->subscription_id = $subscription->id ?? null;
            $school->save();

            Log::info('School subscription updated by super admin', [
                'school_id' => $school->id,
                'school_name' => $school->name,
                'has_free' => $hasFreeSubscription,
                'type' => $subscriptionType
            ]);

            return response()->json([
                'message' => 'Subscription updated successfully',
                'data' => [
                    'school' => $school,
                    'subscription_status' => $school->getSubscriptionStatus(),
                    'remaining_days' => $school->getRemainingDays(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update subscription: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to update subscription: ' . $e->getMessage()
            ], 500);
        }
    }

    private function calculateAmount(string $type, int $capacity): float
    {
        $pricing = SubscriptionPricing::where('plan_type', $type)
            ->where('is_active', true)
            ->first();

        if (!$pricing) {
            $basePrice = $type === 'termly' ? 20000 : 50000;
            $perStudentPrice = $type === 'termly' ? 2000 : 5000;
        } else {
            $basePrice = $pricing->base_price;
            $perStudentPrice = $pricing->per_student_price;
        }

        return $basePrice + ($capacity * $perStudentPrice);
    }

    public function unlock($id)
    {
        try {
            $school = School::findOrFail($id);
            $school->unlock();

            // Activate any pending subscription
            $pendingSubscription = $school->subscriptions()
                ->where('payment_status', 'paid')
                ->where('status', 'inactive')
                ->first();

            if ($pendingSubscription) {
                $pendingSubscription->update(['status' => 'active']);
            }

            Log::info('School unlocked', [
                'school_id' => $school->id,
                'school_name' => $school->name,
                'unlocked_by' => auth()->id()
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'School unlocked successfully',
                'data' => $school
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to unlock school: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to unlock school: ' . $e->getMessage()
            ], 500);
        }
    }

    public function lock($id)
    {
        if (!auth()->user()->isSuperAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        try {
            $school = School::findOrFail($id);
            $school->lock();

            Log::info('School locked', [
                'school_id' => $school->id,
                'school_name' => $school->name,
                'locked_by' => auth()->id()
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'School locked successfully',
                'data' => $school
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to lock school: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to lock school: ' . $e->getMessage()
            ], 500);
        }
    }

    public function uploadBranding(Request $request, $id)
    {
        $school = School::findOrFail($id);

        if ($request->hasFile('logo')) {
            $logoPath = $request->file('logo')->store('school_logos', 'public');
            $school->logo = $logoPath;
        }

        if ($request->hasFile('principal_signature')) {
            $signPath = $request->file('principal_signature')->store('signatures', 'public');
            $school->principal_signature = $signPath;
        }

        $school->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Branding updated successfully'
        ]);
    }

    /**
     * Get all schools with subscription status (Super Admin only)
     */
    public function getSchoolsWithStatus(Request $request)
    {
        if (!auth()->user()->isSuperAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $schools = School::with(['activeSubscription', 'currentSubscription'])
            ->latest()
            ->paginate(25);

        $schools->map(function ($school) {
            $school->subscription_status = $school->getSubscriptionStatus();
            $school->remaining_days = $school->getRemainingDays();
            $school->student_capacity = $school->getStudentCapacity();
            return $school;
        });

        return response()->json($schools);
    }
}