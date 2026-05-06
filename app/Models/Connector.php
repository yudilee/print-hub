<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Connector represents an external data source that templates can connect to.
 *
 * Connectors define how client apps pull data from external systems
 * (APIs, webhooks, Odoo instances, custom sources) for template rendering.
 */
class Connector extends Model
{
    use SoftDeletes;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'client_app_id',
        'name',
        'type',
        'config',
        'icon',
        'is_active',
        'last_test_at',
    ];

    protected $casts = [
        'id'           => 'string',
        'config'       => 'array',
        'is_active'    => 'boolean',
        'last_test_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Connector $connector) {
            if (empty($connector->id)) {
                $connector->id = (string) Str::uuid();
            }
        });
    }

    // ── Scopes ────────────────────────────────────────────────

    /**
     * Scope to only active connectors.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // ── Relationships ─────────────────────────────────────────

    /**
     * The client app that owns this connector (nullable for system connectors).
     */
    public function clientApp(): BelongsTo
    {
        return $this->belongsTo(ClientApp::class);
    }

    /**
     * Templates connected to this connector (many-to-many).
     */
    public function templates(): BelongsToMany
    {
        return $this->belongsToMany(PrintTemplate::class, 'connector_template')
            ->withTimestamps();
    }

    // ── Connection Testing ────────────────────────────────────

    /**
     * Test the connector by sending a simple GET/HEAD request to its URL.
     *
     * @return array{success: bool, message: string, latency_ms: ?int}
     */
    public function testConnection(): array
    {
        $config = $this->config ?? [];
        $url    = $config['endpoint_url'] ?? $config['url'] ?? null;

        if (empty($url)) {
            return [
                'success'    => false,
                'message'    => 'No endpoint URL configured.',
                'latency_ms' => null,
            ];
        }

        try {
            $start    = microtime(true);
            $response = Http::timeout(10)->head($url);
            $latency  = (int) ((microtime(true) - $start) * 1000);

            if ($response->successful()) {
                $this->update(['last_test_at' => now()]);

                return [
                    'success'    => true,
                    'message'    => "Connected successfully (HTTP {$response->status()}).",
                    'latency_ms' => $latency,
                ];
            }

            return [
                'success'    => false,
                'message'    => "HTTP {$response->status()}: {$response->reason()}",
                'latency_ms' => $latency,
            ];
        } catch (\Throwable $e) {
            return [
                'success'    => false,
                'message'    => 'Connection failed: ' . $e->getMessage(),
                'latency_ms' => null,
            ];
        }
    }
}
