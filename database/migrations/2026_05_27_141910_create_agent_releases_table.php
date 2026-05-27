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
        Schema::create('agent_releases', function (Blueprint $table) {
            $table->id();
            $table->string('version', 50);
            $table->string('platform', 20); // linux, windows, macos
            $table->string('channel', 20)->default('stable'); // stable, beta, alpha
            $table->string('file_original_name');
            $table->string('file_stored_path');
            $table->string('file_mime_type', 100)->nullable();
            $table->bigInteger('file_size')->unsigned()->nullable();
            $table->string('sha256_hash', 64);
            $table->text('release_notes')->nullable();
            $table->boolean('is_mandatory')->default(false);
            $table->boolean('is_latest')->default(false);
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->timestamps();

            $table->foreign('uploaded_by')->references('id')->on('users')->onDelete('set null');

            $table->unique(['version', 'platform', 'channel']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_releases');
    }
};
