<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaystackTransaction;
use Illuminate\Http\Request;

class PaystackTransactionController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $query = PaystackTransaction::with(['school', 'subscription']);

        if ($user->isSchoolAdmin()) {
            $query->where('school_id', $user->school_id);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by date range
        if ($request->has('from_date') && $request->has('to_date')) {
            $query->whereBetween('created_at', [$request->from_date, $request->to_date]);
        }

        $transactions = $query->latest()->paginate(25);

        return response()->json($transactions);
    }

    public function show($id)
    {
        $transaction = PaystackTransaction::with(['school', 'subscription'])->findOrFail($id);
        
        // Authorization check
        $user = auth()->user();
        if ($user->isSchoolAdmin() && $transaction->school_id !== $user->school_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($transaction);
    }

    public function getStats()
    {
        $user = auth()->user();
        $query = PaystackTransaction::query();

        if ($user->isSchoolAdmin()) {
            $query->where('school_id', $user->school_id);
        }

        $stats = [
            'total_transactions' => $query->count(),
            'successful_transactions' => $query->clone()->successful()->count(),
            'failed_transactions' => $query->clone()->failed()->count(),
            'total_amount' => $query->clone()->successful()->sum('amount'),
        ];

        return response()->json($stats);
    }
}