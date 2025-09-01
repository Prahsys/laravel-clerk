<?php

declare(strict_types=1);

namespace Prahsys\LaravelClerk\Services;

use Carbon\Carbon;
use Prahsys\LaravelClerk\Data\PaymentData;
use Prahsys\LaravelClerk\Data\PortalConfigurationData;
use Prahsys\LaravelClerk\Models\PaymentSession;
use Prahsys\LaravelClerk\Models\PaymentTransaction;
use Saloon\Http\Response;

class PaymentSessionManager
{
    public function __construct(
        protected PaymentService $paymentService
    ) {
    }

    /**
     * Create a new payment session
     */
    public function createSession(
        string $paymentId,
        float $amount,
        string $description,
        string $currency = 'USD',
        ?PortalConfigurationData $portalConfig = null,
        array $metadata = []
    ): PaymentSession {
        $paymentData = new PaymentData(
            id: $paymentId,
            amount: $amount,
            description: $description,
            currency: $currency
        );

        $response = $this->paymentService->createSession($paymentData, $portalConfig);
        
        if (!$response->successful()) {
            throw new \Exception('Failed to create payment session: ' . $response->body());
        }

        $responseData = $response->json();
        $sessionData = $responseData['data'];

        return PaymentSession::create([
            'session_id' => $sessionData['id'],
            'payment_id' => $paymentId,
            'merchant_id' => config('clerk.api.merchant_id'),
            'status' => 'created',
            'amount' => $amount,
            'currency' => $currency,
            'description' => $description,
            'portal_configuration' => $portalConfig?->toArray(),
            'success_indicator' => $sessionData['portal']['successIndicator'] ?? null,
            'metadata' => $metadata,
            'expires_at' => now()->addHour(),
        ]);
    }

    /**
     * Create a Pay Portal session
     */
    public function createPortalSession(
        string $paymentId,
        float $amount,
        string $description,
        string $returnUrl,
        string $cancelUrl,
        string $merchantName,
        ?string $merchantLogo = null,
        string $operation = 'PAY',
        array $metadata = []
    ): PaymentSession {
        $portalConfig = new PortalConfigurationData(
            operation: $operation,
            returnUrl: $returnUrl,
            cancelUrl: $cancelUrl,
            merchant: new \Prahsys\LaravelClerk\Data\MerchantData(
                name: $merchantName,
                logo: $merchantLogo
            )
        );

        return $this->createSession($paymentId, $amount, $description, 'USD', $portalConfig, $metadata);
    }

    /**
     * Update session status from API
     */
    public function syncSessionStatus(PaymentSession $session): PaymentSession
    {
        $response = $this->paymentService->getSession($session->session_id);
        
        if (!$response->successful()) {
            return $session;
        }

        $responseData = $response->json();
        $sessionData = $responseData['data'];

        $session->update([
            'status' => strtolower($sessionData['status']),
            'customer_email' => $sessionData['customer']['email'] ?? $session->customer_email,
            'customer_name' => $sessionData['customer']['name'] ?? $session->customer_name,
            'card_last4' => $sessionData['card']['last4'] ?? $session->card_last4,
            'card_brand' => $sessionData['card']['brand'] ?? $session->card_brand,
            'completed_at' => isset($sessionData['completedAt']) 
                ? Carbon::parse($sessionData['completedAt']) 
                : $session->completed_at,
        ]);

        return $session->fresh();
    }

    /**
     * Process payment for a session
     */
    public function processPayment(PaymentSession $session, ?array $additionalData = null): PaymentTransaction
    {
        $paymentData = new PaymentData(
            id: $session->payment_id,
            amount: $session->amount,
            description: $session->description,
            currency: $session->currency
        );

        $sessionData = new \Prahsys\LaravelClerk\Data\SessionData(
            id: $session->session_id
        );

        $response = $this->paymentService->processPayment(
            $session->payment_id,
            $paymentData,
            $sessionData
        );

        $responseData = $response->json();
        $transactionData = $responseData['data'];

        $transaction = PaymentTransaction::create([
            'payment_session_id' => $session->id,
            'transaction_id' => $transactionData['id'],
            'type' => 'payment',
            'status' => strtolower($transactionData['status']),
            'amount' => $session->amount,
            'currency' => $session->currency,
            'reference' => $transactionData['payment']['reference'] ?? null,
            'gateway_response' => $responseData,
            'card_data' => $transactionData['card'] ?? null,
            'customer_data' => $transactionData['customer'] ?? null,
            'processed_at' => isset($transactionData['processedAt']) 
                ? Carbon::parse($transactionData['processedAt']) 
                : now(),
        ]);

        // Update session status
        $session->update([
            'status' => strtolower($transactionData['status']),
            'completed_at' => $transaction->isSuccessful() ? now() : null,
        ]);

        return $transaction;
    }

    /**
     * Verify Portal payment using success indicator
     */
    public function verifyPortalPayment(PaymentSession $session, string $resultIndicator): bool
    {
        if (!$session->isPortalSession() || !$session->success_indicator) {
            return false;
        }

        $isValid = $this->paymentService->verifyPortalPayment(
            $session->success_indicator,
            $resultIndicator
        );

        if ($isValid) {
            $session->update([
                'result_indicator' => $resultIndicator,
                'status' => 'verified',
            ]);
        }

        return $isValid;
    }

    /**
     * Clean up expired sessions
     */
    public function cleanupExpiredSessions(): int
    {
        return PaymentSession::where('expires_at', '<', now())
            ->where('status', 'created')
            ->update(['status' => 'expired']);
    }

    /**
     * Get session statistics
     */
    public function getSessionStatistics(Carbon $startDate = null, Carbon $endDate = null): array
    {
        $query = PaymentSession::query();

        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('created_at', '<=', $endDate);
        }

        return [
            'total_sessions' => $query->count(),
            'completed_sessions' => (clone $query)->whereIn('status', ['captured', 'authorized'])->count(),
            'failed_sessions' => (clone $query)->where('status', 'failed')->count(),
            'expired_sessions' => (clone $query)->where('status', 'expired')->count(),
            'total_amount' => (clone $query)->whereIn('status', ['captured', 'authorized'])->sum('amount'),
            'portal_sessions' => (clone $query)->whereNotNull('portal_configuration')->count(),
            'embedded_sessions' => (clone $query)->whereNull('portal_configuration')->count(),
        ];
    }
}