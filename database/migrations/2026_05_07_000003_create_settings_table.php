<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('type')->default('string'); // string, boolean, integer, json
            $table->timestamps();
        });

        // Insert default settings
        $defaults = [
            // General
            ['key' => 'app_name',              'value' => 'Print Hub',         'type' => 'string'],
            ['key' => 'timezone',              'value' => 'UTC',               'type' => 'string'],
            ['key' => 'default_locale',        'value' => 'en',                'type' => 'string'],
            // Print Defaults
            ['key' => 'default_copies',        'value' => '1',                 'type' => 'integer'],
            ['key' => 'default_duplex_mode',   'value' => 'none',              'type' => 'string'],
            ['key' => 'default_paper_size',    'value' => 'A4',                'type' => 'string'],
            // Job Retention
            ['key' => 'retain_completed_days', 'value' => '30',                'type' => 'integer'],
            ['key' => 'retain_failed_days',    'value' => '14',                'type' => 'integer'],
            // Rate Limiting
            ['key' => 'rate_limit_client_app', 'value' => '60',                'type' => 'integer'],
            ['key' => 'rate_limit_agent',      'value' => '120',               'type' => 'integer'],
            // Webhook Defaults
            ['key' => 'webhook_default_retry', 'value' => '3',                 'type' => 'integer'],
            ['key' => 'webhook_default_timeout','value' => '30',               'type' => 'integer'],
            // API Key Rotation Policy (Item 12.4)
            ['key' => 'key_rotation_days',     'value' => '90',                'type' => 'integer'],
            // Per-App/Agent Rate Limits (Item 15.1)
            ['key' => 'max_requests_per_minute_client', 'value' => '60',       'type' => 'integer'],
            ['key' => 'max_requests_per_minute_agent',  'value' => '120',      'type' => 'integer'],
            // Session Management (Item 12.2)
            ['key' => 'session_expiry_minutes','value' => '480',               'type' => 'integer'],
        ];

        foreach ($defaults as $setting) {
            DB::table('settings')->insert($setting);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
