<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * WebhookDelivery tracks each attempt to deliver a webhook event
 * to a client application, including retry state and response data.
 */
class WebhookDelivery extends Model
{
    protected $fillable = [
        'client_app_id',
        'event_type',
        'payload',
        'status',
        'attempts',
        'max_attempts',
        'response_code',
        'response_body',
        'error_message',
        'last_attempt_at',
        'next_retry_at',
    ];

    protected $casts = [
        'payload'         => 'array',
        'attempts'        => 'integer',
        'max_attempts'    => 'integer',
        'response_code'   => 'integer',
        'last_attempt_at' => 'datetime',
        'next_retry_at'   => 'datetime',
    ];

    // ── Relationships ────────────────────────────────────────

    public function clientApp(): BelongsTo
    {
        return $this->belongsTo(ClientApp::class);
    }
}
