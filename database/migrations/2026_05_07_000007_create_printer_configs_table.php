<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('printer_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('print_agent_id')->constrained('print_agents')->cascadeOnDelete();
            $table->string('printer_name');
            $table->json('config');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['print_agent_id', 'printer_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('printer_configs');
    }
};
