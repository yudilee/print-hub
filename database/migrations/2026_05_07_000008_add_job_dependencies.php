<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('print_jobs', function (Blueprint $table) {
            $table->foreignId('depends_on_job_id')
                ->nullable()
                ->after('pool_id')
                ->constrained('print_jobs')
                ->nullOnDelete();

            $table->string('dependency_type')
                ->nullable()
                ->after('depends_on_job_id')
                ->comment('after, after_success, after_failure');
        });
    }

    public function down(): void
    {
        Schema::table('print_jobs', function (Blueprint $table) {
            $table->dropForeign(['depends_on_job_id']);
            $table->dropColumn(['depends_on_job_id', 'dependency_type']);
        });
    }
};
