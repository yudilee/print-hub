<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('printer_pools', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('strategy')->default('round_robin'); // round_robin, least_busy, random, failover
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('printer_pool_printers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pool_id')->constrained('printer_pools')->cascadeOnDelete();
            $table->string('printer_name');
            $table->integer('priority')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::table('print_jobs', function (Blueprint $table) {
            $table->foreignId('pool_id')->nullable()->constrained('printer_pools')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('print_jobs', function (Blueprint $table) {
            $table->dropForeign(['pool_id']);
            $table->dropColumn('pool_id');
        });

        Schema::dropIfExists('printer_pool_printers');
        Schema::dropIfExists('printer_pools');
    }
};
