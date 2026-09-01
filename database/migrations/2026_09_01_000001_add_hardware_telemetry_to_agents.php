<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds hardware_status (paper out, jam, low toner per printer)
     * and last_telemetry_at timestamp for live health monitoring.
     */
    public function up(): void
    {
        Schema::table('print_agents', function (Blueprint $table) {
            $table->json('hardware_status')->nullable()->after('capabilities');
            $table->timestamp('last_telemetry_at')->nullable()->after('last_seen_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('print_agents', function (Blueprint $table) {
            $table->dropColumn(['hardware_status', 'last_telemetry_at']);
        });
    }
};
