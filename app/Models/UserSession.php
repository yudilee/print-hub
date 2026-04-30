<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSession extends Model
{
    protected $fillable = [
        'user_id', 'session_id', 'ip_address', 'user_agent',
        'device_type', 'browser', 'platform', 'location',
        'is_current', 'last_active_at',
    ];

    protected function casts(): array
    {
        return [
            'is_current' => 'boolean',
            'last_active_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function parseUserAgent(string $userAgent): array
    {
        $deviceType = 'desktop';
        $browser = 'Unknown';
        $platform = 'Unknown';

        if (preg_match('/mobile|android|iphone|ipad|ipod/i', $userAgent)) {
            $deviceType = preg_match('/tablet|ipad/i', $userAgent) ? 'tablet' : 'mobile';
        }

        if (preg_match('/Firefox/i', $userAgent)) $browser = 'Firefox';
        elseif (preg_match('/Chrome/i', $userAgent)) $browser = 'Chrome';
        elseif (preg_match('/Safari/i', $userAgent)) $browser = 'Safari';
        elseif (preg_match('/Edge/i', $userAgent)) $browser = 'Edge';
        elseif (preg_match('/Opera|OPR/i', $userAgent)) $browser = 'Opera';

        if (preg_match('/Windows/i', $userAgent)) $platform = 'Windows';
        elseif (preg_match('/Macintosh|Mac OS/i', $userAgent)) $platform = 'macOS';
        elseif (preg_match('/Linux/i', $userAgent)) $platform = 'Linux';
        elseif (preg_match('/Android/i', $userAgent)) $platform = 'Android';
        elseif (preg_match('/iPhone|iPad|iPod/i', $userAgent)) $platform = 'iOS';

        return ['device_type' => $deviceType, 'browser' => $browser, 'platform' => $platform];
    }

    public static function recordLogin(int $userId, string $sessionId, ?string $ip = null, ?string $userAgent = null): self
    {
        $info = $userAgent ? self::parseUserAgent($userAgent) : [];
        self::where('user_id', $userId)->update(['is_current' => false]);
        
        return self::updateOrCreate(
            ['session_id' => $sessionId],
            [
                'user_id' => $userId,
                'ip_address' => $ip,
                'user_agent' => $userAgent,
                'device_type' => $info['device_type'] ?? null,
                'browser' => $info['browser'] ?? null,
                'platform' => $info['platform'] ?? null,
                'is_current' => true,
                'last_active_at' => now(),
            ]
        );
    }
}
