<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmployeeSubject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class EmployeeSubjectController extends Controller
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

            $mappings = EmployeeSubject::where('school_id', $request->school_id)
                ->with(['employee', 'subject'])
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $mappings
            ]);
        } catch (\Exception $e) {
            Log::error('EmployeeSubject index error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch mappings'
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'employee_id' => 'required|exists:employees,id',
                'subject_id' => 'required|exists:subjects,id',
                'school_id' => 'required|exists:schools,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Check if mapping already exists
            $exists = EmployeeSubject::where('employee_id', $request->employee_id)
                ->where('subject_id', $request->subject_id)
                ->where('school_id', $request->school_id)
                ->exists();

            if ($exists) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'This employee is already assigned to this subject'
                ], 422);
            }

            $mapping = EmployeeSubject::create([
                'employee_id' => $request->employee_id,
                'subject_id' => $request->subject_id,
                'school_id' => $request->school_id
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Employee-Subject mapping created successfully',
                'data' => $mapping->load(['employee', 'subject'])
            ], 201);

        } catch (\Exception $e) {
            Log::error('EmployeeSubject store error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create mapping: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id, Request $request)
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

            $mapping = EmployeeSubject::where('id', $id)
                ->where('school_id', $request->school_id)
                ->first();

            if (!$mapping) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Mapping not found'
                ], 404);
            }

            $mapping->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Mapping deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('EmployeeSubject destroy error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete mapping'
            ], 500);
        }
    }
}