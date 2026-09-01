<?php
// app/Modules/Finance/Services/OnlinePaymentService.php

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Models\SchoolGateway;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OnlinePaymentService
{
    /**
     * Initialize payment with the selected gateway
     */
    public function initialize(array $data): array
    {
        $provider = $data['payment_method'] ?? 'paystack';
        $schoolId = $data['school_id'] ?? null;

        if (!$schoolId) {
            throw new \Exception('School ID is required');
        }

        // Get school's gateway configuration
        $gateway = SchoolGateway::where('school_id', $schoolId)
            ->where('provider', $provider)
            ->where('is_active', true)
            ->first();

        if (!$gateway) {
            throw new \Exception("Payment gateway {$provider} is not configured for this school. Please configure it in Online Payment Settings.");
        }

        // The keys are already encrypted via model casts
        $publicKey = $gateway->api_public_key;
        $secretKey = $gateway->api_secret_key;

        // Process payment based on provider
        switch ($provider) {
            case 'paystack':
                return $this->initializePaystack($data, $secretKey, $publicKey);
            case 'flutterwave':
                return $this->initializeFlutterwave($data, $secretKey, $publicKey);
            case 'stripe':
                return $this->initializeStripe($data, $secretKey, $publicKey);
            default:
                throw new \Exception("Unsupported payment gateway: {$provider}");
        }
    }

    /**
     * Initialize Paystack payment - FIXED payload
     */
    private function initializePaystack(array $data, string $secretKey, string $publicKey): array
    {
        // Calculate amount in kobo (Paystack uses kobo - multiply by 100)
        $amountInKobo = intval(round($data['amount'] * 100));
        
        // Log the payload for debugging
        Log::info('Paystack Payload:', [
            'amount' => $amountInKobo,
            'email' => $data['email'],
            'reference' => $data['reference'],
            'currency' => $data['currency'] ?? 'NGN',
            'callback_url' => $data['callback_url'] ?? null,
        ]);

        // Build the payload exactly as Paystack expects
        $payload = [
            'amount' => $amountInKobo,
            'email' => $data['email'],
            'reference' => $data['reference'],
            'currency' => $data['currency'] ?? 'NGN',
            'callback_url' => $data['callback_url'] ?? config('app.url') . '/payment/callback',
            'metadata' => [
                'parent_id' => (string) ($data['metadata']['parent_id'] ?? ''),
                'student_id' => (string) ($data['metadata']['student_id'] ?? ''),
                'fee_id' => (string) ($data['metadata']['fee_id'] ?? ''),
                'transaction_id' => (string) ($data['metadata']['transaction_id'] ?? ''),
            ]
        ];

        // Add optional fields if present
        if (!empty($data['name'])) {
            $payload['name'] = $data['name'];
        }

        // Make the request to Paystack
        $response = Http::withToken($secretKey)
            ->withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])
            ->post('https://api.paystack.co/transaction/initialize', $payload);

        // Log the response for debugging
        Log::info('Paystack Response:', [
            'status' => $response->status(),
            'body' => $response->json()
        ]);

        if (!$response->successful()) {
            $errorMessage = $response->json('message', 'Unknown error');
            $errorData = $response->json('data', []);
            
            Log::error('Paystack initialization failed', [
                'response' => $response->json(),
                'payload' => $payload,
                'status' => $response->status()
            ]);
            
            throw new \Exception('Paystack: ' . $errorMessage);
        }

        $result = $response->json();

        if (!isset($result['data']['authorization_url'])) {
            Log::error('Paystack response missing authorization_url', ['response' => $result]);
            throw new \Exception('Paystack initialization failed: Invalid response structure');
        }

        return [
            'authorization_url' => $result['data']['authorization_url'],
            'access_code' => $result['data']['access_code'] ?? null,
            'reference' => $result['data']['reference'] ?? $data['reference'],
            'public_key' => $publicKey
        ];
    }

    /**
     * Initialize Flutterwave payment
     */
    private function initializeFlutterwave(array $data, string $secretKey, string $publicKey): array
    {
        $payload = [
            'tx_ref' => $data['reference'],
            'amount' => floatval($data['amount']),
            'currency' => $data['currency'] ?? 'NGN',
            'redirect_url' => $data['callback_url'] ?? config('app.url') . '/payment/callback',
            'payment_options' => 'card,ussd,mpesa,mobilemoney',
            'meta' => [
                'consumer_id' => (string) ($data['metadata']['parent_id'] ?? ''),
                'consumer_mac' => 'unique_mac_address'
            ],
            'customer' => [
                'email' => $data['email'],
                'name' => $data['name'] ?? 'Parent',
                'phonenumber' => $data['phone'] ?? ''
            ],
            'customizations' => [
                'title' => 'School Fees Payment',
                'description' => 'Payment for school fees',
                'logo' => config('app.url') . '/logo.png'
            ]
        ];

        Log::info('Flutterwave Payload:', $payload);

        $response = Http::withToken($secretKey)
            ->withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])
            ->post('https://api.flutterwave.com/v3/payments', $payload);

        if (!$response->successful()) {
            Log::error('Flutterwave initialization failed', [
                'response' => $response->json(),
                'payload' => $payload
            ]);
            throw new \Exception('Flutterwave: ' . $response->json('message', 'Unknown error'));
        }

        $result = $response->json();

        return [
            'authorization_url' => $result['data']['link'],
            'reference' => $result['data']['tx_ref'],
            'public_key' => $publicKey
        ];
    }

    /**
     * Initialize Stripe payment
     */
    private function initializeStripe(array $data, string $secretKey, string $publicKey): array
    {
        throw new \Exception('Stripe integration is optional. Please use Paystack or Flutterwave.');
    }

    /**
     * Verify payment with the gateway
     */
    public function verifyPayment(string $reference, string $provider, int $schoolId): array
    {
        $gateway = SchoolGateway::where('school_id', $schoolId)
            ->where('provider', $provider)
            ->where('is_active', true)
            ->first();

        if (!$gateway) {
            throw new \Exception("Payment gateway {$provider} is not configured for this school");
        }

        $secretKey = $gateway->api_secret_key;

        switch ($provider) {
            case 'paystack':
                return $this->verifyPaystack($reference, $secretKey);
            case 'flutterwave':
                return $this->verifyFlutterwave($reference, $secretKey);
            case 'stripe':
                return $this->verifyStripe($reference, $secretKey);
            default:
                throw new \Exception("Unsupported payment gateway for verification: {$provider}");
        }
    }

    /**
     * Verify Paystack payment
     */
    private function verifyPaystack(string $reference, string $secretKey): array
    {
        $response = Http::withToken($secretKey)
            ->get("https://api.paystack.co/transaction/verify/{$reference}");

        if (!$response->successful()) {
            Log::error('Paystack verification failed', [
                'reference' => $reference,
                'response' => $response->json()
            ]);
            throw new \Exception('Paystack verification failed: ' . $response->json('message', 'Unknown error'));
        }

        $result = $response->json();
        $data = $result['data'];

        return [
            'status' => $data['status'] === 'success' ? 'success' : 'failed',
            'gateway_reference' => $data['id'] ?? null,
            'fee' => ($data['fees'] ?? 0) / 100,
            'data' => $data
        ];
    }

    /**
     * Verify Flutterwave payment
     */
    private function verifyFlutterwave(string $reference, string $secretKey): array
    {
        $response = Http::withToken($secretKey)
            ->get("https://api.flutterwave.com/v3/transactions/{$reference}/verify");

        if (!$response->successful()) {
            Log::error('Flutterwave verification failed', [
                'reference' => $reference,
                'response' => $response->json()
            ]);
            throw new \Exception('Flutterwave verification failed: ' . $response->json('message', 'Unknown error'));
        }

        $result = $response->json();
        $data = $result['data'];

        return [
            'status' => $data['status'] === 'successful' ? 'success' : 'failed',
            'gateway_reference' => $data['id'] ?? null,
            'fee' => $data['fee'] ?? 0,
            'data' => $data
        ];
    }

    /**
     * Verify Stripe payment
     */
    private function verifyStripe(string $reference, string $secretKey): array
    {
        throw new \Exception('Stripe verification is optional. Please use Paystack or Flutterwave.');
    }

    /**
     * Get configured gateways for a school
     */
    public function getGateways(int $schoolId): array
    {
        $gateways = SchoolGateway::where('school_id', $schoolId)
            ->select('id', 'provider', 'is_active', 'created_at')
            ->get();

        return $gateways->toArray();
    }

    /**
     * Check if a gateway is active for a school
     */
    public function isGatewayActive(int $schoolId, string $provider): bool
    {
        $gateway = SchoolGateway::where('school_id', $schoolId)
            ->where('provider', $provider)
            ->where('is_active', true)
            ->first();

        return $gateway !== null;
    }
}