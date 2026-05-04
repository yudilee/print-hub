<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('print_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('original_name');
            $table->string('stored_filename');
            $table->string('mime_type');
            $table->integer('file_size'); // bytes
            $table->integer('page_count')->nullable();
            $table->string('disk')->default('local');
            $table->string('storage_path');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Add document_id to print_jobs
        Schema::table('print_jobs', function (Blueprint $table) {
            $table->foreignId('document_id')->nullable()->after('branch_id')->constrained('print_documents')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('print_jobs', function (Blueprint $table) {
            $table->dropForeign(['document_id']);
            $table->dropColumn('document_id');
        });

        Schema::dropIfExists('print_documents');
    }
};
