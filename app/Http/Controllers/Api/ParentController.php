<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Parents;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

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
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'nullable|email',
            'phone' => 'required|string|max:20',
            'address' => 'nullable|string',
            'occupation' => 'nullable|string',
            'student_ids' => 'nullable|array',
            'student_ids.*' => 'exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $schoolId = auth()->user()->school_id;
            $temporaryPassword = $request->phone;

            // Create user account (authentication)
            $user = User::create([
                'name' => $request->name,
                'email' => $username . '@school.local',
                'password' => Hash::make($temporaryPassword),
                'role' => 'parent',
                'school_id' => $schoolId,
                'phone' => $request->phone,
                'address' => $request->address,
                'is_active' => true,
                'must_change_password' => true,
            ]);

            // Create parent record (profile) - using the existing columns in your table
            $parent = Parents::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'address' => $request->address,
                'school_id' => $schoolId,
            ]);

            // Link to students using pivot table if needed
            if ($request->has('student_ids')) {
                foreach ($request->student_ids as $studentId) {
                    DB::table('parent_student')->updateOrInsert([
                        'parent_id' => $parent->id,
                        'student_id' => $studentId,
                    ]);
                }
            }

            DB::commit();

            // Prepare credentials for display
            $credentials = [
                'email' => $user->email,
                'password' => $temporaryPassword,
                'name' => $user->name,
                'role' => 'parent',
                'note' => 'Use your phone number as temporary password. You will be forced to change it on first login.'
            ];

            return response()->json([
                'message' => 'Parent created successfully',
                'data' => $parent,
                'user' => $user->makeHidden(['password']),
                'credentials' => $credentials
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Parent creation failed: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Parent creation failed: ' . $e->getMessage()
            ], 500);
        }
    }
    private function generateUsername($name, $schoolId)
    {
        $baseUsername = strtolower(str_replace(' ', '.', $name));
        $username = $baseUsername;
        $counter = 1;
        
        while (User::where('email', $username . '@school.local')->exists()) {
            $username = $baseUsername . $counter;
            $counter++;
        }
        
        return $username;
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

            // Check for duplicate phone within the same school
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

            // Check for duplicate email within the same school
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