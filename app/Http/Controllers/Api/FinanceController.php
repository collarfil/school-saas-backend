<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Income;
use App\Http\Controllers\Expense;
use App\Http\Controllers\Validator;

class FinanceController extends Controller
{
    public function financeReport(Request $request)
{
    $validator = Validator::make($request->all(), [
        'school_id' => 'required|exists:schools,id',
        'from' => 'required|date',
        'to' => 'required|date'
    ]);

    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }

    $income = Income::where('school_id', $request->school_id)
        ->whereBetween('date', [$request->from, $request->to])
        ->get();

    $expense = Expense::where('school_id', $request->school_id)
        ->whereBetween('date', [$request->from, $request->to])
        ->get();

    return response()->json([
        'status' => 'success',
        'data' => [
            'income' => $income,
            'expense' => $expense,
            'total_income' => $income->sum('amount'),
            'total_expense' => $expense->sum('amount'),
            'balance' => $income->sum('amount') - $expense->sum('amount')
        ]
    ]);
}
}
