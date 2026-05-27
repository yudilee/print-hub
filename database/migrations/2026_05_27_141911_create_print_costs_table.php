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
        Schema::create('print_costs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('print_job_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained();
            $table->foreignId('print_agent_id')->nullable()->constrained('print_agents');
            $table->integer('pages_printed')->default(0);
            $table->boolean('is_color')->default(false);
            $table->decimal('cost_per_page', 10, 4)->default(0);
            $table->decimal('total_cost', 12, 2)->default(0);
            $table->string('currency', 3)->default('IDR');
            $table->timestamps();
        });

        Schema::table('branches', function (Blueprint $table) {
            $table->decimal('bw_cost_per_page', 10, 4)->default(0.05)->after('active');
            $table->decimal('color_cost_per_page', 10, 4)->default(0.25)->after('bw_cost_per_page');
            $table->string('currency', 3)->default('IDR')->after('color_cost_per_page');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn(['bw_cost_per_page', 'color_cost_per_page', 'currency']);
        });

        Schema::dropIfExists('print_costs');
    }
};
