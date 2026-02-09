<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPricing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SubscriptionPricingController extends Controller
{
    /**
     * Get all subscription pricings (available to all authenticated users)
     */
    public function index()
    {
        try {
            $pricings = SubscriptionPricing::all();

            // Format the response
            $formattedPricings = $pricings->map(function ($pricing) {
                return [
                    'id' => $pricing->id,
                    'plan_type' => $pricing->plan_type,
                    'base_price' => (float) $pricing->base_price,
                    'per_student_price' => (float) $pricing->per_student_price,
                    'per_student' => (float) $pricing->per_student_price, // Alias for frontend compatibility
                    'duration_days' => $pricing->duration_days,
                    'duration' => $this->formatDuration($pricing->duration_days),
                    'description' => $pricing->description,
                    'is_active' => (bool) $pricing->is_active,
                    'created_at' => $pricing->created_at->toISOString(),
                    'updated_at' => $pricing->updated_at->toISOString()
                ];
            });

            return response()->json([
                'status' => 'success',
                'data' => $formattedPricings,
                'message' => 'Pricing plans loaded successfully'
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Subscription pricing error: ' . $e->getMessage());
            
            // Fallback data if database query fails
            $fallbackPricings = [
                [
                    'id' => 1,
                    'plan_type' => 'termly',
                    'base_price' => 20000,
                    'per_student_price' => 2000,
                    'per_student' => 2000,
                    'duration_days' => 120,
                    'duration' => '4 months',
                    'description' => 'Per term subscription (4 months)',
                    'is_active' => true,
                    'created_at' => now()->toISOString(),
                    'updated_at' => now()->toISOString()
                ],
                [
                    'id' => 2,
                    'plan_type' => 'yearly',
                    'base_price' => 50000,
                    'per_student_price' => 5000,
                    'per_student' => 5000,
                    'duration_days' => 365,
                    'duration' => '1 year',
                    'description' => 'Annual subscription (1 year)',
                    'is_active' => true,
                    'created_at' => now()->toISOString(),
                    'updated_at' => now()->toISOString()
                ]
            ];

            return response()->json([
                'status' => 'success',
                'data' => $fallbackPricings,
                'message' => 'Using default pricing configuration'
            ]);
        }
    }

    /**
     * Public pricing endpoint (no authentication required)
     */
    public function publicIndex()
    {
        try {
            $pricings = SubscriptionPricing::where('is_active', true)->get();

            // Format the response
            $formattedPricings = $pricings->map(function ($pricing) {
                return [
                    'id' => $pricing->id,
                    'plan_type' => $pricing->plan_type,
                    'base_price' => (float) $pricing->base_price,
                    'per_student_price' => (float) $pricing->per_student_price,
                    'duration_days' => $pricing->duration_days,
                    'duration' => $this->formatDuration($pricing->duration_days),
                    'description' => $pricing->description,
                    'created_at' => $pricing->created_at->toISOString(),
                    'updated_at' => $pricing->updated_at->toISOString()
                ];
            });

            return response()->json([
                'status' => 'success',
                'data' => $formattedPricings,
                'message' => 'Public pricing plans loaded successfully'
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Public pricing error: ' . $e->getMessage());
            
            // Fallback data
            return response()->json([
                'status' => 'success',
                'data' => [],
                'message' => 'Using default pricing'
            ]);
        }
    }

    /**
     * Admin pricing management endpoint
     */
    public function adminIndex()
    {
        try {
            $pricings = SubscriptionPricing::all();

            return response()->json([
                'status' => 'success',
                'data' => $pricings,
                'message' => 'Pricing plans loaded for admin'
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Admin pricing error: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to load pricing plans'
            ], 500);
        }
    }

    /**
     * Create new pricing (Super Admin only)
     */
    public function create(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'plan_type' => 'required|in:termly,yearly',
                'base_price' => 'required|numeric|min:0',
                'per_student_price' => 'required|numeric|min:0',
                'duration_days' => 'required|integer|min:1',
                'description' => 'nullable|string|max:255'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $pricing = SubscriptionPricing::create([
                'plan_type' => $request->plan_type,
                'base_price' => $request->base_price,
                'per_student_price' => $request->per_student_price,
                'duration_days' => $request->duration_days,
                'description' => $request->description,
                'is_active' => false // New pricing is inactive by default
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Pricing created successfully',
                'data' => $pricing
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Create pricing error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create pricing'
            ], 500);
        }
    }

    /**
     * Update pricing (Super Admin only)
     */
    public function update(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'base_price' => 'required|numeric|min:0',
                'per_student_price' => 'required|numeric|min:0',
                'duration_days' => 'required|integer|min:1',
                'description' => 'nullable|string|max:255'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $pricing = SubscriptionPricing::findOrFail($id);
            $pricing->update($request->only([
                'base_price', 'per_student_price', 'duration_days', 'description'
            ]));

            return response()->json([
                'status' => 'success',
                'message' => 'Pricing updated successfully',
                'data' => $pricing
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Update pricing error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update pricing'
            ], 500);
        }
    }

    /**
     * Activate pricing (Super Admin only)
     */
    public function setActive($id)
    {
        try {
            // Deactivate all pricings first
            SubscriptionPricing::query()->update(['is_active' => false]);

            // Activate the selected one
            $pricing = SubscriptionPricing::findOrFail($id);
            $pricing->update(['is_active' => true]);

            return response()->json([
                'status' => 'success',
                'message' => 'Pricing activated successfully'
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Activate pricing error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to activate pricing'
            ], 500);
        }
    }

    /**
     * Format duration in days to human-readable format
     */
    private function formatDuration($days)
    {
        if ($days >= 365) return '1 year';
        if ($days >= 120) return '4 months';
        if ($days >= 90) return '3 months';
        if ($days >= 60) return '2 months';
        if ($days >= 30) return '1 month';
        return "{$days} days";
    }
}