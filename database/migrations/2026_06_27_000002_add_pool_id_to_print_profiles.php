<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('print_profiles', function (Blueprint $table) {
            $table->foreignId('pool_id')
                ->nullable()
                ->after('default_printer')
                ->constrained('printer_pools')
                ->nullOnDelete();
        });

        Schema::table('print_jobs', function (Blueprint $table) {
            $table->string('printer_name')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('print_jobs', function (Blueprint $table) {
            $table->string('printer_name')->nullable(false)->change();
        });

        Schema::table('print_profiles', function (Blueprint $table) {
            $table->dropForeign(['pool_id']);
            $table->dropColumn('pool_id');
        });
    }
};
