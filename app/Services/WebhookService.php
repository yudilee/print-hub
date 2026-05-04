<?php

namespace App\Services;

use App\Models\ClientApp;
use App\Models\WebhookDelivery;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * WebhookService handles dispatching webhook events to client applications,
 * including HMAC signing, retry logic with exponential backoff, and
 * delivery tracking.
 */
class WebhookService
{
    /**
     * Supported event types.
     */
    const EVENTS = [
        'job.created',
        'job.completed',
        'job.failed',
        'job.approved',
        'job.rejected',
        'agent.online',
        'agent.offline',
        'printer.added',
        'printer.removed',
    ];

    /**
     * Retry delays in seconds (exponential backoff).
     */
    const RETRY_DELAYS = [30, 120, 300]; // 30s, 2min, 5min

    /**
     * Dispatch a webhook event.
     *
     * If $clientApp is specified, deliver only to that app.
     * If null, deliver to all client apps subscribed to this event type.
     */
    public function dispatch(string $eventType, array $payload, ?ClientApp $clientApp = null): void
    {
        $apps = $clientApp
            ? collect([$clientApp])
            : ClientApp::where('is_active', true)
                ->whereNotNull('webhook_events')
                ->where('webhook_url', '!=', '')
                ->get();

        foreach ($apps as $app) {
            $events = $app->webhook_events ?? [];

            // If no specific events set, send all (backwards compatibility)
            if (!empty($events) && !in_array($eventType, $events, true)) {
                continue;
            }

            if (empty($app->webhook_url)) {
                continue;
            }

            $delivery = WebhookDelivery::create([
                'client_app_id' => $app->id,
                'event_type'    => $eventType,
                'payload'       => $payload,
                'status'        => 'pending',
                'attempts'      => 0,
                'max_attempts'  => $app->webhook_retry_count ?: 3,
            ]);

            $this->deliver($delivery);
        }
    }

    /**
     * Deliver a specific webhook delivery.
     * Sends HTTP POST to the client app's webhook_url with HMAC signature.
     */
    public function deliver(WebhookDelivery $delivery): void
    {
        $clientApp = $delivery->clientApp;

        if (!$clientApp || !$clientApp->webhook_url) {
            $delivery->update([
                'status'         => 'failed',
                'error_message'  => 'No webhook URL configured',
                'last_attempt_at'=> now(),
            ]);
            return;
        }

        $payload = $delivery->payload;
        $jsonPayload = json_encode($payload);
        $timeout = $clientApp->webhook_timeout ?: 10;

        try {
            $request = Http::timeout($timeout)
                ->withHeaders(['Content-Type' => 'application/json']);

            // Add HMAC-SHA256 signature if secret is set
            if ($clientApp->webhook_secret) {
                $signature = hash_hmac('sha256', $jsonPayload, $clientApp->webhook_secret);
                $request->withHeaders(['X-Webhook-Signature' => $signature]);
            }

            $response = $request->send('POST', $clientApp->webhook_url, [
                'body' => $jsonPayload,
            ]);

            $delivery->update([
                'status'          => $response->successful() ? 'success' : 'failed',
                'attempts'        => $delivery->attempts + 1,
                'response_code'   => $response->status(),
                'response_body'   => substr($response->body(), 0, 1000),
                'last_attempt_at' => now(),
            ]);

            if (!$response->successful()) {
                $this->scheduleRetry($delivery);
            }
        } catch (\Exception $e) {
            $delivery->update([
                'status'          => 'failed',
                'attempts'        => $delivery->attempts + 1,
                'error_message'   => $e->getMessage(),
                'last_attempt_at' => now(),
            ]);

            $this->scheduleRetry($delivery);
        }
    }

    /**
     * Schedule a retry with exponential backoff if attempts remain.
     */
    private function scheduleRetry(WebhookDelivery $delivery): void
    {
        if ($delivery->attempts >= $delivery->max_attempts) {
            Log::warning('Webhook delivery max attempts reached', [
                'delivery_id' => $delivery->id,
                'event_type'  => $delivery->event_type,
                'attempts'    => $delivery->attempts,
            ]);
            return;
        }

        $delayIndex = min($delivery->attempts - 1, count(self::RETRY_DELAYS) - 1);
        $delaySeconds = self::RETRY_DELAYS[max(0, $delayIndex)];

        $nextRetryAt = now()->addSeconds($delaySeconds);

        $delivery->update([
            'status'       => 'retrying',
            'next_retry_at' => $nextRetryAt,
        ]);

        Log::info('Webhook delivery scheduled for retry', [
            'delivery_id'  => $delivery->id,
            'attempt'      => $delivery->attempts,
            'max_attempts' => $delivery->max_attempts,
            'next_retry_at' => $nextRetryAt->toIso8601String(),
        ]);
    }

    /**
     * Retry all failed deliveries that still have attempts remaining.
     * Returns the number of deliveries retried.
     */
    public function retryFailed(): int
    {
        $deliveries = WebhookDelivery::where('status', 'failed')
            ->whereColumn('attempts', '<', 'max_attempts')
            ->get();

        $count = 0;

        foreach ($deliveries as $delivery) {
            // Respect next_retry_at
            if ($delivery->next_retry_at && $delivery->next_retry_at->isFuture()) {
                continue;
            }

            $this->deliver($delivery);
            $count++;
        }

        return $count;
    }
}
