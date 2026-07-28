<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FeePayment;
use App\Models\Fee;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FeePaymentController extends Controller
{
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'school_id' => 'required|exists:schools,id',
            'student_id' => 'nullable|exists:students,id',
            'status' => 'nullable|in:paid,pending,failed',
            'payment_method' => 'nullable|string',
            'payment_reference' => 'nullable|string',
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date',
            'group_by_reference' => 'nullable|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $query = FeePayment::where('school_id', $request->school_id)
            ->with(['student', 'fee', 'fee.grade', 'school', 'transaction']);

        // Apply filters
        if ($request->filled('student_id')) {
            $query->where('student_id', $request->student_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('payment_method')) {
            $query->where('payment_method', 'like', '%' . $request->payment_method . '%');
        }

        if ($request->filled('payment_reference')) {
            $query->where('payment_reference', 'like', '%' . $request->payment_reference . '%');
        }

        if ($request->filled('from_date') && $request->filled('to_date')) {
            $query->whereBetween('payment_date', [$request->from_date, $request->to_date]);
        } elseif ($request->filled('from_date')) {
            $query->where('payment_date', '>=', $request->from_date);
        } elseif ($request->filled('to_date')) {
            $query->where('payment_date', '<=', $request->to_date);
        }

        // Check if we should group by reference
        if ($request->boolean('group_by_reference')) {
            $groupedPayments = $query->select([
                'payment_reference',
                DB::raw('MIN(id) as id'),
                DB::raw('COUNT(*) as payment_count'),
                DB::raw('SUM(amount_paid) as total_amount'),
                DB::raw('MIN(payment_date) as payment_date'),
                DB::raw('MIN(status) as status'),
                DB::raw('MIN(payment_method) as payment_method'),
                DB::raw('MIN(student_id) as student_id')
            ])
            ->groupBy('payment_reference')
            ->orderByDesc('payment_date')
            ->paginate(25);

            // Load relationships for each group
            foreach ($groupedPayments as $payment) {
                if ($payment->student_id) {
                    $payment->load('student');
                }
                
                // Get all payments in this group
                $paymentDetails = FeePayment::where('payment_reference', $payment->payment_reference)
                    ->with(['fee'])
                    ->get();
                $payment->fee_details = $paymentDetails->pluck('fee');
                $payment->payment_count = $paymentDetails->count();
            }

            return response()->json([
                'status' => 'success',
                'data' => $groupedPayments
            ]);
        }

        $payments = $query->orderByDesc('payment_date')->paginate(25);
        
        return response()->json([
            'status' => 'success',
            'data' => $payments
        ]);
    }

    public function store(Request $request)
    {
        // Check if this is a single fee payment or lump sum
        if ($request->has('fee_ids') && is_array($request->fee_ids)) {
            // This is a lump sum payment with multiple fees
            return $this->storeLumpSumPayment($request);
        } else {
            // This is a single fee payment (backward compatibility)
            return $this->storeSinglePayment($request);
        }
    }

    private function storeSinglePayment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'student_id' => 'required|exists:students,id',
            'fee_id' => 'required|exists:fees,id',
            'transaction_id' => 'nullable|exists:transactions,id',
            'amount_paid' => 'required|numeric|min:0',
            'payment_date' => 'required|date',
            'status' => 'required|string|in:paid,pending,failed',
            'school_id' => 'required|exists:schools,id',
            'payment_method' => 'nullable|string|in:cash,bank_transfer,card,paystack,other,manual',
            'payment_reference' => 'nullable|string|unique:fee_payments,payment_reference'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        
        try {
            $validated = $validator->validated();
            
            // Generate payment reference if not provided
            if (empty($validated['payment_reference'])) {
                $validated['payment_reference'] = 'PAY-' . date('Ymd') . '-' . strtoupper(Str::random(8));
            }
            
            // Get fee amount for validation
            $fee = Fee::find($validated['fee_id']);
            if (!$fee) {
                throw new \Exception("Fee with ID {$validated['fee_id']} not found");
            }
            
            // Verify amount matches fee amount (allow small differences)
            $amountDifference = abs($fee->amount - $validated['amount_paid']);
            if ($amountDifference > 0.01) {
                throw new \Exception("Payment amount ({$validated['amount_paid']}) does not match fee amount ({$fee->amount})");
            }
            
            // Create the fee payment
            $payment = FeePayment::create($validated);
            
            // If status is paid and no transaction exists, create one
            if ($validated['status'] === 'paid' && empty($validated['transaction_id'])) {
                $transaction = Transaction::create([
                    'reference' => $validated['payment_reference'],
                    'amount' => $validated['amount_paid'],
                    'method' => $validated['payment_method'] ?? 'manual',
                    'status' => 'successful',
                    'school_id' => $validated['school_id']
                ]);
                
                // Update fee payment with transaction ID
                $payment->update(['transaction_id' => $transaction->id]);
            }
            
            DB::commit();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Payment recorded successfully',
                'data' => $payment->load(['student', 'fee', 'fee.grade', 'school', 'transaction'])
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to record payment: ' . $e->getMessage()
            ], 500);
        }
    }

    private function storeLumpSumPayment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'student_id' => 'required|exists:students,id',
            'fee_ids' => 'required|array|min:1',
            'fee_ids.*' => 'exists:fees,id',
            'amount_paid' => 'required|numeric|min:0',
            'payment_date' => 'required|date',
            'status' => 'required|string|in:paid,pending,failed',
            'school_id' => 'required|exists:schools,id',
            'payment_method' => 'required|string|in:cash,bank_transfer,card,paystack,other,manual',
            'payment_reference' => 'nullable|string|unique:fee_payments,payment_reference'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        
        try {
            $validated = $validator->validated();
            
            // Generate payment reference if not provided
            if (empty($validated['payment_reference'])) {
                $validated['payment_reference'] = 'PAY-' . date('Ymd') . '-' . strtoupper(Str::random(8));
            }
            
            $totalAmount = 0;
            $feePayments = [];
            $studentName = null;
            
            // Create individual fee payments with the same reference
            foreach ($validated['fee_ids'] as $feeId) {
                $fee = Fee::find($feeId);
                
                if (!$fee) {
                    throw new \Exception("Fee with ID {$feeId} not found");
                }
                
                $feePayment = FeePayment::create([
                    'student_id' => $validated['student_id'],
                    'fee_id' => $feeId,
                    'amount_paid' => $fee->amount,
                    'payment_date' => $validated['payment_date'],
                    'status' => $validated['status'],
                    'school_id' => $validated['school_id'],
                    'payment_method' => $validated['payment_method'],
                    'payment_reference' => $validated['payment_reference']
                ]);
                
                $feePayments[] = $feePayment;
                $totalAmount += $fee->amount;
                
                // Get student name once
                if (!$studentName && $feePayment->student) {
                    $studentName = $feePayment->student->name;
                }
            }
            
            // Verify total amount matches the provided amount (with tolerance)
            $amountDifference = abs($totalAmount - $validated['amount_paid']);
            if ($amountDifference > 0.01) {
                throw new \Exception("Total fee amount ({$totalAmount}) does not match provided amount ({$validated['amount_paid']})");
            }
            
            // Create a transaction record for the lump sum payment
            $transaction = Transaction::create([
                'reference' => $validated['payment_reference'],
                'amount' => $totalAmount,
                'method' => $validated['payment_method'],
                'status' => $validated['status'] === 'paid' ? 'successful' : ($validated['status'] === 'pending' ? 'pending' : 'failed'),
                'school_id' => $validated['school_id']
            ]);
            
            // Update fee payments with transaction ID
            FeePayment::where('payment_reference', $validated['payment_reference'])
                ->update(['transaction_id' => $transaction->id]);
            
            DB::commit();
            
            // Reload relationships
            $transaction->load('feePayment.student');
            foreach ($feePayments as $feePayment) {
                $feePayment->load('fee', 'student');
            }
            
            return response()->json([
                'status' => 'success',
                'message' => 'Lump sum payment recorded successfully',
                'data' => [
                    'payment_reference' => $validated['payment_reference'],
                    'total_amount' => $totalAmount,
                    'transaction' => $transaction,
                    'fee_payments' => $feePayments,
                    'payment_count' => count($feePayments)
                ]
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to record lump sum payment: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show(Request $request, $id)
    {
        $validator = Validator::make([
            'id' => $id,
            'school_id' => $request->school_id
        ], [
            'id' => 'required|exists:fee_payments,id',
            'school_id' => 'required|exists:schools,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check if ID is a numeric ID or a payment reference
        if (is_numeric($id)) {
            // Get single payment by ID
            $payment = FeePayment::where('school_id', $request->school_id)
                ->with(['student', 'fee', 'fee.grade', 'school', 'transaction'])
                ->find($id);

            if (!$payment) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Payment not found or does not belong to this school.'
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'data' => $payment
            ]);
        } else {
            // Get all payments by reference (lump sum payment)
            return $this->showByReference($request, $id);
        }
    }

    private function showByReference(Request $request, $reference)
    {
        // Get all payments with this reference
        $payments = FeePayment::where('school_id', $request->school_id)
            ->where('payment_reference', $reference)
            ->with(['student', 'fee', 'fee.grade', 'school', 'transaction'])
            ->get();

        if ($payments->isEmpty()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Payment not found or does not belong to this school.'
            ], 404);
        }

        $totalAmount = $payments->sum('amount_paid');
        $firstPayment = $payments->first();
        
        return response()->json([
            'status' => 'success',
            'data' => [
                'payment_reference' => $reference,
                'total_amount' => $totalAmount,
                'student' => $firstPayment->student,
                'transaction' => $firstPayment->transaction,
                'fee_payments' => $payments,
                'payment_count' => $payments->count(),
                'payment_method' => $firstPayment->payment_method,
                'payment_date' => $firstPayment->payment_date,
                'status' => $firstPayment->status
            ]
        ]);
    }

    public function update(Request $request, $id)
    {
        $validator = Validator::make(array_merge(['id' => $id], $request->all()), [
            'id' => 'required|exists:fee_payments,id',
            'student_id' => 'sometimes|required|exists:students,id',
            'fee_id' => 'sometimes|required|exists:fees,id',
            'transaction_id' => 'nullable|exists:transactions,id',
            'amount_paid' => 'sometimes|required|numeric|min:0',
            'payment_date' => 'sometimes|required|date',
            'status' => 'sometimes|required|string|in:paid,pending,failed',
            'payment_method' => 'nullable|string|in:cash,bank_transfer,card,paystack,other,manual',
            'school_id' => 'required|exists:schools,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $payment = FeePayment::where('school_id', $request->school_id)->find($id);

        if (!$payment) {
            return response()->json([
                'status' => 'error',
                'message' => 'Payment not found or does not belong to this school.'
            ], 404);
        }

        $payment->update($validator->validated());

        // If this payment has a transaction and status changed, update transaction too
        if ($request->has('status') && $payment->transaction_id) {
            $transaction = Transaction::find($payment->transaction_id);
            if ($transaction) {
                $transactionStatus = $request->status === 'paid' ? 'successful' : 
                                   ($request->status === 'pending' ? 'pending' : 'failed');
                $transaction->update(['status' => $transactionStatus]);
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Payment updated successfully',
            'data' => $payment->fresh(['student', 'fee', 'fee.grade', 'school', 'transaction'])
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $validator = Validator::make([
            'id' => $id,
            'school_id' => $request->school_id
        ], [
            'id' => 'required|exists:fee_payments,id',
            'school_id' => 'required|exists:schools,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $payment = FeePayment::where('school_id', $request->school_id)->find($id);

        if (!$payment) {
            return response()->json([
                'status' => 'error',
                'message' => 'Payment not found or does not belong to this school.'
            ], 404);
        }

        // Check if this is part of a lump sum payment
        if ($payment->payment_reference) {
            // Check how many payments share this reference
            $paymentCount = FeePayment::where('payment_reference', $payment->payment_reference)
                ->where('school_id', $request->school_id)
                ->count();
            
            // If this is the only payment with this reference, delete the transaction too
            if ($paymentCount === 1 && $payment->transaction_id) {
                Transaction::where('id', $payment->transaction_id)
                    ->where('school_id', $request->school_id)
                    ->delete();
            }
        } else if ($payment->transaction_id) {
            // If single payment with transaction, delete transaction
            Transaction::where('id', $payment->transaction_id)
                ->where('school_id', $request->school_id)
                ->delete();
        }

        $payment->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Payment deleted successfully'
        ]);
    }

    public function getStudentPayments(Request $request, $studentId)
    {
        $validator = Validator::make([
            'student_id' => $studentId,
            'school_id' => $request->school_id
        ], [
            'student_id' => 'required|exists:students,id',
            'school_id' => 'required|exists:schools,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Get grouped payments for the student
        $payments = FeePayment::where('school_id', $request->school_id)
            ->where('student_id', $studentId)
            ->select([
                'payment_reference',
                DB::raw('MIN(id) as id'),
                DB::raw('COUNT(*) as payment_count'),
                DB::raw('SUM(amount_paid) as total_amount'),
                DB::raw('MIN(payment_date) as payment_date'),
                DB::raw('MIN(status) as status'),
                DB::raw('MIN(payment_method) as payment_method')
            ])
            ->groupBy('payment_reference')
            ->orderByDesc('payment_date')
            ->paginate(25);

        foreach ($payments as $payment) {
            if ($payment->payment_reference) {
                $paymentDetails = FeePayment::where('payment_reference', $payment->payment_reference)
                    ->with(['fee', 'transaction'])
                    ->get();
                $payment->fee_details = $paymentDetails->pluck('fee');
                $payment->transaction = $paymentDetails->first()->transaction;
            }
        }

        return response()->json([
            'status' => 'success',
            'data' => $payments
        ]);
    }

    public function getPaymentStats(Request $request)
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

        $query = FeePayment::where('school_id', $request->school_id);

        if ($request->filled('from_date') && $request->filled('to_date')) {
            $query->whereBetween('payment_date', [$request->from_date, $request->to_date]);
        }

        $stats = [
            'total_payments' => $query->count(),
            'paid_payments' => $query->clone()->where('status', 'paid')->count(),
            'pending_payments' => $query->clone()->where('status', 'pending')->count(),
            'failed_payments' => $query->clone()->where('status', 'failed')->count(),
            'total_amount' => $query->clone()->where('status', 'paid')->sum('amount_paid'),
            'unique_transactions' => $query->clone()->whereNotNull('payment_reference')->distinct('payment_reference')->count('payment_reference'),
        ];

        // Get payment method breakdown
        $methodBreakdown = FeePayment::where('school_id', $request->school_id)
            ->where('status', 'paid')
            ->select('payment_method', DB::raw('COUNT(*) as count'), DB::raw('SUM(amount_paid) as total'))
            ->groupBy('payment_method')
            ->get();

        $stats['payment_method_breakdown'] = $methodBreakdown;

        return response()->json([
            'status' => 'success',
            'data' => $stats
        ]);
    }
}