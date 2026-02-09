<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Get all fee payments without a payment_reference
        $feePayments = DB::table('fee_payments')
            ->whereNull('payment_reference')
            ->orderBy('id')
            ->get();
        
        // Group payments by student and payment date to create lump sum references
        $groupedPayments = [];
        
        foreach ($feePayments as $payment) {
            $key = $payment->student_id . '_' . $payment->payment_date;
            
            if (!isset($groupedPayments[$key])) {
                $groupedPayments[$key] = [
                    'reference' => 'LEGACY-' . strtoupper(Str::random(10)),
                    'payments' => []
                ];
            }
            
            $groupedPayments[$key]['payments'][] = $payment->id;
        }
        
        // Update each group with the same reference
        foreach ($groupedPayments as $group) {
            DB::table('fee_payments')
                ->whereIn('id', $group['payments'])
                ->update([
                    'payment_reference' => $group['reference'],
                    'payment_method' => 'legacy', // Default method for existing records
                    'updated_at' => now()
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Set payment_reference to null for all records
        DB::table('fee_payments')
            ->update([
                'payment_reference' => null,
                'payment_method' => null
            ]);
    }
};