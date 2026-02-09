<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Section;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SectionController extends Controller
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

        $sections = Section::where('school_id', $request->school_id)
            ->with('school')
            ->get();
            
        return response()->json([
            'status' => 'success',
            'data' => $sections
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'school_id' => 'required|exists:schools,id'
        ]);

        // Check for duplicate section name within the same school
        $existingSection = Section::where('school_id', $validated['school_id'])
            ->where('name', $validated['name'])
            ->first();
            
        if ($existingSection) {
            return response()->json([
                'status' => 'error',
                'message' => 'A section with this name already exists in this school.'
            ], 422);
        }

        $section = Section::create($validated);
        
        return response()->json([
            'status' => 'success',
            'message' => 'Section created successfully',
            'data' => $section->load('school')
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $validator = Validator::make([
            'id' => $id,
            'school_id' => $request->school_id
        ], [
            'id' => 'required|exists:sections,id',
            'school_id' => 'required|exists:schools,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $section = Section::where('school_id', $request->school_id)
            ->with('school')
            ->find($id);

        if (!$section) {
            return response()->json([
                'status' => 'error',
                'message' => 'Section not found or does not belong to this school.'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $section
        ]);
    }

    public function update(Request $request, $id)
    {
        $validator = Validator::make(array_merge(['id' => $id], $request->all()), [
            'id' => 'required|exists:sections,id',
            'name' => 'sometimes|required|string|max:255',
            'school_id' => 'required|exists:schools,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $section = Section::where('school_id', $request->school_id)->find($id);

        if (!$section) {
            return response()->json([
                'status' => 'error',
                'message' => 'Section not found or does not belong to this school.'
            ], 404);
        }

        // Check for duplicate section name within the same school (excluding current section)
        if ($request->has('name')) {
            $existingSection = Section::where('school_id', $request->school_id)
                ->where('name', $request->name)
                ->where('id', '!=', $id)
                ->first();
                
            if ($existingSection) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'A section with this name already exists in this school.'
                ], 422);
            }
        }

        $section->update($validator->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Section updated successfully',
            'data' => $section->fresh('school')
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $validator = Validator::make([
            'id' => $id,
            'school_id' => $request->school_id
        ], [
            'id' => 'required|exists:sections,id',
            'school_id' => 'required|exists:schools,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $section = Section::where('school_id', $request->school_id)->find($id);

        if (!$section) {
            return response()->json([
                'status' => 'error',
                'message' => 'Section not found or does not belong to this school.'
            ], 404);
        }

        // Check if section has grades before deleting
        if ($section->grades()->count() > 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'Cannot delete section because it has grades assigned. Please reassign grades first.'
            ], 422);
        }

        $section->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Section deleted successfully'
        ]);
    }
}