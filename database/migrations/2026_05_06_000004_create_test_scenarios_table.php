<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('test_scenarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('print_template_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('data');
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index('print_template_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_scenarios');
    }
};
