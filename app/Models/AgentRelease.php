<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class AgentRelease extends Model
{
    protected $fillable = [
        'version',
        'platform',
        'channel',
        'file_original_name',
        'file_stored_path',
        'file_mime_type',
        'file_size',
        'sha256_hash',
        'release_notes',
        'is_mandatory',
        'is_latest',
        'uploaded_by',
    ];

    protected $casts = [
        'is_mandatory' => 'boolean',
        'is_latest'    => 'boolean',
        'file_size'    => 'integer',
    ];

    /**
     * The uploader (admin user) who uploaded this release.
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Get the download URL for this release (temporary signed URL, 1 hour).
     */
    public function getDownloadUrl(): string
    {
        // Storage is 'local' disk which maps to storage/app/private
        // We store files under agent-releases/ directory
        return Storage::disk('local')->temporaryUrl(
            $this->file_stored_path,
            now()->addHour()
        );
    }

    /**
     * Format file size for display.
     */
    public function getFormattedSizeAttribute(): string
    {
        if (! $this->file_size) {
            return '—';
        }

        $bytes = (int) $this->file_size;

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Scope a query to only include releases for a given platform.
     */
    public function scopeForPlatform($query, string $platform)
    {
        return $query->where('platform', $platform);
    }

    /**
     * Scope a query to only include the latest release per platform.
     */
    public function scopeLatestPerPlatform($query)
    {
        return $query->where('is_latest', true);
    }

    /**
     * Scope a query to only include releases for a given channel.
     */
    public function scopeOnChannel($query, string $channel)
    {
        return $query->where('channel', $channel);
    }
}
