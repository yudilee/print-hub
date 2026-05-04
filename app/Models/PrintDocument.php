<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/**
 * PrintDocument represents an uploaded document (PDF, image) that can be
 * associated with print jobs for re-use or tracking.
 */
class PrintDocument extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'original_name',
        'stored_filename',
        'mime_type',
        'file_size',
        'page_count',
        'disk',
        'storage_path',
        'metadata',
    ];

    protected $casts = [
        'file_size'  => 'integer',
        'page_count' => 'integer',
        'metadata'   => 'array',
    ];

    protected $appends = [
        'formatted_size',
        'preview_url',
    ];

    // ── Relationships ────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ── Accessors ────────────────────────────────────────────

    public function getFormattedSizeAttribute(): string
    {
        $bytes = $this->file_size;

        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        }

        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }

        return $bytes . ' B';
    }

    public function getPreviewUrlAttribute(): string
    {
        return route('api.documents.preview', $this->id);
    }
}
