<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        try {
            $schoolId = $request->school_id;

            if (!$schoolId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'school_id is required'
                ], 422);
            }

            $employees = Employee::where('school_id', $schoolId)
                ->with(['school', 'subjects', 'grades'])
                ->orderBy('name')
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $employees
            ]);
        } catch (\Exception $e) {
            Log::error('Employee index error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch employees'
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
                'id' => 'required|exists:employees,id',
                'school_id' => 'required|exists:schools,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $employee = Employee::where('school_id', $request->school_id)
                ->with(['school', 'subjects', 'grades'])
                ->find($id);

            if (!$employee) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Employee not found or does not belong to this school.'
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'data' => $employee
            ]);
        } catch (\Exception $e) {
            Log::error('EmployeeController show error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'required|string|max:20',
            'employee_type' => 'required|in:teaching,non_teaching',
            'school_id' => 'required|exists:schools,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $schoolId = $request->school_id;
            $temporaryPassword = $request->phone;

            // Map employee_type to role for employees table
            $role = $request->employee_type === 'teaching' ? 'teacher' : 'non-teaching';

            // Create user account (authentication)
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($temporaryPassword),
                'role' => 'employee',
                'school_id' => $schoolId,
                'phone' => $request->phone,
                'is_active' => true,
                'must_change_password' => true,
            ]);

            // Create employee record (profile) - using your actual table structure
            $employee = Employee::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'role' => $role,  // 'teacher' or 'non-teaching'
                'school_id' => $schoolId,
            ]);

            DB::commit();

            // Prepare credentials for display
            $credentials = [
                'email' => $user->email,
                'password' => $temporaryPassword,
                'name' => $user->name,
                'role' => 'employee',
                'employee_type' => $request->employee_type,
                'note' => 'Use your phone number as temporary password. You will be forced to change it on first login.'
            ];

            return response()->json([
                'message' => 'Employee created successfully',
                'data' => $employee,
                'user' => $user->makeHidden(['password']),
                'credentials' => $credentials
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Employee creation failed: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Employee creation failed: ' . $e->getMessage()
            ], 500);
        }
    }

    private function generateEmployeeId()
    {
        $year = date('Y');
        $lastEmployee = Employee::whereYear('created_at', $year)->latest()->first();
        $lastNumber = $lastEmployee ? intval(substr($lastEmployee->employee_id, -4)) : 0;
        $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        
        return 'EMP/' . $year . '/' . $newNumber;
    }

    public function update(Request $request, $id)
    {
        try {
            $validator = Validator::make(array_merge(['id' => $id], $request->all()), [
                'id' => 'required|exists:employees,id',
                'name' => 'sometimes|required|string|max:255',
                'email' => 'nullable|email|unique:employees,email,' . $id,
                'phone' => 'nullable|string',
                'role' => 'sometimes|required|in:teacher,non-teaching',
                'school_id' => 'required|exists:schools,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $employee = Employee::where('school_id', $request->school_id)->find($id);

            if (!$employee) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Employee not found for this school'
                ], 404);
            }

            $employee->update($validator->validated());

            return response()->json([
                'status' => 'success',
                'message' => 'Employee updated successfully',
                'data' => $employee->fresh(['school', 'subjects', 'grades'])
            ]);

        } catch (\Exception $e) {
            Log::error('EmployeeController update error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update employee'
            ], 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        try {
            $schoolId = $request->school_id;

            $employee = Employee::where('school_id', $schoolId)->find($id);

            if (!$employee) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Employee not found for this school'
                ], 404);
            }

            $employee->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Employee deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Employee destroy error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete employee'
            ], 500);
        }
    }
}