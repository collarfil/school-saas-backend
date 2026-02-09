<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Services\UserCreationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

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
            \Log::error('Employee index error: ' . $e->getMessage());
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
            \Log::error('EmployeeController show error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
{
    try {
        $user = auth()->user();

        if (!$user || !$user->school_id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized or school not assigned'
            ], 403);
        }

        /** ------------------------------------
         * VALIDATION
         * -----------------------------------*/
        $validated = $request->validate([
            'name'  => 'required|string|max:255',
            'email' => 'required|email|unique:employees,email',
            'phone' => 'nullable|string|max:20',
            'role'  => 'required|string|max:50',
        ]);

        /** ------------------------------------
         * CREATE EMPLOYEE
         * -----------------------------------*/
        $employee = Employee::create([
            'school_id' => $user->school_id,
            'name'      => $validated['name'],
            'email'     => $validated['email'],
            'phone'     => $validated['phone'] ?? null,
            'role'      => $validated['role'],
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Employee created successfully',
            'data'    => $employee
        ], 201);

    } catch (\Illuminate\Validation\ValidationException $e) {

        // 👇 THIS IS THE IMPORTANT PART
        return response()->json([
            'status' => 'error',
            'message' => $e->errors()['email'][0] ?? 'Validation error',
            'errors' => $e->errors()
        ], 422);

    } catch (\Throwable $e) {

        Log::error('Employee store error', [
            'message' => $e->getMessage(),
            'line' => $e->getLine(),
            'file' => $e->getFile(),
        ]);

        return response()->json([
            'status' => 'error',
            'message' => 'Failed to create employee'
        ], 500);
    }
}


    public function update(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'email' => 'nullable|email|unique:employees,email,' . $id,
                'phone' => 'nullable|string',
                'role' => 'sometimes|required|in:teacher,non-teaching',
                'school_id' => 'required|exists:schools,id',
            ]);

            $employee = Employee::where('school_id', $validated['school_id'])->find($id);

            if (!$employee) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Employee not found for this school'
                ], 404);
            }

            $employee->update($validated);

            return response()->json([
                'status' => 'success',
                'message' => 'Employee updated successfully',
                'data' => $employee->fresh(['school', 'subjects', 'grades'])
            ]);

        } catch (\Exception $e) {
            \Log::error('EmployeeController update error: ' . $e->getMessage());
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
            \Log::error('Employee destroy error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete employee'
            ], 500);
        }
    }
}
