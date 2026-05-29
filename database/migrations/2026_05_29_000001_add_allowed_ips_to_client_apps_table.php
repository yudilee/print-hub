<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_apps', function (Blueprint $table) {
            $table->json('allowed_ips')->nullable()->after('allowed_origins');
        });
    }

    public function down(): void
    {
        Schema::table('client_apps', function (Blueprint $table) {
            $table->dropColumn('allowed_ips');
        });
    }
};
