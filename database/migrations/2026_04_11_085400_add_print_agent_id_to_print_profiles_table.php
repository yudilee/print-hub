<?php
/*
 * (c) Yudi Lee - Print Hub
 */

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
        Schema::table('print_profiles', function (Blueprint $table) {
            $table->foreignId('print_agent_id')
                ->after('description')
                ->nullable()
                ->constrained('print_agents')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('print_profiles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('print_agent_id');
        });
    }
};
