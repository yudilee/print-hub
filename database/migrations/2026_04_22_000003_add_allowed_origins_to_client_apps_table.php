<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_apps', function (Blueprint $table) {
            // JSON array of allowed origin URLs for this client app
            // e.g. ["https://myapp.company.com", "http://localhost:8082"]
            $table->json('allowed_origins')->nullable()->after('api_key');
        });
    }

    public function down(): void
    {
        Schema::table('client_apps', function (Blueprint $table) {
            $table->dropColumn('allowed_origins');
        });
    }
};
