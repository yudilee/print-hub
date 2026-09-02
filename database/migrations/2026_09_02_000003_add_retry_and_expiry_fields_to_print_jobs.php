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
        Schema::table('print_jobs', function (Blueprint $table) {
            $table->string('error_code', 50)->nullable()->after('error');
            $table->unsignedInteger('max_retries')->default(3)->after('retry_count');
            $table->string('retry_reason')->nullable()->after('max_retries');
            $table->timestamp('expires_at')->nullable()->after('scheduled_at');
            $table->timestamp('dead_lettered_at')->nullable()->after('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('print_jobs', function (Blueprint $table) {
            $table->dropColumn([
                'error_code',
                'max_retries',
                'retry_reason',
                'expires_at',
                'dead_lettered_at',
            ]);
        });
    }
};
