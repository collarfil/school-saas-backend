<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\FeePayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'school_id' => 'required|exists:schools,id',
            'status' => 'nullable|in:pending,successful,failed',
            'method' => 'nullable|string',
            'reference' => 'nullable|string',
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date',
            'type' => 'nullable|in:fee_payment,subscription,other'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $query = Transaction::where('school_id', $request->school_id)
            ->with(['school', 'feePayment.student', 'feePayment.fee.grade']);

        // Apply filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('method')) {
            $query->where('method', 'like', '%' . $request->method . '%');
        }

        if ($request->filled('reference')) {
            $query->where('reference', 'like', '%' . $request->reference . '%');
        }

        // Filter by type
        if ($request->filled('type')) {
            if ($request->type === 'fee_payment') {
                $query->whereHas('feePayment');
            } elseif ($request->type === 'subscription') {
                $query->where('reference', 'like', 'SUB-%');
            } else {
                $query->whereDoesntHave('feePayment')
                    ->where('reference', 'not like', 'SUB-%');
            }
        }

        if ($request->filled('from_date') && $request->filled('to_date')) {
            $query->whereBetween('created_at', [$request->from_date, $request->to_date]);
        } elseif ($request->filled('from_date')) {
            $query->where('created_at', '>=', $request->from_date);
        } elseif ($request->filled('to_date')) {
            $query->where('created_at', '<=', $request->to_date);
        }

        $transactions = $query->latest()->paginate(25);
        
        // Add fee breakdown for fee payment transactions
        foreach ($transactions as $transaction) {
            if ($transaction->feePayment) {
                // Get all fee payments with this reference (transaction reference = payment_reference)
                $feePayments = FeePayment::where('payment_reference', $transaction->reference)
                    ->with(['fee', 'student'])
                    ->get();
                    
                if ($feePayments->count() > 0) {
                    $transaction->fee_payments = $feePayments;
                    $transaction->total_fees = $feePayments->count();
                    $transaction->type = 'fee_payment';
                    
                    // Create fee breakdown
                    $feeBreakdown = $feePayments->map(function ($feePayment) {
                        return [
                            'fee_description' => $feePayment->fee ? $feePayment->fee->description : 'Fee',
                            'amount' => $feePayment->amount_paid,
                            'grade' => $feePayment->fee && $feePayment->fee->grade ? $feePayment->fee->grade->name : null
                        ];
                    });
                    
                    $transaction->fee_breakdown = $feeBreakdown;
                }
            } else {
                $transaction->type = str_starts_with($transaction->reference, 'SUB-') ? 'subscription' : 'other';
            }
        }
        
        return response()->json([
            'status' => 'success',
            'data' => $transactions
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'reference' => 'required|string',
            'amount' => 'required|numeric|min:0',
            'method' => 'nullable|string',
            'status' => 'required|in:pending,successful,failed',
            'response_payload' => 'nullable|array',
            'school_id' => 'required|exists:schools,id'
        ]);

        // Check if reference is unique within the school
        $existingTransaction = Transaction::where('school_id', $validated['school_id'])
            ->where('reference', $validated['reference'])
            ->first();
            
        if ($existingTransaction) {
            return response()->json([
                'status' => 'error',
                'message' => 'A transaction with this reference already exists in this school.'
            ], 422);
        }

        $transaction = Transaction::create($validated);
        
        return response()->json([
            'status' => 'success',
            'message' => 'Transaction logged successfully',
            'data' => $transaction->load(['school', 'feePayment.student'])
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $validator = Validator::make([
            'id' => $id,
            'school_id' => $request->school_id
        ], [
            'id' => 'required|exists:transactions,id',
            'school_id' => 'required|exists:schools,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $transaction = Transaction::where('school_id', $request->school_id)
            ->with(['school', 'feePayment.student', 'feePayment.fee.grade'])
            ->find($id);

        if (!$transaction) {
            return response()->json([
                'status' => 'error',
                'message' => 'Transaction not found or does not belong to this school.'
            ], 404);
        }

        // Add fee breakdown if it's a fee payment transaction
        if ($transaction->feePayment) {
            // Get all fee payments with this reference
            $feePayments = FeePayment::where('payment_reference', $transaction->reference)
                ->with(['fee', 'student'])
                ->get();
                
            if ($feePayments->count() > 0) {
                $transaction->fee_payments = $feePayments;
                $transaction->total_fees = $feePayments->count();
                
                $feeBreakdown = $feePayments->map(function ($feePayment) {
                    return [
                        'fee_description' => $feePayment->fee ? $feePayment->fee->description : 'Fee',
                        'amount' => $feePayment->amount_paid,
                        'grade' => $feePayment->fee && $feePayment->fee->grade ? $feePayment->fee->grade->name : null
                    ];
                });
                
                $transaction->fee_breakdown = $feeBreakdown;
            }
        }

        return response()->json([
            'status' => 'success',
            'data' => $transaction
        ]);
    }

    public function update(Request $request, $id)
    {
        $validator = Validator::make(array_merge(['id' => $id], $request->all()), [
            'id' => 'required|exists:transactions,id',
            'status' => 'sometimes|required|in:pending,successful,failed',
            'method' => 'nullable|string',
            'response_payload' => 'nullable|array',
            'school_id' => 'required|exists:schools,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $transaction = Transaction::where('school_id', $request->school_id)->find($id);

        if (!$transaction) {
            return response()->json([
                'status' => 'error',
                'message' => 'Transaction not found or does not belong to this school.'
            ], 404);
        }

        // Prevent updating certain fields
        $updatableFields = $validator->validated();
        unset($updatableFields['reference']); // Reference should not be changed
        unset($updatableFields['amount']); // Amount should not be changed
        
        $transaction->update($updatableFields);

        // If this is a fee payment transaction, update the fee payments status too
        if ($request->has('status') && $transaction->feePayment) {
            $feePaymentStatus = $request->status === 'successful' ? 'paid' : 
                               ($request->status === 'pending' ? 'pending' : 'failed');
            
            FeePayment::where('payment_reference', $transaction->reference)
                ->update(['status' => $feePaymentStatus]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Transaction updated successfully',
            'data' => $transaction->fresh(['school', 'feePayment.student'])
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $validator = Validator::make([
            'id' => $id,
            'school_id' => $request->school_id
        ], [
            'id' => 'required|exists:transactions,id',
            'school_id' => 'required|exists:schools,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $transaction = Transaction::where('school_id', $request->school_id)->find($id);

        if (!$transaction) {
            return response()->json([
                'status' => 'error',
                'message' => 'Transaction not found or does not belong to this school.'
            ], 404);
        }

        // Check if transaction is linked to fee payments
        if ($transaction->feePayment) {
            return response()->json([
                'status' => 'error',
                'message' => 'Cannot delete transaction because it is linked to fee payments. Delete the fee payments first.'
            ], 422);
        }

        $transaction->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Transaction deleted successfully'
        ]);
    }

    public function getStats(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'school_id' => 'required|exists:schools,id',
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $query = Transaction::where('school_id', $request->school_id);

        if ($request->filled('from_date') && $request->filled('to_date')) {
            $query->whereBetween('created_at', [$request->from_date, $request->to_date]);
        }

        $stats = [
            'total_transactions' => $query->count(),
            'successful_transactions' => $query->clone()->where('status', 'successful')->count(),
            'failed_transactions' => $query->clone()->where('status', 'failed')->count(),
            'pending_transactions' => $query->clone()->where('status', 'pending')->count(),
            'total_amount' => $query->clone()->where('status', 'successful')->sum('amount'),
            'average_amount' => $query->clone()->where('status', 'successful')->avg('amount'),
        ];

        // Get transaction type breakdown
        $feePaymentTransactions = $query->clone()->whereHas('feePayment')->count();
        $subscriptionTransactions = $query->clone()->where('reference', 'like', 'SUB-%')->count();
        $otherTransactions = $query->clone()->whereDoesntHave('feePayment')
            ->where('reference', 'not like', 'SUB-%')
            ->count();

        $stats['type_breakdown'] = [
            'fee_payments' => $feePaymentTransactions,
            'subscriptions' => $subscriptionTransactions,
            'other' => $otherTransactions
        ];

        // Get payment method breakdown
        $methodBreakdown = Transaction::where('school_id', $request->school_id)
            ->where('status', 'successful')
            ->select('method', DB::raw('COUNT(*) as count'), DB::raw('SUM(amount) as total'))
            ->groupBy('method')
            ->get();

        $stats['payment_method_breakdown'] = $methodBreakdown;

        return response()->json([
            'status' => 'success',
            'data' => $stats
        ]);
    }

    // New method to get fee payment transactions only
    public function getFeePaymentTransactions(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'school_id' => 'required|exists:schools,id',
            'student_id' => 'nullable|exists:students,id',
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $query = Transaction::where('school_id', $request->school_id)
            ->whereHas('feePayment')
            ->with(['feePayment.student', 'feePayment.fee.grade']);

        if ($request->filled('student_id')) {
            $query->whereHas('feePayment', function ($q) use ($request) {
                $q->where('student_id', $request->student_id);
            });
        }

        if ($request->filled('from_date') && $request->filled('to_date')) {
            $query->whereBetween('created_at', [$request->from_date, $request->to_date]);
        }

        $transactions = $query->latest()->paginate(25);

        // Add fee breakdown for each transaction
        foreach ($transactions as $transaction) {
            if ($transaction->feePayment) {
                // Get all fee payments with this reference
                $feePayments = FeePayment::where('payment_reference', $transaction->reference)
                    ->with(['fee', 'student'])
                    ->get();
                    
                if ($feePayments->count() > 0) {
                    $transaction->fee_payments = $feePayments;
                    $transaction->total_fees = $feePayments->count();
                    
                    $feeBreakdown = $feePayments->map(function ($feePayment) {
                        return [
                            'fee_description' => $feePayment->fee ? $feePayment->fee->description : 'Fee',
                            'amount' => $feePayment->amount_paid,
                            'grade' => $feePayment->fee && $feePayment->fee->grade ? $feePayment->fee->grade->name : null
                        ];
                    });
                    
                    $transaction->fee_breakdown = $feeBreakdown;
                }
            }
        }

        return response()->json([
            'status' => 'success',
            'data' => $transactions
        ]);
    }
}