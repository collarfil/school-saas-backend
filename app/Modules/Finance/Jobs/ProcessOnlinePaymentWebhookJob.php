<?php

namespace App\Modules\Finance\Jobs;

use App\Modules\Finance\Enums\PaymentStatus;
use App\Modules\Finance\Models\Fee;
use App\Modules\Finance\Models\FeePayment;
use App\Modules\Finance\Models\Transaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class ProcessOnlinePaymentWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $reference,
        public string $gatewayReference,
        public string $status,
        public float $gatewayFee,
        public array $rawPayload
    ) {}

    public function handle(): void
    {
        DB::transaction(function () {
            $transaction = Transaction::where('reference', $this->reference)
                ->where('status', PaymentStatus::PENDING)
                ->lockForUpdate()
                ->first();

            if (!$transaction) {
                return; // Already processed or missing reference
            }

            if ($this->status === 'successful') {
                $transaction->update([
                    'gateway_reference' => $this->gatewayReference,
                    'gateway_fee' => $this->gatewayFee,
                    'status' => PaymentStatus::SUCCESSFUL,
                    'raw_response' => $this->rawPayload,
                    'paid_at' => now(),
                ]);

                // Create individual FeePayment items tied to this transaction
                $metadata = $transaction->raw_response;
                $feeIds = $metadata['fee_ids'] ?? [];
                $studentId = $metadata['student_id'] ?? null;

                if ($studentId && !empty($feeIds)) {
                    $fees = Fee::whereIn('id', $feeIds)->get();

                    foreach ($fees as $fee) {
                        FeePayment::create([
                            'school_id' => $transaction->school_id,
                            'student_id' => $studentId,
                            'fee_id' => $fee->id,
                            'transaction_id' => $transaction->id,
                            'amount_paid' => $fee->amount,
                            'payment_date' => now(),
                            'status' => PaymentStatus::PAID,
                            'payment_reference' => $transaction->reference,
                            'payment_method' => $transaction->method,
                        ]);
                    }
                }
            } else {
                $transaction->update([
                    'status' => PaymentStatus::FAILED,
                    'raw_response' => $this->rawPayload,
                ]);
            }
        });
    }
}