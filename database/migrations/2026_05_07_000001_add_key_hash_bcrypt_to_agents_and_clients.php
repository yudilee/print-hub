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
            $table->text('key_hash_bcrypt')->nullable()->after('agent_key');
        });

        Schema::table('client_apps', function (Blueprint $table) {
            $table->text('key_hash_bcrypt')->nullable()->after('api_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('print_agents', function (Blueprint $table) {
            $table->dropColumn('key_hash_bcrypt');
        });

        Schema::table('client_apps', function (Blueprint $table) {
            $table->dropColumn('key_hash_bcrypt');
        });
    }
};
