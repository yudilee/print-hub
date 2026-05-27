<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mfa_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('secret');                          // Base32-encoded TOTP secret
            $table->boolean('is_enabled')->default(false);     // Whether MFA is active
            $table->timestamp('last_verified_at')->nullable(); // Last successful MFA verification
            $table->json('recovery_codes')->nullable();        // One-time recovery codes
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mfa_tokens');
    }
};
