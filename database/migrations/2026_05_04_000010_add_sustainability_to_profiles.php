<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('print_profiles', function (Blueprint $table) {
            // Eco Mode — global toggle for sustainable printing features
            $table->boolean('eco_mode')->default(false);

            // Duplex saved counter (how many pages saved by duplex)
            $table->integer('duplex_saved')->default(0);

            // Force grayscale (monochrome) for all print jobs in this profile
            $table->boolean('grayscale_force')->default(false);

            // Number of pages per sheet (N-up: 1, 2, 4, 6, 8, 9, 16)
            $table->integer('pages_per_sheet')->default(1);

            // Remove embedded images from documents to reduce toner/ink usage
            $table->boolean('remove_images')->default(false);

            // Estimated carbon savings (grams CO₂) — accumulated over time
            $table->decimal('carbon_saved', 12, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('print_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'eco_mode',
                'duplex_saved',
                'grayscale_force',
                'pages_per_sheet',
                'remove_images',
                'carbon_saved',
            ]);
        });
    }
};
