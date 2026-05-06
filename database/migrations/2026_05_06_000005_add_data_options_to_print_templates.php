<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('print_templates', function (Blueprint $table) {
            $table->json('data_options')->nullable()->after('parameters')
                ->comment('JSON: sortFields, groupFields, filterExpression for SGF panel');
        });
    }

    public function down(): void
    {
        Schema::table('print_templates', function (Blueprint $table) {
            $table->dropColumn('data_options');
        });
    }
};
