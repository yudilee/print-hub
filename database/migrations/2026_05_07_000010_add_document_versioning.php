<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('print_documents', function (Blueprint $table) {
            $table->integer('version')
                ->default(1)
                ->after('auto_delete')
                ->comment('Document version number, incremented on re-upload');

            $table->foreignId('previous_version_id')
                ->nullable()
                ->after('version')
                ->constrained('print_documents')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('print_documents', function (Blueprint $table) {
            $table->dropForeign(['previous_version_id']);
            $table->dropColumn(['version', 'previous_version_id']);
        });
    }
};
