<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Company;
use App\Models\PrintAgent;
use App\Models\PrintProfile;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Create Companies
        $hrm = Company::updateOrCreate(
            ['code' => 'HRM'],
            ['name' => 'Hartono Raya Motor Group']
        );

        $sdp = Company::updateOrCreate(
            ['code' => 'SDP'],
            ['name' => 'Surya Darma Perkasa', 'short_name' => 'Harent']
        );

        // 2. Create Branches
        $hq = Branch::updateOrCreate(
            ['code' => 'HRM-HQ'],
            ['company_id' => $hrm->id, 'name' => 'Headquarters']
        );

        $sdpMain = Branch::updateOrCreate(
            ['code' => 'SDP-MAIN'],
            ['company_id' => $sdp->id, 'name' => 'SDP - Main']
        );

        // 3. Migrate existing data to SDP-MAIN branch
        PrintAgent::whereNull('branch_id')->update(['branch_id' => $sdpMain->id]);
        PrintProfile::whereNull('branch_id')->update(['branch_id' => $sdpMain->id]);

        // 4. Create / update super-admin user at HQ
        $defaultPassword = env('SEEDER_DEFAULT_PASSWORD', \Illuminate\Support\Str::random(16));

        User::updateOrCreate(
            ['email' => 'yudi.it@hrmsby.co.id'],
            [
                'name'        => 'Yudi (Admin)',
                'password'    => \Illuminate\Support\Facades\Hash::make($defaultPassword),
                'role'        => 'super-admin',
                'auth_source' => 'local',
                'branch_id'   => $hq->id,
                'company_id'  => $hrm->id,
            ]
        );

        // Also update the admin@printhub.local account if it exists
        $localAdmin = User::where('email', 'admin@printhub.local')->first();
        if ($localAdmin) {
            $localAdmin->update([
                'role'       => 'super-admin',
                'branch_id'  => $hq->id,
                'company_id' => $hrm->id,
            ]);
        }

        // 5. Seed demo data in non-production environments
        if (!app()->environment('production')) {
            $this->call(DemoDataSeeder::class);
        }
    }
}
