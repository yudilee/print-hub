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
            // Queue lease tracking
            $table->timestamp('dispatched_at')->nullable()->after('status');
            
            // Retry lineage
            $table->foreignId('retried_from_job_id')
                ->nullable()
                ->after('depends_on_job_id')
                ->constrained('print_jobs')
                ->nullOnDelete();
            
            $table->integer('retry_count')->default(0)->after('retried_from_job_id');

            // Optimizing indexes
            $table->index(['print_agent_id', 'status', 'approval_status'], 'idx_agent_status_approval');
            $table->index('scheduled_at', 'idx_scheduled_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('print_jobs', function (Blueprint $table) {
            $table->dropIndex('idx_agent_status_approval');
            $table->dropIndex('idx_scheduled_at');
            
            $table->dropForeign(['retried_from_job_id']);
            $table->dropColumn(['dispatched_at', 'retried_from_job_id', 'retry_count']);
        });
    }
};
