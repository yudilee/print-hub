<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_schemas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_app_id')->nullable()->constrained('client_apps')->nullOnDelete();
            $table->string('schema_name')->unique();
            $table->string('label')->nullable();
            $table->json('fields')->nullable();
            $table->json('tables')->nullable();
            $table->json('sample_data')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_schemas');
    }
};
