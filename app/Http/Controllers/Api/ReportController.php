<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function studentResults(Request $request)
    {
        // Placeholder: Implement later
        return response()->json(['message' => 'Student results report - coming soon']);
    }

    public function feeReceipts(Request $request)
    {
        // Placeholder: Implement later
        return response()->json(['message' => 'Fee receipts report - coming soon']);
    }

    public function incomeExpenditure(Request $request)
    {
        // Placeholder: Implement later
        return response()->json(['message' => 'Income & Expenditure report - coming soon']);
    }

    public function academic(Request $request)
    {
        // Placeholder: Implement later
        return response()->json(['message' => 'Academic performance report - coming soon']);
    }

    public function employees(Request $request)
    {
        // Placeholder: Implement later
        return response()->json(['message' => 'Employees report - coming soon']);
    }
}