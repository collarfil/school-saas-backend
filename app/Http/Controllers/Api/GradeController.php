<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Grade;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class GradeController extends Controller
{
    public function index(Request $request)
    {
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

        $grades = Grade::where('school_id', $request->school_id)
            ->with(['section', 'school', 'students', 'subjects'])
            ->get();
            
        return response()->json([
            'status' => 'success',
            'data' => $grades
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                // Make name unique per school, not globally
                Rule::unique('grades')->where(function ($query) use ($request) {
                    return $query->where('school_id', $request->school_id);
                })
            ],
            'section_id' => 'required|exists:sections,id',
            'school_id' => 'required|exists:schools,id'
        ]);


        // Check for duplicate name within the same school
        $existingGrade = Grade::where('school_id', $validated['school_id'])
            ->where('name', $validated['name'])
            ->first();
            
        if ($existingGrade) {
            return response()->json([
                'status' => 'error',
                'message' => 'A grade with this name already exists in this school.'
            ], 422);
        }

        $grade = Grade::create($validated);
        
        return response()->json([
            'status' => 'success',
            'message' => 'Grade created successfully',
            'data' => $grade->load(['section', 'school'])
        ], 201);
    }

     public function show(Request $request, $id)
    {
        $validator = Validator::make([
            'id' => $id,
            'school_id' => $request->school_id
        ], [
            'id' => 'required|exists:grades,id',
            'school_id' => 'required|exists:schools,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $grade = Grade::where('school_id', $request->school_id)
            ->with(['section', 'school', 'students', 'subjects'])
            ->find($id);

        if (!$grade) {
            return response()->json([
                'status' => 'error',
                'message' => 'Grade not found or does not belong to this school.'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $grade
        ]);
    }

    public function update(Request $request, $id)
    {
        $validator = Validator::make(array_merge(['id' => $id], $request->all()), [
            'id' => 'required|exists:grades,id',
            'name' => [
                'sometimes',
                'required',
                'string',
                // Make name unique per school (excluding current grade)
                Rule::unique('grades')->where(function ($query) use ($request) {
                    return $query->where('school_id', $request->school_id);
                })->ignore($id)
            ],
            'section_id' => 'sometimes|required|exists:sections,id',
            'school_id' => 'required|exists:schools,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $grade = Grade::where('school_id', $request->school_id)->find($id);

        if (!$grade) {
            return response()->json([
                'status' => 'error',
                'message' => 'Grade not found or does not belong to this school.'
            ], 404);
        }

        // Check for duplicate name within the same school (excluding current grade)
        if ($request->has('name')) {
            $existingGrade = Grade::where('school_id', $request->school_id)
                ->where('name', $request->name)
                ->where('id', '!=', $id)
                ->first();
                
            if ($existingGrade) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'A grade with this name already exists in this school.'
                ], 422);
            }
        }

        $grade->update($validator->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Grade updated successfully',
            'data' => $grade->fresh(['section', 'school'])
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $validator = Validator::make([
            'id' => $id,
            'school_id' => $request->school_id
        ], [
            'id' => 'required|exists:grades,id',
            'school_id' => 'required|exists:schools,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $grade = Grade::where('school_id', $request->school_id)->find($id);

        if (!$grade) {
            return response()->json([
                'status' => 'error',
                'message' => 'Grade not found or does not belong to this school.'
            ], 404);
        }

        // Check if grade has students before deleting
        if ($grade->students()->count() > 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'Cannot delete grade because it has students assigned. Please reassign students first.'
            ], 422);
        }

        $grade->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Grade deleted successfully'
        ]);
    }
}