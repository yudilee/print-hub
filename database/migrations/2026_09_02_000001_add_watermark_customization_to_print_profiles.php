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
        Schema::table('print_profiles', function (Blueprint $table) {
            $table->string('watermark_color', 7)->nullable()->default('#B4B4B4');
            $table->integer('watermark_font_size')->nullable();
            $table->string('watermark_font_family', 50)->nullable()->default('Arial');
            $table->string('watermark_font_style', 10)->nullable()->default('B');
            $table->float('watermark_transparency')->nullable()->default(0.3);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('print_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'watermark_color',
                'watermark_font_size',
                'watermark_font_family',
                'watermark_font_style',
                'watermark_transparency',
            ]);
        });
    }
};
