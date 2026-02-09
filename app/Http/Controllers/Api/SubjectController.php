<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class SubjectController extends Controller
{
    public function index(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'school_id' => 'required|exists:schools,id',
                'grade_id' => 'nullable|exists:grades,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $query = Subject::with(['grade', 'school'])
                ->where('school_id', $request->school_id);

            // Filter by grade if specified
            if ($request->filled('grade_id')) {
                $query->where('grade_id', $request->grade_id);
            }

            $subjects = $query->orderBy('grade_id')
                ->orderBy('name')
                ->get();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Subjects retrieved successfully',
                'data' => $subjects
            ]);
            
        } catch (\Exception $e) {
            Log::error('SubjectController index error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch subjects',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'grade_id' => 'required|exists:grades,id',
                'school_id' => 'required|exists:schools,id'
            ]);
            
            // Check if subject with same name and grade already exists for this school
            $existingSubject = Subject::where('school_id', $validated['school_id'])
                ->where('name', $validated['name'])
                ->where('grade_id', $validated['grade_id'])
                ->first();
            
            if ($existingSubject) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'A subject with this name already exists for the selected grade in this school.'
                ], 422);
            }
            
            $subject = Subject::create($validated);
            
            return response()->json([
                'status' => 'success',
                'message' => 'Subject created successfully',
                'data' => $subject->load(['grade', 'school'])
            ], 201);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('SubjectController store error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create subject',
                'error' => $e->getMessage()
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
                'id' => 'required|exists:subjects,id',
                'school_id' => 'required|exists:schools,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $subject = Subject::with(['grade', 'school'])
                ->where('school_id', $request->school_id)
                ->find($id);

            if (!$subject) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Subject not found or does not belong to this school.'
                ], 404);
            }
            
            return response()->json([
                'status' => 'success',
                'message' => 'Subject retrieved successfully',
                'data' => $subject
            ]);
            
        } catch (\Exception $e) {
            Log::error('SubjectController show error: ' . $e->getMessage());
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
                'id' => 'required|exists:subjects,id',
                'name' => 'sometimes|required|string|max:255',
                'grade_id' => 'sometimes|required|exists:grades,id',
                'school_id' => 'required|exists:schools,id'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $subject = Subject::where('school_id', $request->school_id)->find($id);

            if (!$subject) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Subject not found or does not belong to this school.'
                ], 404);
            }
            
            // If name is being updated, check for duplicates in the same school and grade
            if ($request->has('name') && $request->has('grade_id')) {
                $existingSubject = Subject::where('school_id', $request->school_id)
                    ->where('name', $request->name)
                    ->where('grade_id', $request->grade_id)
                    ->where('id', '!=', $id)
                    ->first();
                
                if ($existingSubject) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'A subject with this name already exists for the selected grade in this school.'
                    ], 422);
                }
            }
            
            $subject->update($validator->validated());
            
            return response()->json([
                'status' => 'success',
                'message' => 'Subject updated successfully',
                'data' => $subject->fresh()->load(['grade', 'school'])
            ]);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('SubjectController update error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update subject',
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
                'id' => 'required|exists:subjects,id',
                'school_id' => 'required|exists:schools,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $subject = Subject::where('school_id', $request->school_id)->find($id);

            if (!$subject) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Subject not found or does not belong to this school.'
                ], 404);
            }

            $subject->delete();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Subject deleted successfully'
            ]);
            
        } catch (\Exception $e) {
            Log::error('SubjectController destroy error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete subject',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    public function byGrade(Request $request, $gradeId)
    {
        try {
            $validator = Validator::make([
                'grade_id' => $gradeId,
                'school_id' => $request->school_id
            ], [
                'grade_id' => 'required|exists:grades,id',
                'school_id' => 'required|exists:schools,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $subjects = Subject::where('school_id', $request->school_id)
                ->where('grade_id', $gradeId)
                ->orderBy('name')
                ->get();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Subjects retrieved successfully',
                'data' => $subjects
            ]);
            
        } catch (\Exception $e) {
            Log::error('SubjectController byGrade error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch subjects for this grade'
            ], 500);
        }
    }
    
    public function byGradeWithDetails(Request $request, $gradeId)
    {
        try {
            $validator = Validator::make([
                'grade_id' => $gradeId,
                'school_id' => $request->school_id
            ], [
                'grade_id' => 'required|exists:grades,id',
                'school_id' => 'required|exists:schools,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $subjects = Subject::with(['grade', 'school'])
                ->where('school_id', $request->school_id)
                ->where('grade_id', $gradeId)
                ->orderBy('name')
                ->get();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Subjects retrieved successfully',
                'data' => $subjects
            ]);
            
        } catch (\Exception $e) {
            Log::error('SubjectController byGradeWithDetails error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch subjects for this grade'
            ], 500);
        }
    }
}