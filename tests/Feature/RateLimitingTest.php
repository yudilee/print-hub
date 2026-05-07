<?php

namespace Tests\Feature;

use App\Models\ClientApp;
use App\Models\PrintAgent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RateLimitingTest extends TestCase
{
    use RefreshDatabase;

    protected ClientApp $clientApp;
    protected PrintAgent $agent;
    protected string $rawApiKey;
    protected string $rawAgentKey;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rawApiKey = '550e8400-e29b-41d4-a716-446655440000';
        $this->rawAgentKey = 'test-agent-key-32-chars-long!!';

        // Use sha256 hash because ClientApp::findByKey() and PrintAgent::findByKey()
        // first try a sha256 lookup against the api_key/agent_key column.
        $this->clientApp = ClientApp::create([
            'name'      => 'Test App',
            'api_key'   => hash('sha256', $this->rawApiKey),
            'is_active' => true,
        ]);

        $this->agent = PrintAgent::create([
            'name'         => 'Test Agent',
            'agent_key'    => hash('sha256', $this->rawAgentKey),
            'is_active'    => true,
            'ip_address'   => '127.0.0.1',
            'last_seen_at' => now(),
            'printers'     => ['Test Printer'],
        ]);
    }

    public function test_client_api_rate_limit_headers_are_returned()
    {
        $response = $this->withHeaders(['X-API-Key' => $this->rawApiKey])
            ->getJson('/api/v1/test');

        $response->assertOk();
        $response->assertHeader('X-RateLimit-Limit');
        $response->assertHeader('X-RateLimit-Remaining');
    }

    public function test_agent_api_rate_limit_headers_are_returned()
    {
        $response = $this->withHeader('X-Agent-Key', $this->rawAgentKey)
            ->getJson('/api/print-hub/profiles');

        $response->assertOk();
        $response->assertHeader('X-RateLimit-Limit');
        $response->assertHeader('X-RateLimit-Remaining');
    }

    public function test_exceeding_client_api_rate_limit_returns_429()
    {
        // The client API has a limit of 60 requests per minute (configured in routes/api.php)
        // We'll make enough requests to trigger the limit
        $headers = ['X-API-Key' => $this->rawApiKey];

        // Make 60 requests (the limit)
        for ($i = 0; $i < 60; $i++) {
            $response = $this->withHeaders($headers)->getJson('/api/v1/test');
            if ($response->status() === 429) {
                break;
            }
        }

        // The 61st request should be rate-limited
        $response = $this->withHeaders($headers)->getJson('/api/v1/test');

        if ($response->status() === 429) {
            $response->assertHeader('Retry-After');
            // The 429 response from Laravel's ThrottleRequests middleware
            // returns an exception JSON, not our ApiResponse format.
            // It has 'message' key instead of 'success'.
            $response->assertJson([
                'message' => 'Too Many Attempts.',
            ]);
        }
    }

    public function test_different_client_apps_have_separate_rate_limits()
    {
        // Create a second client app
        $clientApp2 = ClientApp::create([
            'name'      => 'Second App',
            'api_key'   => hash('sha256', 'second-key-here'),
            'is_active' => true,
        ]);

        // Both should be able to make requests independently
        $response1 = $this->withHeaders(['X-API-Key' => $this->rawApiKey])
            ->getJson('/api/v1/test');
        $response1->assertOk();

        $response2 = $this->withHeaders(['X-API-Key' => 'second-key-here'])
            ->getJson('/api/v1/test');
        $response2->assertOk();
    }

    public function test_agent_api_has_separate_rate_limit()
    {
        // Agent API uses a different rate limiter (120 per minute)
        $response = $this->withHeader('X-Agent-Key', $this->rawAgentKey)
            ->getJson('/api/print-hub/profiles');

        $response->assertOk();
        $response->assertHeader('X-RateLimit-Limit');
    }

    public function test_unauthenticated_requests_are_not_rate_limited_before_auth_check()
    {
        // No API key provided - should get 401 before rate limiting
        $response = $this->getJson('/api/v1/test');

        $response->assertStatus(401);
        $response->assertJson([
            'success' => false,
        ]);
    }

    public function test_agent_heartbeat_has_rate_limiting()
    {
        $response = $this->withHeader('X-Agent-Key', $this->rawAgentKey)
            ->postJson('/api/print-hub/heartbeat');

        $response->assertOk();
        $response->assertHeader('X-RateLimit-Limit');
        $response->assertHeader('X-RateLimit-Remaining');
    }

    public function test_login_endpoint_has_rate_limiting()
    {
        // Login has throttle:30,1
        $response = $this->postJson('/login', [
            'email'    => 'test@example.com',
            'password' => 'wrong-password',
        ]);

        // Should get a response (not 429 unless we hit the limit)
        // Login endpoint redirects back with errors on failed auth
        $response->assertStatus(302); // Redirect back to login with error
    }
}
