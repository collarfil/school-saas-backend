<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Income;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class IncomeController extends Controller
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

        $incomes = Income::where('school_id', $request->school_id)
            ->with('school')
            ->latest()
            ->paginate(25);
            
        return response()->json([
            'status' => 'success',
            'data' => $incomes
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0',
            'date' => 'required|date',
            'note' => 'nullable|string',
            'school_id' => 'required|exists:schools,id'
        ]);

        $income = Income::create($validated);
        
        return response()->json([
            'status' => 'success',
            'message' => 'Income recorded successfully',
            'data' => $income->load('school')
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $validator = Validator::make([
            'id' => $id,
            'school_id' => $request->school_id
        ], [
            'id' => 'required|exists:incomes,id',
            'school_id' => 'required|exists:schools,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $income = Income::where('school_id', $request->school_id)
            ->with('school')
            ->find($id);

        if (!$income) {
            return response()->json([
                'status' => 'error',
                'message' => 'Income not found or does not belong to this school.'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $income
        ]);
    }

    public function update(Request $request, $id)
    {
        $validator = Validator::make(array_merge(['id' => $id], $request->all()), [
            'id' => 'required|exists:incomes,id',
            'amount' => 'sometimes|required|numeric|min:0',
            'date' => 'sometimes|required|date',
            'note' => 'nullable|string',
            'school_id' => 'required|exists:schools,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $income = Income::where('school_id', $request->school_id)->find($id);

        if (!$income) {
            return response()->json([
                'status' => 'error',
                'message' => 'Income not found or does not belong to this school.'
            ], 404);
        }

        $income->update($validator->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Income updated successfully',
            'data' => $income->fresh('school')
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $validator = Validator::make([
            'id' => $id,
            'school_id' => $request->school_id
        ], [
            'id' => 'required|exists:incomes,id',
            'school_id' => 'required|exists:schools,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $income = Income::where('school_id', $request->school_id)->find($id);

        if (!$income) {
            return response()->json([
                'status' => 'error',
                'message' => 'Income not found or does not belong to this school.'
            ], 404);
        }

        $income->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Income deleted successfully'
        ]);
    }
}