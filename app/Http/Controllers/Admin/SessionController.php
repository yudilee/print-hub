<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SessionController extends Controller
{
    public function index()
    {
        $sessions = DB::table('sessions')
            ->orderBy('last_activity', 'desc')
            ->get()
            ->filter(function ($session) {
                if (!empty($session->user_id)) {
                    return true;
                }
                $payload = $session->payload;
                $data = @unserialize(base64_decode($payload));
                if (is_array($data)) {
                    foreach ($data as $key => $value) {
                        if (str_starts_with($key, 'login_web_')) {
                            $session->user_id = $value;
                            return true;
                        }
                    }
                }
                return false;
            })
            ->values();

        $userIds = $sessions->pluck('user_id')->unique();
        $users = User::whereIn('id', $userIds)->get()->keyBy('id');

        foreach ($sessions as $session) {
            $session->user = $users->get($session->user_id);
            // Parse user agent for device/browser info
            $uaInfo = $this->parseUserAgent($session->user_agent ?? '');
            $session->device_type = $uaInfo['device_type'];
            $session->browser = $uaInfo['browser'];
            $session->platform = $uaInfo['platform'];
        }

        $sessionExpiryMinutes = Setting::getValue('session_expiry_minutes', 480);

        return view('admin.users.sessions', [
            'sessions' => $sessions,
            'sessionExpiryMinutes' => $sessionExpiryMinutes,
        ]);
    }

    public function destroy($id)
    {
        DB::table('sessions')->where('id', $id)->delete();
        return redirect()->route('admin.sessions')->with('success', 'Session revoked successfully.');
    }

    /**
     * Force logout all sessions for a specific user.
     */
    public function forceLogoutUser(Request $request, User $user)
    {
        DB::table('sessions')->where('user_id', $user->id)->delete();

        return redirect()->route('admin.sessions')
            ->with('success', "All sessions for {$user->name} have been terminated.");
    }

    /**
     * Force logout all sessions across the system (except current).
     */
    public function forceLogoutAll(Request $request)
    {
        $currentSessionId = session()->getId();

        DB::table('sessions')
            ->where('id', '!=', $currentSessionId)
            ->delete();

        return redirect()->route('admin.sessions')
            ->with('success', 'All other sessions have been terminated.');
    }

    /**
     * Parse a user agent string into device/browser/platform info.
     */
    private function parseUserAgent(?string $userAgent): array
    {
        $deviceType = 'desktop';
        $browser = 'Unknown';
        $platform = 'Unknown';

        if (empty($userAgent)) {
            return ['device_type' => $deviceType, 'browser' => $browser, 'platform' => $platform];
        }

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
}
