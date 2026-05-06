<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $password = env('SEEDER_ADMIN_PASSWORD', Str::random(16));

        User::updateOrCreate(
            ['email' => 'admin@printhub.local'],
            [
                'name'        => 'Administrator',
                'password'    => Hash::make($password),
                'role'        => 'super-admin',
                'auth_source' => 'local',
            ]
        );

        // Only output password in non-production environments
        if (app()->environment('local', 'testing', 'development')) {
            $this->command?->info("Admin user created: admin@printhub.local / {$password}");
        }
    }
}
