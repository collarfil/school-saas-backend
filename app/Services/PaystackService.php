<?php

namespace App\Services;

use App\Models\School;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaystackService
{
    protected $secretKey;
    protected $publicKey;
    protected $baseUrl;
    protected $webhookSecret; // Added for clarity and security

    public function __construct()
    {
        $this->secretKey = config('services.paystack.secret_key');
        $this->publicKey = config('services.paystack.public_key');
        $this->baseUrl = config('services.paystack.payment_url', 'https://api.paystack.co');
        $this->webhookSecret = config('services.paystack.webhook_secret'); // Fetch webhook secret

        Log::info('🎯 PaystackService Constructor', [
            'has_secret' => !empty($this->secretKey),
            'has_public' => !empty($this->publicKey),
            'base_url' => $this->baseUrl,
        ]);
        
        if (empty($this->secretKey)) {
            Log::critical('❌ PAYSTACK SECRET KEY IS EMPTY IN CONSTRUCTOR!');
            throw new \Exception('Paystack secret key is empty. Check config/services.php');
        }
    }

    /**
     * Helper function to get the configured HTTP client with headers and SSL settings.
     * @return \Illuminate\Http\Client\PendingRequest
     */
    protected function getHttpClient()
    {
        $shouldVerifySSL = app()->environment('production');

        $httpClient = Http::withOptions([
            'verify' => $shouldVerifySSL,
            'timeout' => 30,
            'connect_timeout' => 10,
        ])->withHeaders([
            'Authorization' => 'Bearer ' . $this->secretKey,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json'
        ]);

        return $httpClient;
    }

    /**
     * Initialize payment for a new subscription.
     * @param School $school The school model.
     * @param int $amount The amount in NGN.
     * @param string $planType The plan type (e.g., 'termly', 'yearly').
     * @param string|null $term The term name.
     * @param string|null $school_session The school session name.
     * @return array
     */
    public function initializeSubscription(School $school, $amount, $planType, $term = null, $school_session = null)
    {
        try {
            $reference = 'SUB_' . uniqid() . '_' . time();
            
            $metadata = [
                'school_id' => $school->id,
                'school_name' => $school->name,
                'plan_type' => $planType,
                'term' => $term,
                'school_session' => $school_session,
                'amount' => $amount,
                'type' => 'subscription_payment'
            ];

            $payload = [
                'email' => $school->email ?? ($school->admin->email ?? 'admin@example.com'),
                'amount' => $amount * 100, // Convert to kobo
                'reference' => $reference,
                'callback_url' => url('/api/v1/subscriptions/payment/callback'),
                'metadata' => $metadata,
                'currency' => 'NGN'
            ];

            $response = $this->getHttpClient()
                ->post("{$this->baseUrl}/transaction/initialize", $payload);

            $responseData = $response->json();
            
            if (!$response->successful() || !($responseData['status'] ?? false)) {
                 Log::error('❌ Paystack API Request Failed', [
                    'status' => $response->status(),
                    'response' => $responseData,
                    'reference' => $reference
                ]);
                return [
                    'status' => false,
                    'message' => $responseData['message'] ?? 'Paystack initialization failed'
                ];
            }

            Log::info('✅ Paystack Payment Initialized Successfully', ['reference' => $reference]);

            return [
                'status' => true,
                'message' => 'Payment initialized successfully',
                'data' => $responseData['data']
            ];

        } catch (\Exception $e) {
            Log::error('💥 Paystack Exception in initializeSubscription', ['error' => $e->getMessage()]);
            return [
                'status' => false,
                'message' => 'Payment Service Error: ' . $e->getMessage()
            ];
        }
    }

    // --- CRUCIAL FIXES FOR SUBSCRIPTION FLOW ---

    /**
     * Manually verify a Paystack transaction by its reference.
     * This is CRUCIAL for webhook security and fixing stuck payments.
     * @param string $reference The transaction reference.
     * @return array
     */
    public function verifyTransaction($reference)
    {
        try {
            Log::info('🔍 Verifying Paystack Transaction', ['reference' => $reference]);

            $response = $this->getHttpClient()
                ->get("{$this->baseUrl}/transaction/verify/{$reference}");

            $responseData = $response->json();

            if (!$response->successful() || !($responseData['status'] ?? false)) {
                Log::error('❌ Transaction Verification Failed', [
                    'status' => $response->status(),
                    'response' => $responseData,
                    'reference' => $reference
                ]);
                return [
                    'status' => false,
                    'message' => $responseData['message'] ?? 'Verification failed',
                    'data' => $responseData['data'] ?? []
                ];
            }

            Log::info('✅ Transaction Verification Successful', [
                'reference' => $reference, 
                'paystack_status' => $responseData['data']['status'] ?? 'unknown'
            ]);

            return [
                'status' => true,
                'message' => $responseData['message'] ?? 'Verification successful',
                'data' => $responseData['data']
            ];

        } catch (\Exception $e) {
            Log::error('💥 Paystack Exception in verifyTransaction', ['error' => $e->getMessage(), 'reference' => $reference]);
            return [
                'status' => false,
                'message' => 'Verification Service Error: ' . $e->getMessage(),
                'data' => []
            ];
        }
    }

    /**
     * Verifies the authenticity of a Paystack webhook request.
     * This is CRUCIAL for server-side security.
     * @param string $payload The raw JSON request body.
     * @param string $signature The value of the 'x-paystack-signature' header.
     * @return bool
     */
    public function verifyWebhookSignature($payload, $signature)
    {
        // Use the dedicated webhook secret from the Paystack dashboard, 
        // which might be different from the secret key (though often the same).
        $webhookSecret = $this->webhookSecret ?? $this->secretKey; 
        
        if (!$webhookSecret) {
            Log::critical('Paystack webhook secret or key not configured for signature verification.');
            return false;
        }

        $computedSignature = hash_hmac('sha512', $payload, $webhookSecret);
        
        // Use hash_equals for secure comparison against timing attacks
        $isValid = hash_equals($computedSignature, $signature);
        
        if (!$isValid) {
            Log::warning('⚠️ Invalid webhook signature received!', [
                'computed_prefix' => substr($computedSignature, 0, 10) . '...',
                'received_prefix' => substr($signature, 0, 10) . '...'
            ]);
        }

        return $isValid;
    }
    
    // --- Other Methods (Refactored to use getHttpClient) ---

    public function createSubscriptionPlan($name, $amount, $interval = 'monthly')
    {
        try {
            $response = $this->getHttpClient()
                ->post("{$this->baseUrl}/plan", [
                    'name' => $name,
                    'amount' => $amount * 100,
                    'interval' => $interval,
                    'currency' => 'NGN'
                ]);

            return $response->json();

        } catch (\Exception $e) {
            Log::error('Create subscription plan failed', ['error' => $e->getMessage()]);
            return ['status' => false, 'message' => $e->getMessage()];
        }
    }

    public function createSubscriptionCustomer($email, $first_name = '', $last_name = '')
    {
        try {
            $response = $this->getHttpClient()
                ->post("{$this->baseUrl}/customer", [
                    'email' => $email,
                    'first_name' => $first_name,
                    'last_name' => $last_name
                ]);

            return $response->json();

        } catch (\Exception $e) {
            Log::error('Create customer failed', ['error' => $e->getMessage()]);
            return ['status' => false, 'message' => $e->getMessage()];
        }
    }

    public function getBanks()
    {
        try {
            $response = $this->getHttpClient()
                ->get("{$this->baseUrl}/bank?country=nigeria");

            return $response->json();

        } catch (\Exception $e) {
            Log::error('Get banks failed', ['error' => $e->getMessage()]);
            return ['status' => false, 'message' => $e->getMessage()];
        }
    }

    public function resolveAccountNumber($accountNumber, $bankCode)
    {
        try {
            $response = $this->getHttpClient()
                ->get("{$this->baseUrl}/bank/resolve", [
                    'account_number' => $accountNumber,
                    'bank_code' => $bankCode
                ]);

            return $response->json();

        } catch (\Exception $e) {
            Log::error('Resolve account failed', [
                'error' => $e->getMessage(),
                'account' => $accountNumber,
                'bank_code' => $bankCode
            ]);
            return ['status' => false, 'message' => $e->getMessage()];
        }
    }

    public function checkCredentials()
    {
        try {
            // Check credentials by attempting a simple API call (e.g., list transactions)
            $response = $this->getHttpClient()
                ->get("{$this->baseUrl}/transaction?perPage=1");

            return $response->successful();

        } catch (\Exception $e) {
            Log::error('Paystack credentials check failed', ['error' => $e->getMessage()]);
            return false;
        }
    }
}