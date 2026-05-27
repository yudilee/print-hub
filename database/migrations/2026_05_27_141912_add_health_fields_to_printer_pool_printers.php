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
        Schema::table('printer_pool_printers', function (Blueprint $table) {
            $table->timestamp('last_healthy_at')->nullable();
            $table->integer('failure_count')->default(0);
            $table->boolean('is_healthy')->default(true);
            $table->timestamp('last_error_at')->nullable();
            $table->text('last_error_message')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('printer_pool_printers', function (Blueprint $table) {
            $table->dropColumn([
                'last_healthy_at',
                'failure_count',
                'is_healthy',
                'last_error_at',
                'last_error_message',
            ]);
        });
    }
};
