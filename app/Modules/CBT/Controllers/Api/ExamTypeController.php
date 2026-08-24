<?php

namespace App\Modules\Cbt\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Cbt\Models\ExamType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ExamTypeController extends Controller
{
   public function index(Request $request)
{
    $validator = Validator::make($request->all(), [
        'school_id' => 'required|exists:schools,id',
        'school_session_id' => 'required|exists:school_sessions,id', // Added requirement
    ]);

    if ($validator->fails()) {
        return response()->json([
            'status' => 'error',
            'message' => 'Validation failed',
            'errors' => $validator->errors()
        ], 422);
    }

    try {
        $examTypes = ExamType::where('school_id', $request->school_id)
            ->where('school_session_id', $request->school_session_id) // Filtered
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $examTypes
        ]);
    } catch (\Exception $e) {
        Log::error('ExamTypeController index error: ' . $e->getMessage());
        return response()->json([
            'status' => 'error',
            'message' => 'Failed to retrieve scoped exam configurations.',
            'error' => $e->getMessage()
        ], 500);
    }
}

    public function store(Request $request)
{
    $validator = Validator::make($request->all(), [
        'school_id'         => 'required|exists:schools,id',
        'school_session_id' => 'required|exists:school_sessions,id',
        'name'              => 'required|string',
        'slug'              => 'required|string',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'status'  => 'error',
            'message' => 'Validation failed',
            'errors'  => $validator->errors()
        ], 422);
    }

    try {
        // Check if an exam type with this slug already exists for this school session
        $exists = ExamType::where('school_id', $request->school_id)
            ->where('school_session_id', $request->school_session_id)
            ->where('slug', $request->slug)
            ->exists();

        // ✅ Updated $existing -> $exists
        if ($exists) {
            return response()->json([
                'status'  => 'error',
                'message' => 'An exam type with this name already exists for this school session.'
            ], 422);
        }

        $examType = ExamType::create($request->only([
            'school_id', 
            'school_session_id', 
            'name', 
            'slug'
        ]));

        return response()->json([
            'status'  => 'success',
            'message' => 'Session-scoped exam layout generated cleanly.',
            'data'    => $examType
        ], 201);

    } catch (\Exception $e) {
        Log::error('ExamTypeController store error: ' . $e->getMessage());
        return response()->json([
            'status'  => 'error',
            'message' => 'Failed to compile exam configuration structural map.',
            'error'   => $e->getMessage()
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
                'id' => 'required|exists:exam_types,id',
                'school_id' => 'required|exists:schools,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $examType = ExamType::where('school_id', $request->school_id)->find($id);

            if (!$examType) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Exam type not found or does not belong to this school.'
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'data' => $examType
            ]);
        } catch (\Exception $e) {
            Log::error('ExamTypeController show error: ' . $e->getMessage());
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
                'id' => 'required|exists:exam_types,id',
                'name' => 'sometimes|string|max:255',
                'school_id' => 'required|exists:schools,id',
                'school_session_id' => 'required|exists:school_sessions,id', // Added
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $examType = ExamType::where('school_id', $request->school_id)
                ->where('school_session_id', $request->school_session_id)
                ->find($id);

            if (!$examType) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Exam type not found or does not belong to this school.'
                ], 404);
            }

            if ($request->filled('name')) {
                $slug = Str::slug($request->name);
                $existing = ExamType::where('school_id', $request->school_id)
                    ->where('slug', $slug)
                    ->where('school_session_id', $request->school_session_id)
                    ->where('id', '!=', $id)
                    ->first();

                if ($existing) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Another exam type with this name already exists.'
                    ], 422);
                }
                
                $examType->name = $request->name;
                $examType->slug = $slug;
            }

            $examType->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Exam type updated successfully',
                'data' => $examType
            ]);
        } catch (\Exception $e) {
            Log::error('ExamTypeController update error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update exam type',
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
                'id' => 'required|exists:exam_types,id',
                'school_id' => 'required|exists:schools,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $examType = ExamType::where('school_id', $request->school_id)->find($id);

            if (!$examType) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Exam type not found or does not belong to this school.'
                ], 404);
            }

            if ($examType->exams()->count() > 0) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Cannot delete exam type because it is linked to existing active exams.'
                ], 422);
            }

            $examType->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Exam type deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('ExamTypeController destroy error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete exam type',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}