<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('print_agents', function (Blueprint $table) {
            $table->id();
            $table->string('name');                     // e.g. "PC Front Office"
            $table->string('agent_key')->unique();      // Auth token for the agent
            $table->string('ip_address')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('print_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();           // e.g. "invoice_sewa"
            $table->string('description')->nullable();
            $table->string('paper_size')->default('A4'); // A4, A5, Letter, Legal
            $table->string('orientation')->default('portrait'); // portrait, landscape
            $table->integer('copies')->default(1);
            $table->string('duplex')->default('one-sided'); // one-sided, two-sided-long, two-sided-short
            $table->string('default_printer')->nullable();  // suggestion, agent uses its local printer if empty
            $table->json('extra_options')->nullable();
            $table->timestamps();
        });

        Schema::create('print_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('job_id');                   // UUID from the agent
            $table->foreignId('print_agent_id')->nullable()->constrained('print_agents')->nullOnDelete();
            $table->string('printer_name');
            $table->string('type')->default('raw');     // raw, pdf
            $table->string('status');                   // pending, success, failed
            $table->text('error')->nullable();
            $table->json('options')->nullable();
            $table->timestamp('agent_created_at')->nullable();
            $table->timestamp('agent_completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('print_jobs');
        Schema::dropIfExists('print_profiles');
        Schema::dropIfExists('print_agents');
    }
};
