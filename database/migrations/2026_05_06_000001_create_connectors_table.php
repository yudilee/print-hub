<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('connectors', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('client_app_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('type'); // api, webhook, odoo, custom
            $table->text('config'); // JSON: endpoint URL, auth type, headers, etc.
            $table->string('icon')->nullable(); // emoji or URL
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_test_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('client_app_id');
            $table->index('type');
            $table->index('is_active');
        });

        // Pivot table for connector-template many-to-many relationship
        Schema::create('connector_template', function (Blueprint $table) {
            $table->uuid('connector_id');
            $table->foreignId('print_template_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->foreign('connector_id')->references('id')->on('connectors')->cascadeOnDelete();
            $table->primary(['connector_id', 'print_template_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('connector_template');
        Schema::dropIfExists('connectors');
    }
};
