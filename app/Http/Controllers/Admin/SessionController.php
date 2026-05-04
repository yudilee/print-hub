<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\User;

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
        }

        return view('admin.users.sessions', ['sessions' => $sessions]);
    }

    public function destroy($id)
    {
        DB::table('sessions')->where('id', $id)->delete();
        return redirect()->route('admin.sessions')->with('success', 'Session revoked successfully.');
    }
}
