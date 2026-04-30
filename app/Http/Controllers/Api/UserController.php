<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserController extends Controller
{
    public function sync(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email',
            'name' => 'required|string',
            'role' => 'nullable|string|in:admin,user',
            'action' => 'required|string|in:create_or_update,delete',
            'password' => 'nullable|string',
            'auth_source' => 'nullable|string',
        ]);

        if ($data['action'] === 'delete') {
            User::where('email', $data['email'])->delete();
            return response()->json(['message' => 'User deleted successfully']);
        }

        $user = User::updateOrCreate(
            ['email' => $data['email']],
            [
                'name' => $data['name'],
                'role' => $data['role'] ?? 'user',
                'auth_source' => $data['auth_source'] ?? 'api',
            ]
        );

        if (!empty($data['password'])) {
            $user->update(['password' => Hash::make($data['password'])]);
        } elseif (!$user->password) {
            $user->update(['password' => Hash::make(Str::random(16))]);
        }

        return response()->json(['message' => 'User synced successfully', 'user' => $user]);
    }
}
