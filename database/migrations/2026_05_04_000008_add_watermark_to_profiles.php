<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('print_profiles', function (Blueprint $table) {
            $table->string('watermark_text')->nullable();
            $table->decimal('watermark_opacity', 3, 2)->default(0.3);
            $table->integer('watermark_rotation')->default(-45);
            $table->string('watermark_position')->default('center');
        });
    }

    public function down(): void
    {
        Schema::table('print_profiles', function (Blueprint $table) {
            $table->dropColumn(['watermark_text', 'watermark_opacity', 'watermark_rotation', 'watermark_position']);
        });
    }
};
