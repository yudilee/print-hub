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
            ->whereNotNull('user_id')
            ->orderBy('last_activity', 'desc')
            ->get();
            
        $userIds = $sessions->pluck('user_id')->unique();
        $users = User::whereIn('id', $userIds)->get()->keyBy('id');
        
        foreach ($sessions as $session) {
            $session->user = $users->get($session->user_id);
        }

        return view('admin.users.sessions', compact('sessions'));
    }

    public function destroy($id)
    {
        DB::table('sessions')->where('id', $id)->delete();
        return redirect()->route('admin.sessions')->with('success', 'Session revoked successfully.');
    }
}
