<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('print_profiles', function (Blueprint $table) {
            // Rename column: watermark_copy_texts -> watermark_copies
            // SQLite doesn't support renameColumn well, so we add new and drop old
            if (Schema::hasColumn('print_profiles', 'watermark_copy_texts')) {
                $table->json('watermark_copies')->nullable()->after('watermark_position');
            }
        });

        // Migrate existing data: copy old values to new column
        DB::table('print_profiles')
            ->whereNotNull('watermark_copy_texts')
            ->update([
                'watermark_copies' => DB::raw('watermark_copy_texts'),
            ]);

        Schema::table('print_profiles', function (Blueprint $table) {
            if (Schema::hasColumn('print_profiles', 'watermark_copy_texts')) {
                $table->dropColumn('watermark_copy_texts');
            }
        });
    }

    public function down(): void
    {
        Schema::table('print_profiles', function (Blueprint $table) {
            $table->json('watermark_copy_texts')->nullable()->after('watermark_position');
        });

        DB::table('print_profiles')
            ->whereNotNull('watermark_copies')
            ->update([
                'watermark_copy_texts' => DB::raw('watermark_copies'),
            ]);

        Schema::table('print_profiles', function (Blueprint $table) {
            $table->dropColumn('watermark_copies');
        });
    }
};
