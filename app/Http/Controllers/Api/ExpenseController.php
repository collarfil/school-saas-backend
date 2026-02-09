<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ExpenseController extends Controller
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

        $expenses = Expense::where('school_id', $request->school_id)
            ->with('school')
            ->latest()
            ->paginate(25);
            
        return response()->json([
            'status' => 'success',
            'data' => $expenses
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

        $expense = Expense::create($validated);
        
        return response()->json([
            'status' => 'success',
            'message' => 'Expense recorded successfully',
            'data' => $expense->load('school')
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $validator = Validator::make([
            'id' => $id,
            'school_id' => $request->school_id
        ], [
            'id' => 'required|exists:expenses,id',
            'school_id' => 'required|exists:schools,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $expense = Expense::where('school_id', $request->school_id)
            ->with('school')
            ->find($id);

        if (!$expense) {
            return response()->json([
                'status' => 'error',
                'message' => 'Expense not found or does not belong to this school.'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $expense
        ]);
    }

    public function update(Request $request, $id)
    {
        $validator = Validator::make(array_merge(['id' => $id], $request->all()), [
            'id' => 'required|exists:expenses,id',
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

        $expense = Expense::where('school_id', $request->school_id)->find($id);

        if (!$expense) {
            return response()->json([
                'status' => 'error',
                'message' => 'Expense not found or does not belong to this school.'
            ], 404);
        }

        $expense->update($validator->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Expense updated successfully',
            'data' => $expense->fresh('school')
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $validator = Validator::make([
            'id' => $id,
            'school_id' => $request->school_id
        ], [
            'id' => 'required|exists:expenses,id',
            'school_id' => 'required|exists:schools,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $expense = Expense::where('school_id', $request->school_id)->find($id);

        if (!$expense) {
            return response()->json([
                'status' => 'error',
                'message' => 'Expense not found or does not belong to this school.'
            ], 404);
        }

        $expense->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Expense deleted successfully'
        ]);
    }
}