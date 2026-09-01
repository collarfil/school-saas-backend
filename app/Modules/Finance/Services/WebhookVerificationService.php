<?php

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Models\SchoolGateway;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class WebhookVerificationService
{
    public function verifyAndExtract(Request $request, string $provider, int $schoolId): array
    {
        $gateway = SchoolGateway::where('school_id', $schoolId)
            ->where('provider', $provider)
            ->where('is_active', true)
            ->firstOrFail();

        return match ($provider) {
            'paystack' => $this->verifyPaystack($request, $gateway->webhook_secret),
            'stripe' => $this->verifyStripe($request, $gateway->webhook_secret),
            default => throw new AccessDeniedHttpException("Unsupported provider {$provider}."),
        };
    }

    private function verifyPaystack(Request $request, string $secret): array
    {
        $signature = $request->header('x-paystack-signature');
        $computed = hash_hmac('sha512', $request->getContent(), $secret);

        if (!$signature || !hash_equals($computed, $signature)) {
            throw new AccessDeniedHttpException('Invalid Paystack signature.');
        }

        $payload = $request->all();

        return [
            'reference' => $payload['data']['reference'] ?? null,
            'gateway_reference' => (string) ($payload['data']['id'] ?? ''),
            'status' => ($payload['event'] ?? '') === 'charge.success' ? 'successful' : 'failed',
            'fee' => ($payload['data']['fees'] ?? 0) / 100,
            'raw' => $payload,
        ];
    }

    private function verifyStripe(Request $request, string $secret): array
    {
        $signature = $request->header('Stripe-Signature');
        if (!$signature) {
            throw new AccessDeniedHttpException('Missing Stripe signature header.');
        }

        $items = explode(',', $signature);
        $timestamp = null;
        $hash = null;

        foreach ($items as $item) {
            [$key, $val] = explode('=', trim($item), 2);
            if ($key === 't') $timestamp = $val;
            if ($key === 'v1') $hash = $val;
        }

        $signedPayload = "{$timestamp}." . $request->getContent();
        $expectedHash = hash_hmac('sha256', $signedPayload, $secret);

        if (!hash_equals($expectedHash, $hash)) {
            throw new AccessDeniedHttpException('Invalid Stripe signature.');
        }

        $payload = $request->all();

        return [
            'reference' => $payload['data']['object']['metadata']['reference'] ?? null,
            'gateway_reference' => $payload['data']['object']['id'] ?? null,
            'status' => ($payload['type'] ?? '') === 'payment_intent.succeeded' ? 'successful' : 'failed',
            'fee' => ($payload['data']['object']['application_fee_amount'] ?? 0) / 100,
            'raw' => $payload,
        ];
    }
}