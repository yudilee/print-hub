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
            $table->boolean('is_custom')->default(false)->after('paper_size');
            $table->decimal('custom_width', 8, 2)->nullable()->after('is_custom');
            $table->decimal('custom_height', 8, 2)->nullable()->after('custom_width');
            $table->decimal('margin_top', 5, 2)->default(0)->after('custom_height');
            $table->decimal('margin_bottom', 5, 2)->default(0)->after('margin_top');
            $table->decimal('margin_left', 5, 2)->default(0)->after('margin_bottom');
            $table->decimal('margin_right', 5, 2)->default(0)->after('margin_left');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('print_profiles', function (Blueprint $table) {
            $table->dropColumn(['is_custom', 'custom_width', 'custom_height', 'margin_top', 'margin_bottom', 'margin_left', 'margin_right']);
        });
    }
};
