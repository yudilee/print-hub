<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('print_templates', function (Blueprint $table) {
            $table->foreignId('data_schema_id')->nullable()->after('id')->constrained('data_schemas')->nullOnDelete();
        });

        Schema::table('print_jobs', function (Blueprint $table) {
            $table->json('template_data')->nullable()->after('options');
            $table->string('template_name')->nullable()->after('template_data');
        });
    }

    public function down(): void
    {
        Schema::table('print_templates', function (Blueprint $table) {
            $table->dropForeign(['data_schema_id']);
            $table->dropColumn('data_schema_id');
        });

        Schema::table('print_jobs', function (Blueprint $table) {
            $table->dropColumn(['template_data', 'template_name']);
        });
    }
};
