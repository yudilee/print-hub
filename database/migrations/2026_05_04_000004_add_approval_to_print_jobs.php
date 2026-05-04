<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('print_jobs', function (Blueprint $table) {
            $table->string('approval_status')->default('auto_approved')->after('recurrence_count');
            $table->foreignId('approved_by')->nullable()->after('approval_status')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->text('rejected_reason')->nullable()->after('approved_at');
            $table->boolean('requires_approval')->default(false)->after('rejected_reason');
        });

        Schema::create('print_approval_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('rule_type'); // 'user', 'role', 'page_count', 'cost'
            $table->string('rule_value'); // the value to match
            $table->boolean('requires_approval')->default(true);
            $table->foreignId('approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('print_approval_rules');

        Schema::table('print_jobs', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->dropColumn([
                'approval_status',
                'approved_by',
                'approved_at',
                'rejected_reason',
                'requires_approval',
            ]);
        });
    }
};
