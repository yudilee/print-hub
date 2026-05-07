<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('print_profiles', function (Blueprint $table) {
            $table->json('watermark_copy_texts')->nullable()->after('watermark_position');
        });
    }

    public function down(): void
    {
        Schema::table('print_profiles', function (Blueprint $table) {
            $table->dropColumn('watermark_copy_texts');
        });
    }
};
