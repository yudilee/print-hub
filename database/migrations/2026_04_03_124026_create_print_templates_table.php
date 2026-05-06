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
        Schema::create('print_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->decimal('paper_width_mm', 8, 2)->default(215.90);
            $table->decimal('paper_height_mm', 8, 2)->default(139.70);
            $table->string('background_image_path')->nullable();
            $table->json('elements')->nullable(); // Stores [ {type, key, x, y, options...}, ... ]
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('print_templates');
    }
};
