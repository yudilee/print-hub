<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('print_agents', function (Blueprint $table) {
            $table->timestamp('last_key_rotated_at')->nullable()->after('agent_key');
        });

        Schema::table('client_apps', function (Blueprint $table) {
            $table->timestamp('last_key_rotated_at')->nullable()->after('api_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('print_agents', function (Blueprint $table) {
            $table->dropColumn('last_key_rotated_at');
        });

        Schema::table('client_apps', function (Blueprint $table) {
            $table->dropColumn('last_key_rotated_at');
        });
    }
};
