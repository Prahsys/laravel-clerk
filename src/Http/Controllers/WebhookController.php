<?php

declare(strict_types=1);

namespace Prahsys\LaravelClerk\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Prahsys\LaravelClerk\Exceptions\WebhookVerificationException;
use Prahsys\LaravelClerk\Services\WebhookEventHandler;

class WebhookController extends Controller
{
    public function __construct(
        protected WebhookEventHandler $webhookHandler
    ) {
    }

    /**
     * Handle incoming Prahsys webhook
     */
    public function handle(Request $request): JsonResponse
    {
        try {
            $webhookEvent = $this->webhookHandler->handleWebhook($request);

            Log::info('Webhook received and queued for processing', [
                'event_id' => $webhookEvent->event_id,
                'event_type' => $webhookEvent->event_type,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Webhook received successfully',
                'event_id' => $webhookEvent->event_id,
            ], 200);

        } catch (WebhookVerificationException $e) {
            Log::warning('Webhook verification failed', [
                'error' => $e->getMessage(),
                'signature' => $request->header('Prahsys-Signature'),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Webhook verification failed',
            ], 401);

        } catch (\Exception $e) {
            Log::error('Webhook processing error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Internal server error',
            ], 500);
        }
    }
}