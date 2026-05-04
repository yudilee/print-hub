<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_apps', function (Blueprint $table) {
            $table->json('webhook_events')->nullable()->after('allowed_origins');
            $table->integer('webhook_retry_count')->default(3)->after('webhook_events');
            $table->integer('webhook_timeout')->default(10)->after('webhook_retry_count');
            $table->string('webhook_secret')->nullable()->after('webhook_timeout');
        });

        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_app_id')->constrained()->cascadeOnDelete();
            $table->string('event_type');
            $table->json('payload');
            $table->string('status'); // 'success', 'failed', 'retrying'
            $table->integer('attempts')->default(0);
            $table->integer('max_attempts')->default(3);
            $table->integer('response_code')->nullable();
            $table->text('response_body')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');

        Schema::table('client_apps', function (Blueprint $table) {
            $table->dropColumn([
                'webhook_events',
                'webhook_retry_count',
                'webhook_timeout',
                'webhook_secret',
            ]);
        });
    }
};
