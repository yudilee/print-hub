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
        Schema::table('print_templates', function (Blueprint $table) {
            // Laravel defaults to 'print_templates_name_unique' for unique string name
            // If that fails, the user might need to fix it, but this is standard.
            $table->dropUnique('print_templates_name_unique');
            $table->integer('version')->default(1)->after('name');
            $table->boolean('is_latest')->default(true)->after('version');
            $table->unique(['name', 'version']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('print_templates', function (Blueprint $table) {
            $table->dropUnique(['name', 'version']);
            $table->dropColumn(['version', 'is_latest']);
            $table->unique('name');
        });
    }
};
