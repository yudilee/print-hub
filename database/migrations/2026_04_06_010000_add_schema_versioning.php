<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ─── Schema Versioning ───────────────────────────────────
        // Remove the unique constraint on schema_name so we can have multiple versions
        Schema::table('data_schemas', function (Blueprint $table) {
            $table->dropUnique(['schema_name']);
        });

        Schema::table('data_schemas', function (Blueprint $table) {
            $table->unsignedInteger('version')->default(1)->after('schema_name');
            $table->boolean('is_latest')->default(true)->after('version');
            $table->json('changelog')->nullable()->after('sample_data');

            // Composite unique: one version per schema_name
            $table->unique(['schema_name', 'version']);

            // Index for fast "latest" lookups
            $table->index(['schema_name', 'is_latest']);
        });

        // ─── Template schema version binding ─────────────────────
        Schema::table('print_templates', function (Blueprint $table) {
            $table->unsignedInteger('data_schema_version')->nullable()->after('data_schema_id');
        });
    }

    public function down(): void
    {
        Schema::table('print_templates', function (Blueprint $table) {
            $table->dropColumn('data_schema_version');
        });

        Schema::table('data_schemas', function (Blueprint $table) {
            $table->dropIndex(['schema_name', 'is_latest']);
            $table->dropUnique(['schema_name', 'version']);
            $table->dropColumn(['version', 'is_latest', 'changelog']);
        });

        Schema::table('data_schemas', function (Blueprint $table) {
            $table->unique('schema_name');
        });
    }
};
