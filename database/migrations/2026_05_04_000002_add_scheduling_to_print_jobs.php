<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('print_jobs', function (Blueprint $table) {
            $table->timestamp('scheduled_at')->nullable()->after('priority');
            $table->string('recurrence')->nullable()->after('scheduled_at');
            $table->timestamp('recurrence_end_at')->nullable()->after('recurrence');
            $table->integer('recurrence_count')->nullable()->after('recurrence_end_at');
        });
    }

    public function down(): void
    {
        Schema::table('print_jobs', function (Blueprint $table) {
            $table->dropColumn(['scheduled_at', 'recurrence', 'recurrence_end_at', 'recurrence_count']);
        });
    }
};
