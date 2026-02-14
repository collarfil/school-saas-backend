<?php

namespace App\Http\Controllers\Api;

use App\Services\UserCreationService;
use App\Http\Controllers\Controller;
use App\Models\Parents;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ParentController extends Controller
{
    public function index(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'school_id' => 'required|exists:schools,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $parents = Parents::where('school_id', $request->school_id)
                ->with(['school', 'students'])
                ->orderBy('name')
                ->get();
                
            return response()->json([
                'status' => 'success',
                'data' => $parents
            ]);
        } catch (\Exception $e) {
            Log::error('ParentController error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch parents',
                'error' => $e->getMessage()
            ], 500);
        }
    }

        public function store(Request $request)
        {
            try {
                $validated = $request->validate([
                    'name' => 'required|string|max:255',
                    'phone' => 'required|string|max:20',
                    'email' => 'nullable|email',
                    'address' => 'nullable|string',
                    'school_id' => 'required|exists:schools,id',
                    // ✅ NEW
                    'delivery_method' => 'nullable|in:email,print,both'
                ]);

                $exists = Parents::where('school_id', $validated['school_id'])
                    ->where('phone', $validated['phone'])
                    ->exists();

                if ($exists) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Parent already exists with this phone'
                    ], 422);
                }

                $parent = Parents::create($validated);

                // ✅ UPDATED
                $deliveryMethod = $validated['delivery_method'] ?? 'print';

                $result = UserCreationService::createParentUser(
                    $parent,
                    $deliveryMethod
                );

                return response()->json([
                    'status' => 'success',
                    'message' => 'Parent created successfully',
                    'data' => $parent->load(['school','students']),
                    // ✅ ONLY RETURN WHEN NOT EMAIL-ONLY
                    'credentials' => $deliveryMethod !== 'email' ? [
                        'name' => $result['user']->name,
                        'username' => $result['user']->email,
                        'password' => $result['plain_password'],
                    ] : null
                ], 201);

            } catch (\Exception $e) {
                Log::error($e->getMessage());
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to create parent'
                ], 500);
            }
        }


    public function show(Request $request, $id)
    {
        try {
            $validator = Validator::make([
                'id' => $id,
                'school_id' => $request->school_id
            ], [
                'id' => 'required|exists:parents,id',
                'school_id' => 'required|exists:schools,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $parent = Parents::where('school_id', $request->school_id)
                ->with(['school', 'students'])
                ->find($id);

            if (!$parent) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Parent not found or does not belong to this school.'
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'data' => $parent
            ]);
        } catch (\Exception $e) {
            Log::error('ParentController show error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $validator = Validator::make(array_merge(['id' => $id], $request->all()), [
                'id' => 'required|exists:parents,id',
                'name' => 'sometimes|required|string|max:255',
                'phone' => 'sometimes|required|string|max:20',
                'email' => 'nullable|email',
                'address' => 'nullable|string',
                'school_id' => 'required|exists:schools,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $parent = Parents::where('school_id', $request->school_id)->find($id);

            if (!$parent) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Parent not found or does not belong to this school.'
                ], 404);
            }

            // Check for duplicate phone within the same school (excluding current parent)
            if ($request->has('phone')) {
                $existingParent = Parents::where('school_id', $request->school_id)
                    ->where('phone', $request->phone)
                    ->where('id', '!=', $id)
                    ->first();
                    
                if ($existingParent) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'A parent with this phone number already exists in this school.'
                    ], 422);
                }
            }

            // Check for duplicate email within the same school (excluding current parent)
            if ($request->has('email') && !empty($request->email)) {
                $existingParentByEmail = Parents::where('school_id', $request->school_id)
                    ->where('email', $request->email)
                    ->where('id', '!=', $id)
                    ->first();
                    
                if ($existingParentByEmail) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'A parent with this email already exists in this school.'
                    ], 422);
                }
            }

            $parent->update($validator->validated());
            
            return response()->json([
                'status' => 'success',
                'message' => 'Parent updated successfully', 
                'data' => $parent->fresh(['school', 'students'])
            ]);
            
        } catch (\Exception $e) {
            Log::error('ParentController update error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update parent',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        try {
            $validator = Validator::make([
                'id' => $id,
                'school_id' => $request->school_id
            ], [
                'id' => 'required|exists:parents,id',
                'school_id' => 'required|exists:schools,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $parent = Parents::where('school_id', $request->school_id)
                ->with(['students'])
                ->find($id);

            if (!$parent) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Parent not found or does not belong to this school.'
                ], 404);
            }

            // Check if parent has students before deleting
            if ($parent->students->count() > 0) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Cannot delete parent because they have students assigned. Please reassign students first.'
                ], 422);
            }

            $parent->delete();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Parent deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('ParentController destroy error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete parent',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}