<?php

namespace App\Modules\Core\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\School;
use App\Models\SubscriptionPricing;
use App\Models\PaystackTransaction;
use App\Services\PaystackService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Carbon;

class SubscriptionController extends Controller
{
    protected PaystackService $paystackService;

    public function __construct(PaystackService $paystackService)
    {
        $this->paystackService = $paystackService;
    }

    /**
     * List subscriptions (paginated).
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            $query = Subscription::with(['school', 'pricing']);

            if ($user && $user->school_id && !$user->isSuperAdmin()) {
                $query->where('school_id', $user->school_id);
            }

            $subscriptions = $query->latest()->paginate(25);

            return response()->json(['status' => 'success', 'data' => $subscriptions]);
        } catch (\Throwable $e) {
            Log::error('Subscription index error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['status' => 'error', 'message' => 'Failed to load subscriptions'], 500);
        }
    }

    /**
     * Return pricing data.
     */
    public function getPricing()
    {
        try {
            $pricingRecords = SubscriptionPricing::where('is_active', true)->get();

            if ($pricingRecords->isEmpty()) {
                $pricing = [
                    'termly' => [
                        'id' => null,
                        'base_price' => 20000.0,
                        'per_student' => 2000.0,
                        'duration_days' => 120,
                        'description' => 'Per term subscription'
                    ],
                    'yearly' => [
                        'id' => null,
                        'base_price' => 50000.0,
                        'per_student' => 5000.0,
                        'duration_days' => 365,
                        'description' => 'Annual subscription'
                    ]
                ];
            } else {
                $pricing = [];
                foreach ($pricingRecords as $p) {
                    $pricing[$p->plan_type] = [
                        'id' => $p->id,
                        'base_price' => (float)$p->base_price,
                        'per_student' => (float)$p->per_student_price,
                        'duration_days' => (int)$p->duration_days,
                        'description' => $p->description,
                    ];
                }
            }

            return response()->json([
                'status' => 'success',
                'data' => [
                    'pricing' => $pricing,
                    'currency' => 'NGN',
                    'updated_at' => now()->toISOString()
                ]
            ]);
        } catch (\Throwable $e) {
            Log::error('Get pricing error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['status' => 'error', 'message' => 'Failed to get pricing'], 500);
        }
    }

    /**
     * Check subscription status for user's school.
     */
    public function checkStatus(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user || !$user->school_id) {
                return response()->json([
                    'has_active_subscription' => false,
                    'is_expired' => true,
                    'expires_in' => 0,
                    'school_unlocked' => false,
                    'message' => 'No school associated with user'
                ]);
            }

            $school = School::with(['activeSubscription'])->find($user->school_id);

            if (!$school) {
                return response()->json([
                    'has_active_subscription' => false,
                    'is_expired' => true,
                    'expires_in' => 0,
                    'school_unlocked' => false,
                    'message' => 'School not found'
                ]);
            }

            $activeSubscription = $school->activeSubscription;
            $isExpired = !$activeSubscription || $activeSubscription->valid_until === null || Carbon::parse($activeSubscription->valid_until)->lt(now());
            $expiresIn = $activeSubscription && $activeSubscription->valid_until ? now()->diffInDays(Carbon::parse($activeSubscription->valid_until), false) : 0;

            return response()->json([
                'has_active_subscription' => !$isExpired,
                'subscription' => $activeSubscription,
                'expires_in' => max(0, (int)$expiresIn),
                'is_expired' => $isExpired,
                'school_unlocked' => (bool)$school->is_unlocked,
                'student_capacity' => $activeSubscription->student_capacity ?? 0,
                'remaining_capacity' => method_exists($school, 'getRemainingStudentCapacity') ? $school->getRemainingStudentCapacity() : null
            ]);
        } catch (\Throwable $e) {
            Log::error('Subscription status check error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['has_active_subscription' => false, 'is_expired' => true, 'message' => 'Error checking subscription status'], 500);
        }
    }

    /**
     * Initialize payment for a subscription.
     */
    public function initializePayment(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'pricing_id' => 'required|exists:subscription_pricings,id',
                'school_id' => 'required|exists:schools,id',
                'student_capacity' => 'nullable|integer|min:1',
                'term' => 'nullable|string',
                'school_session' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 'error', 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
            }

            $user = $request->user();
            $schoolId = (int)$request->school_id;

            if (!$user->isSuperAdmin() && !($user->isSchoolAdmin() && $user->school_id == $schoolId)) {
                return response()->json(['status' => 'error', 'message' => 'Unauthorized.'], 403);
            }

            $school = School::findOrFail($schoolId);
            $pricing = SubscriptionPricing::findOrFail($request->pricing_id);

            // Prevent rapid double initializations within 2 minutes
            $recentPending = Subscription::where('school_id', $school->id)
                ->where('payment_status', 'pending')
                ->where('created_at', '>', now()->subMinutes(2))
                ->first();

            if ($recentPending) {
                return response()->json([
                    'status' => 'pending_exists',
                    'message' => 'A payment was recently initialized. Please complete it or cancel the pending payment.',
                    'existing_reference' => $recentPending->payment_reference,
                    'subscription_id' => $recentPending->id
                ], 200);
            }

            $studentCapacity = (int)$request->input('student_capacity', 100);
            $amount = (float)$pricing->base_price + ($studentCapacity * (float)$pricing->per_student_price);
            $planType = $pricing->plan_type;
            $term = $planType === 'termly' ? ($request->term ?? '1st term') : null;
            $schoolSession = $request->school_session ?? (date('Y') . '/' . (date('Y') + 1));

            // Initialize Paystack
            $response = $this->paystackService->initializeSubscription(
                $school,
                $amount,
                $planType,
                $term,
                $schoolSession
            );

            if (!is_array($response) || !($response['status'] ?? false)) {
                Log::error('Paystack initialization failed', ['response' => $response]);
                return response()->json(['status' => 'error', 'message' => 'Payment gateway error: ' . ($response['message'] ?? 'Unknown')], 500);
            }

            $reference = $response['data']['reference'] ?? null;

            $subscription = Subscription::create([
                'school_id' => $school->id,
                'pricing_id' => $pricing->id,
                'plan_type' => $planType,
                'term' => $term,
                'school_session' => $schoolSession,
                'student_capacity' => $studentCapacity,
                'amount' => $amount,
                'payment_reference' => $reference,
                'payment_status' => 'pending',
                'payment_gateway' => 'paystack',
                'valid_from' => null,
                'valid_until' => null,
                'status' => 'inactive',
            ]);

            Log::info('✅ Paystack Payment Initialized Successfully', ['reference' => $reference, 'subscription_id' => $subscription->id]);

            return response()->json([
                'status' => 'success',
                'message' => 'Payment initialized successfully',
                'data' => [
                    'authorization_url' => $response['data']['authorization_url'] ?? null,
                    'reference' => $reference,
                    'amount' => $amount,
                    'subscription_id' => $subscription->id
                ]
            ]);
        } catch (\Throwable $e) {
            Log::error('💥 Initialize payment error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['status' => 'error', 'message' => 'Failed to initialize payment: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Paystack webhook handler.
     */
    public function handlePaymentWebhook(Request $request)
    {
        try {
            $payload = $request->getContent();
            $signature = $request->header('x-paystack-signature');

            if (!$this->paystackService->verifyWebhookSignature($payload, $signature)) {
                Log::error('Invalid webhook signature', ['signature' => $signature]);
                return response('Invalid signature', 401);
            }

            $data = json_decode($payload, true);
            $event = $data['event'] ?? null;
            $transactionData = $data['data'] ?? [];
            $reference = $transactionData['reference'] ?? null;

            if (in_array($event, ['charge.success', 'transaction.success'], true)) {
                if (!$reference) {
                    Log::error('Webhook success event missing reference.');
                    return response('OK', 200);
                }

                $verification = $this->paystackService->verifyTransaction($reference);

                if (!($verification['status'] ?? false) || ($verification['data']['status'] ?? '') !== 'success') {
                    Log::warning('Webhook verification mismatch', ['reference' => $reference, 'verification' => $verification]);
                    return response('OK', 200);
                }

                return $this->handleSuccessfulPayment($verification['data']);
            }

            if (in_array($event, ['charge.failed', 'transaction.failed'], true)) {
                return $this->handleFailedPayment($transactionData);
            }

            return response('OK', 200);
        } catch (\Throwable $e) {
            Log::error('Webhook processing error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response('Error', 500);
        }
    }

    /**
     * Manual verification endpoint (frontend).
     */
    public function verifyPayment(Request $request)
    {
        $validator = Validator::make($request->all(), ['reference' => 'required|string|max:100']);
        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => 'Invalid reference'], 422);
        }

        $reference = $request->reference;

        try {
            $verification = $this->paystackService->verifyTransaction($reference);

            if (!($verification['status'] ?? false)) {
                return response()->json(['status' => 'pending', 'message' => 'Verification failed, try again later'], 200);
            }

            if (($verification['data']['status'] ?? '') === 'success') {
                $this->handleSuccessfulPayment($verification['data'], true);
                return response()->json(['status' => 'success', 'message' => 'Payment verified and subscription activated.']);
            }

            return response()->json([
                'status' => 'pending',
                'message' => 'Payment not completed yet.',
                'transaction_status' => $verification['data']['status'] ?? 'unknown'
            ]);
        } catch (\Throwable $e) {
            Log::error('Manual payment verification error: ' . $e->getMessage(), ['reference' => $reference]);
            return response()->json(['status' => 'error', 'message' => 'Verification failed due to service error.'], 500);
        }
    }

    /**
     * Cancel a pending payment.
     */
    public function cancelPendingPayment(Request $request)
    {
        $validator = Validator::make($request->all(), ['subscription_id' => 'required|exists:subscriptions,id']);
        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => 'Invalid subscription ID'], 422);
        }

        $subscriptionId = $request->subscription_id;
        $user = $request->user();

        try {
            $subscription = Subscription::findOrFail($subscriptionId);

            if ($user && $user->school_id && $subscription->school_id !== $user->school_id && !$user->isSuperAdmin()) {
                return response()->json(['status' => 'error', 'message' => 'Unauthorized access to subscription.'], 403);
            }

            if ($subscription->payment_status !== 'pending') {
                return response()->json(['status' => 'error', 'message' => 'Subscription is not currently pending.'], 400);
            }

            $subscription->update([
                'payment_status' => 'cancelled',
                'status' => 'inactive',
                'valid_from' => null,
                'valid_until' => null
            ]);

            Log::info('⚠️ Pending subscription manually cancelled.', ['subscription_id' => $subscriptionId, 'user_id' => $user->id ?? null]);

            return response()->json(['status' => 'success', 'message' => 'Pending payment successfully cancelled.']);
        } catch (\Throwable $e) {
            Log::error('Cancel payment error: ' . $e->getMessage(), ['subscription_id' => $subscriptionId]);
            return response()->json(['status' => 'error', 'message' => 'Failed to cancel payment.'], 500);
        }
    }

    /**
     * Handle successful payment (idempotent).
     */
    private function handleSuccessfulPayment(array $transactionData, bool $isManualVerification = false)
    {
        $reference = $transactionData['reference'] ?? null;
        if (!$reference) {
            Log::error('Successful payment handler called without reference.');
            return $isManualVerification ? response()->json(['status' => 'error'], 500) : response('OK', 200);
        }

        try {
            $amountInNaira = isset($transactionData['amount']) ? ($transactionData['amount'] / 100) : 0;

            DB::transaction(function () use ($reference, $amountInNaira, $transactionData) {
                $existingTransaction = PaystackTransaction::where('reference', $reference)->where('status', 'success')->first();
                if ($existingTransaction) {
                    Log::warning('Successful payment already processed.', ['reference' => $reference]);
                    return;
                }

                $subscription = Subscription::where('payment_reference', $reference)->first();
                if (!$subscription) {
                    Log::critical('Subscription not found for payment reference.', ['reference' => $reference]);
                    return;
                }

                $pricing = SubscriptionPricing::find($subscription->pricing_id);
                $durationDays = $pricing->duration_days ?? ($subscription->plan_type === 'termly' ? 120 : 365);

                $now = now();
                $validUntil = $now->copy()->addDays($durationDays);

                $subscription->update([
                    'payment_status' => 'paid',
                    'payment_date' => $now,
                    'status' => 'active',
                    'amount' => $amountInNaira ?: $subscription->amount,
                    'valid_from' => $now,
                    'valid_until' => $validUntil,
                    'payment_response' => $transactionData
                ]);

                $school = $subscription->school;
                if ($school) {
                    if (method_exists($school, 'unlock')) {
                        $school->unlock();
                    } else {
                        $school->is_unlocked = true;
                        $school->save();
                    }
                }

                // Deactivate previously active subscriptions
                Subscription::where('school_id', $subscription->school_id)
                    ->where('id', '!=', $subscription->id)
                    ->where('status', 'active')
                    ->update(['status' => 'expired']);

                PaystackTransaction::updateOrCreate(
                    ['reference' => $reference],
                    [
                        'school_id' => $subscription->school_id,
                        'subscription_id' => $subscription->id,
                        'amount' => $amountInNaira,
                        'currency' => $transactionData['currency'] ?? 'NGN',
                        'channel' => $transactionData['channel'] ?? 'web',
                        'status' => 'success',
                        'gateway_response' => $transactionData['gateway_response'] ?? 'Transaction successful',
                        'customer_email' => $transactionData['customer']['email'] ?? ($subscription->school->email ?? null),
                        'gateway_data' => $transactionData
                    ]
                );

                Log::info('✅ Subscription activated successfully.', ['subscription_id' => $subscription->id, 'reference' => $reference]);
            });

            return $isManualVerification ? response()->json(['status' => 'success']) : response('OK', 200);
        } catch (\Throwable $e) {
            Log::error('Failed to process successful payment', ['error' => $e->getMessage(), 'reference' => $reference]);
            return $isManualVerification ? response()->json(['status' => 'error'], 500) : response('OK', 200);
        }
    }

    /**
     * Handle failed payment.
     */
    private function handleFailedPayment(array $transactionData)
    {
        $reference = $transactionData['reference'] ?? null;
        if (!$reference) {
            return response('OK', 200);
        }

        try {
            DB::transaction(function () use ($transactionData, $reference) {
                $subscription = Subscription::where('payment_reference', $reference)->first();

                if ($subscription) {
                    $subscription->update([
                        'payment_status' => 'failed',
                        'payment_response' => $transactionData
                    ]);

                    PaystackTransaction::updateOrCreate(
                        ['reference' => $reference],
                        [
                            'school_id' => $subscription->school_id,
                            'subscription_id' => $subscription->id,
                            'amount' => $subscription->amount,
                            'currency' => 'NGN',
                            'status' => 'failed',
                            'gateway_response' => $transactionData['gateway_response'] ?? 'Payment failed',
                            'customer_email' => $subscription->school->email ?? null,
                            'gateway_data' => $transactionData
                        ]
                    );

                    Log::warning('Payment failed via webhook', ['reference' => $reference]);
                }
            });

            return response('OK', 200);
        } catch (\Throwable $e) {
            Log::error('Failed to process failed payment', ['error' => $e->getMessage(), 'reference' => $reference]);
            return response('Error', 500);
        }
    }

    /**
     * Frontend return callback (redirect).
     */
    public function paymentCallback(Request $request)
    {
        try {
            $reference = $request->query('reference');
            if (!$reference) {
                return redirect($this->getFrontendUrl() . '/subscription/failed');
            }

            $verification = $this->paystackService->verifyTransaction($reference);

            if (!($verification['status'] ?? false)) {
                return redirect($this->getFrontendUrl() . '/subscription/failed?reference=' . $reference);
            }

            if (($verification['data']['status'] ?? '') === 'success') {
                return redirect($this->getFrontendUrl() . '/subscription/success?reference=' . $reference);
            }

            return redirect($this->getFrontendUrl() . '/subscription/failed?reference=' . $reference);
        } catch (\Throwable $e) {
            Log::error('Payment callback error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return redirect($this->getFrontendUrl() . '/subscription/failed');
        }
    }

    /**
     * Show subscription details.
     */
    public function show($id, Request $request)
    {
        try {
            $subscription = Subscription::with(['school', 'paystackTransactions', 'pricing'])->findOrFail($id);
            $user = $request->user();

            if (!$user->isSuperAdmin() && $user->school_id && $subscription->school_id !== $user->school_id) {
                return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403);
            }

            return response()->json(['status' => 'success', 'data' => $subscription]);
        } catch (\Throwable $e) {
            Log::error('Subscription show error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['status' => 'error', 'message' => 'Failed to load subscription'], 500);
        }
    }

    /**
     * Get subscription transactions.
     */
    public function getSubscriptionTransactions($id, Request $request)
    {
        try {
            $subscription = Subscription::findOrFail($id);
            $user = $request->user();

            if (!$user->isSuperAdmin() && $user->school_id && $subscription->school_id !== $user->school_id) {
                return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403);
            }

            $transactions = $subscription->paystackTransactions()->latest()->get();

            return response()->json(['status' => 'success', 'data' => $transactions]);
        } catch (\Throwable $e) {
            Log::error('Get subscription transactions error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['status' => 'error', 'message' => 'Failed to load transactions'], 500);
        }
    }

    /**
     * Get frontend URL for redirects.
     */
    private function getFrontendUrl(): string
    {
        return config('app.frontend_url') ?? url('/');
    }
}