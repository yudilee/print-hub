<?php

namespace Tests\Feature;

use App\Console\Commands\RetryWebhooks;
use App\Models\ClientApp;
use App\Models\WebhookDelivery;
use App\Services\WebhookService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WebhookDeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected ClientApp $clientApp;
    protected WebhookService $webhookService;

    protected function setUp(): void
    {
        parent::setUp();

        // Use sha256 hash because ClientApp::findByKey() first tries
        // a sha256 lookup against the api_key column.
        // Note: webhook_url is NOT in ClientApp::$fillable, so it must be
        // set directly on the model after creation to bypass mass assignment.
        $this->clientApp = ClientApp::create([
            'name'              => 'Test App',
            'api_key'           => hash('sha256', 'test-key'),
            'is_active'         => true,
            'webhook_events'    => ['job.completed', 'job.failed'],
            'webhook_retry_count' => 3,
            'webhook_secret'    => 'test-secret',
        ]);
        $this->clientApp->webhook_url = 'https://example.com/webhook';
        $this->clientApp->save();

        $this->webhookService = app(WebhookService::class);
    }

    public function test_webhook_delivery_is_created_on_dispatch()
    {
        Http::fake([
            'https://example.com/webhook' => Http::response(['ok' => true], 200),
        ]);

        $this->webhookService->dispatch('job.completed', [
            'job_id' => 'test-job-123',
            'status' => 'success',
        ]);

        $this->assertDatabaseHas('webhook_deliveries', [
            'client_app_id' => $this->clientApp->id,
            'event_type'    => 'job.completed',
            'status'        => 'success',
        ]);
    }

    public function test_webhook_delivery_tracks_status()
    {
        Http::fake([
            'https://example.com/webhook' => Http::response(['ok' => true], 200),
        ]);

        $delivery = WebhookDelivery::create([
            'client_app_id' => $this->clientApp->id,
            'event_type'    => 'job.completed',
            'payload'       => ['job_id' => 'test-job-123'],
            'status'        => 'pending',
            'attempts'      => 0,
            'max_attempts'  => 3,
        ]);

        $this->webhookService->deliver($delivery);

        $delivery->refresh();
        $this->assertEquals('success', $delivery->status);
        $this->assertEquals(1, $delivery->attempts);
        $this->assertEquals(200, $delivery->response_code);
    }

    public function test_webhook_delivery_failed_status_is_tracked()
    {
        Http::fake([
            'https://example.com/webhook' => Http::response(['error' => 'Server Error'], 500),
        ]);

        $delivery = WebhookDelivery::create([
            'client_app_id' => $this->clientApp->id,
            'event_type'    => 'job.completed',
            'payload'       => ['job_id' => 'test-job-123'],
            'status'        => 'pending',
            'attempts'      => 0,
            'max_attempts'  => 3,
        ]);

        $this->webhookService->deliver($delivery);

        $delivery->refresh();
        // After a failed HTTP response, deliver() sets status to 'failed' first,
        // then scheduleRetry() changes it to 'retrying' if attempts remain.
        $this->assertEquals('retrying', $delivery->status);
        $this->assertEquals(1, $delivery->attempts);
    }

    public function test_signature_header_is_present()
    {
        Http::fake(function ($request) {
            $this->assertArrayHasKey('X-Webhook-Signature', $request->headers());

            $signature = $request->header('X-Webhook-Signature')[0];
            $this->assertNotEmpty($signature);

            // Verify the signature is a valid HMAC-SHA256
            $expected = hash_hmac('sha256', $request->getBody()->getContents(), 'test-secret');
            $this->assertEquals($expected, $signature);

            return Http::response(['ok' => true], 200);
        });

        $delivery = WebhookDelivery::create([
            'client_app_id' => $this->clientApp->id,
            'event_type'    => 'job.completed',
            'payload'       => ['job_id' => 'test-job-123'],
            'status'        => 'pending',
            'attempts'      => 0,
            'max_attempts'  => 3,
        ]);

        $this->webhookService->deliver($delivery);
    }

    public function test_retry_webhooks_command_retries_failed_deliveries()
    {
        Http::fake([
            'https://example.com/webhook' => Http::response(['ok' => true], 200),
        ]);

        // Create a failed delivery that still has attempts remaining
        $delivery = WebhookDelivery::create([
            'client_app_id' => $this->clientApp->id,
            'event_type'    => 'job.completed',
            'payload'       => ['job_id' => 'test-job-123'],
            'status'        => 'failed',
            'attempts'      => 1,
            'max_attempts'  => 3,
            'next_retry_at' => now()->subMinute(), // Past due
        ]);

        Artisan::call(RetryWebhooks::class);

        $delivery->refresh();
        $this->assertEquals('success', $delivery->status);
        $this->assertEquals(2, $delivery->attempts);
    }

    public function test_retry_webhooks_skips_future_retry_at()
    {
        Http::fake([
            'https://example.com/webhook' => Http::response(['ok' => true], 200),
        ]);

        $delivery = WebhookDelivery::create([
            'client_app_id' => $this->clientApp->id,
            'event_type'    => 'job.completed',
            'payload'       => ['job_id' => 'test-job-123'],
            'status'        => 'failed',
            'attempts'      => 1,
            'max_attempts'  => 3,
            'next_retry_at' => now()->addHour(), // Future
        ]);

        Artisan::call(RetryWebhooks::class);

        $delivery->refresh();
        $this->assertEquals('failed', $delivery->status); // Should remain failed
    }

    public function test_retry_webhooks_skips_max_attempts_reached()
    {
        $delivery = WebhookDelivery::create([
            'client_app_id' => $this->clientApp->id,
            'event_type'    => 'job.completed',
            'payload'       => ['job_id' => 'test-job-123'],
            'status'        => 'failed',
            'attempts'      => 3,
            'max_attempts'  => 3,
        ]);

        Artisan::call(RetryWebhooks::class);

        $delivery->refresh();
        $this->assertEquals('failed', $delivery->status);
        $this->assertEquals(3, $delivery->attempts);
    }

    public function test_webhook_dispatch_respects_subscribed_events()
    {
        // This event is NOT in the subscribed events
        $this->webhookService->dispatch('agent.online', [
            'agent_id' => 1,
        ]);

        // Should not create a delivery since 'agent.online' is not subscribed
        $this->assertEquals(0, WebhookDelivery::count());
    }

    public function test_webhook_delivery_without_url_fails_gracefully()
    {
        // webhook_url is not in $fillable, so update() ignores it via mass assignment.
        // Set it directly on the model instead.
        $this->clientApp->webhook_url = '';
        $this->clientApp->save();

        $delivery = WebhookDelivery::create([
            'client_app_id' => $this->clientApp->id,
            'event_type'    => 'job.completed',
            'payload'       => ['job_id' => 'test-job-123'],
            'status'        => 'pending',
            'attempts'      => 0,
            'max_attempts'  => 3,
        ]);

        $this->webhookService->deliver($delivery);

        $delivery->refresh();
        $this->assertEquals('failed', $delivery->status);
        $this->assertEquals('No webhook URL configured', $delivery->error_message);
    }

    public function test_webhook_retry_uses_exponential_backoff()
    {
        Http::fake([
            'https://example.com/webhook' => Http::response(['error' => 'Server Error'], 500),
        ]);

        $delivery = WebhookDelivery::create([
            'client_app_id' => $this->clientApp->id,
            'event_type'    => 'job.completed',
            'payload'       => ['job_id' => 'test-job-123'],
            'status'        => 'pending',
            'attempts'      => 0,
            'max_attempts'  => 3,
        ]);

        $this->webhookService->deliver($delivery);

        $delivery->refresh();
        $this->assertEquals('retrying', $delivery->status);
        $this->assertNotNull($delivery->next_retry_at);
    }
}
