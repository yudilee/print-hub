<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds a JSON column to store per-printer capability data (trays, resolutions,
     * media sizes, color modes) discovered by the TrayPrint agent.
     */
    public function up(): void
    {
        Schema::table('print_agents', function (Blueprint $table) {
            $table->json('capabilities')->nullable()->after('printers');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('print_agents', function (Blueprint $table) {
            $table->dropColumn('capabilities');
        });
    }
};
