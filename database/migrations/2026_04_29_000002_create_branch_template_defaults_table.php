<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_template_defaults', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('print_template_id')->constrained('print_templates')->cascadeOnDelete();
            $table->foreignId('print_profile_id')->constrained('print_profiles')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['branch_id', 'print_template_id'], 'branch_template_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_template_defaults');
    }
};
