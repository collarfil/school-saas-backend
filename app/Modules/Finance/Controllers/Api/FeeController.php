<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Fee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FeeController extends Controller
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

        $fees = Fee::where('school_id', $request->school_id)
            ->with(['grade', 'schoolsession', 'school'])
            ->paginate(25);
            
        return response()->json([
            'status' => 'success',
            'data' => $fees
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'grade_id' => 'required|exists:grades,id',
            'school_session_id' => 'required|exists:school_sessions,id',
            'term' => 'required|string',
            'amount' => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'school_id' => 'required|exists:schools,id'
        ]);

        $fee = Fee::create($validated);
        
        return response()->json([
            'status' => 'success',
            'message' => 'Fee created successfully',
            'data' => $fee->load(['grade', 'schoolsession', 'school'])
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $validator = Validator::make([
            'id' => $id,
            'school_id' => $request->school_id
        ], [
            'id' => 'required|exists:fees,id',
            'school_id' => 'required|exists:schools,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $fee = Fee::where('school_id', $request->school_id)
            ->with(['grade', 'schoolsession', 'school'])
            ->find($id);

        if (!$fee) {
            return response()->json([
                'status' => 'error',
                'message' => 'Fee not found or does not belong to this school.'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $fee
        ]);
    }

    public function update(Request $request, $id)
    {
        $validator = Validator::make(array_merge(['id' => $id], $request->all()), [
            'id' => 'required|exists:fees,id',
            'grade_id' => 'sometimes|required|exists:grades,id',
            'school_session_id' => 'sometimes|required|exists:school_sessions,id',
            'term' => 'sometimes|required|string',
            'amount' => 'sometimes|required|numeric|min:0',
            'description' => 'nullable|string',
            'school_id' => 'required|exists:schools,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $fee = Fee::where('school_id', $request->school_id)->find($id);

        if (!$fee) {
            return response()->json([
                'status' => 'error',
                'message' => 'Fee not found or does not belong to this school.'
            ], 404);
        }

        $fee->update($validator->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Fee updated successfully',
            'data' => $fee->fresh(['grade', 'schoolsession', 'school'])
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $validator = Validator::make([
            'id' => $id,
            'school_id' => $request->school_id
        ], [
            'id' => 'required|exists:fees,id',
            'school_id' => 'required|exists:schools,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $fee = Fee::where('school_id', $request->school_id)->find($id);

        if (!$fee) {
            return response()->json([
                'status' => 'error',
                'message' => 'Fee not found or does not belong to this school.'
            ], 404);
        }

        $fee->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Fee deleted successfully'
        ]);
    }
}