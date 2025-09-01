<?php

declare(strict_types=1);

namespace Prahsys\LaravelClerk\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Prahsys\LaravelClerk\Models\PaymentSession;
use Prahsys\LaravelClerk\Models\WebhookEvent;
use Prahsys\LaravelClerk\Events\WebhookReceived;
use Prahsys\LaravelClerk\Exceptions\WebhookVerificationException;

class WebhookEventHandler
{
    public function __construct(
        protected PaymentSessionManager $sessionManager
    ) {
    }

    /**
     * Handle incoming webhook request
     */
    public function handleWebhook(Request $request): WebhookEvent
    {
        $payload = $request->all();
        $signature = $request->header('Prahsys-Signature');

        // Verify webhook signature
        if (!$this->verifyWebhookSignature($request->getContent(), $signature)) {
            throw new WebhookVerificationException('Invalid webhook signature');
        }

        // Create webhook event record
        $webhookEvent = WebhookEvent::create([
            'event_id' => $payload['id'] ?? uniqid('evt_'),
            'event_type' => $payload['type'] ?? 'unknown',
            'status' => 'pending',
            'payload' => $payload,
            'signature' => $signature,
        ]);

        // Try to associate with payment session
        if ($sessionId = $this->extractSessionId($payload)) {
            $session = PaymentSession::where('session_id', $sessionId)->first();
            if ($session) {
                $webhookEvent->update(['payment_session_id' => $session->id]);
            }
        }

        // Dispatch event for processing
        event(new WebhookReceived($webhookEvent));

        return $webhookEvent;
    }

    /**
     * Process a webhook event
     */
    public function processWebhookEvent(WebhookEvent $webhookEvent): void
    {
        try {
            $this->processEventByType($webhookEvent);
            $webhookEvent->markAsProcessed();
            
            Log::info('Webhook event processed successfully', [
                'event_id' => $webhookEvent->event_id,
                'event_type' => $webhookEvent->event_type,
            ]);
        } catch (\Exception $e) {
            $webhookEvent->markAsFailed($e->getMessage());
            $webhookEvent->incrementRetryCount();
            
            Log::error('Failed to process webhook event', [
                'event_id' => $webhookEvent->event_id,
                'event_type' => $webhookEvent->event_type,
                'error' => $e->getMessage(),
                'retry_count' => $webhookEvent->retry_count,
            ]);

            throw $e;
        }
    }

    /**
     * Process event based on type
     */
    protected function processEventByType(WebhookEvent $webhookEvent): void
    {
        $payload = $webhookEvent->payload;

        match ($webhookEvent->event_type) {
            'payment.created' => $this->handlePaymentCreated($webhookEvent, $payload),
            'payment.captured' => $this->handlePaymentCaptured($webhookEvent, $payload),
            'payment.authorized' => $this->handlePaymentAuthorized($webhookEvent, $payload),
            'payment.failed' => $this->handlePaymentFailed($webhookEvent, $payload),
            'payment.refunded' => $this->handlePaymentRefunded($webhookEvent, $payload),
            'payment.voided' => $this->handlePaymentVoided($webhookEvent, $payload),
            'session.expired' => $this->handleSessionExpired($webhookEvent, $payload),
            default => Log::warning('Unknown webhook event type', [
                'event_type' => $webhookEvent->event_type,
                'event_id' => $webhookEvent->event_id,
            ]),
        };
    }

    /**
     * Handle payment created event
     */
    protected function handlePaymentCreated(WebhookEvent $webhookEvent, array $payload): void
    {
        if ($session = $webhookEvent->paymentSession) {
            $session->update(['status' => 'pending']);
        }
    }

    /**
     * Handle payment captured event
     */
    protected function handlePaymentCaptured(WebhookEvent $webhookEvent, array $payload): void
    {
        if ($session = $webhookEvent->paymentSession) {
            $session->update([
                'status' => 'captured',
                'completed_at' => now(),
            ]);

            // Create transaction record if it doesn't exist
            $this->createTransactionFromWebhook($session, $payload, 'capture');
        }
    }

    /**
     * Handle payment authorized event
     */
    protected function handlePaymentAuthorized(WebhookEvent $webhookEvent, array $payload): void
    {
        if ($session = $webhookEvent->paymentSession) {
            $session->update([
                'status' => 'authorized',
                'completed_at' => now(),
            ]);

            // Create transaction record if it doesn't exist
            $this->createTransactionFromWebhook($session, $payload, 'payment');
        }
    }

    /**
     * Handle payment failed event
     */
    protected function handlePaymentFailed(WebhookEvent $webhookEvent, array $payload): void
    {
        if ($session = $webhookEvent->paymentSession) {
            $session->update(['status' => 'failed']);

            // Create failed transaction record
            $this->createTransactionFromWebhook($session, $payload, 'payment', 'failed');
        }
    }

    /**
     * Handle payment refunded event
     */
    protected function handlePaymentRefunded(WebhookEvent $webhookEvent, array $payload): void
    {
        if ($session = $webhookEvent->paymentSession) {
            $this->createTransactionFromWebhook($session, $payload, 'refund');
        }
    }

    /**
     * Handle payment voided event
     */
    protected function handlePaymentVoided(WebhookEvent $webhookEvent, array $payload): void
    {
        if ($session = $webhookEvent->paymentSession) {
            $session->update(['status' => 'voided']);
            $this->createTransactionFromWebhook($session, $payload, 'void');
        }
    }

    /**
     * Handle session expired event
     */
    protected function handleSessionExpired(WebhookEvent $webhookEvent, array $payload): void
    {
        if ($session = $webhookEvent->paymentSession) {
            $session->update(['status' => 'expired']);
        }
    }

    /**
     * Create transaction record from webhook data
     */
    protected function createTransactionFromWebhook(
        PaymentSession $session,
        array $payload,
        string $type,
        string $status = null
    ): void {
        $transactionData = $payload['data']['object'] ?? [];
        
        $session->transactions()->create([
            'transaction_id' => $transactionData['id'] ?? 'webhook_' . uniqid(),
            'type' => $type,
            'status' => $status ?? strtolower($transactionData['status'] ?? 'completed'),
            'amount' => $transactionData['amount'] ?? $session->amount,
            'currency' => $transactionData['currency'] ?? $session->currency,
            'reference' => $transactionData['reference'] ?? null,
            'gateway_response' => $transactionData,
            'card_data' => $transactionData['card'] ?? null,
            'customer_data' => $transactionData['customer'] ?? null,
            'processed_at' => now(),
            'captured_at' => $type === 'capture' ? now() : null,
            'refunded_at' => $type === 'refund' ? now() : null,
            'voided_at' => $type === 'void' ? now() : null,
        ]);
    }

    /**
     * Verify webhook signature
     */
    protected function verifyWebhookSignature(string $payload, ?string $signature): bool
    {
        if (!$signature) {
            return false;
        }

        $webhookSecret = config('clerk.webhooks.secret');
        if (!$webhookSecret) {
            Log::warning('Webhook secret not configured, skipping signature verification');
            return true; // Allow webhooks if no secret configured for testing
        }

        $expectedSignature = hash_hmac('sha256', $payload, $webhookSecret);
        $providedSignature = str_replace('prahsys_', '', $signature);

        return hash_equals($expectedSignature, $providedSignature);
    }

    /**
     * Extract session ID from webhook payload
     */
    protected function extractSessionId(array $payload): ?string
    {
        return $payload['data']['object']['session_id'] ?? 
               $payload['session_id'] ?? 
               null;
    }

    /**
     * Retry failed webhook events
     */
    public function retryFailedWebhooks(): int
    {
        $failedEvents = WebhookEvent::where('status', 'failed')
            ->where('retry_count', '<', config('clerk.webhooks.max_attempts', 3))
            ->get();

        $processed = 0;

        foreach ($failedEvents as $event) {
            try {
                $this->processWebhookEvent($event);
                $processed++;
            } catch (\Exception $e) {
                // Already logged in processWebhookEvent
                continue;
            }
        }

        return $processed;
    }
}