<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');                    // e.g. "Surya Darma Perkasa"
            $table->string('code')->unique();          // e.g. "SDP"
            $table->string('short_name')->nullable();  // e.g. "Harent"
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('name');                    // e.g. "SDP - Surabaya Office"
            $table->string('code')->unique();          // e.g. "SDP-SBY"
            $table->text('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Add branch_id and company_id to existing tables
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('auth_source')->constrained('branches')->nullOnDelete();
            $table->foreignId('company_id')->nullable()->after('branch_id')->constrained('companies')->nullOnDelete();
        });

        Schema::table('print_agents', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('department')->constrained('branches')->nullOnDelete();
        });

        Schema::table('print_profiles', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('print_agent_id')->constrained('branches')->nullOnDelete();
        });

        Schema::table('print_jobs', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('print_agent_id')->constrained('branches')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('print_jobs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('branch_id');
        });

        Schema::table('print_profiles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('branch_id');
        });

        Schema::table('print_agents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('branch_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('branch_id');
            $table->dropConstrainedForeignId('company_id');
        });

        Schema::dropIfExists('branches');
        Schema::dropIfExists('companies');
    }
};
