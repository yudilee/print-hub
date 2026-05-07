<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('print_documents', function (Blueprint $table) {
            $table->timestamp('retain_until')
                ->nullable()
                ->after('metadata')
                ->comment('Documents past this date are eligible for purging');

            $table->boolean('auto_delete')
                ->default(false)
                ->after('retain_until')
                ->comment('Whether to automatically delete when retain_until is reached');
        });
    }

    public function down(): void
    {
        Schema::table('print_documents', function (Blueprint $table) {
            $table->dropColumn(['retain_until', 'auto_delete']);
        });
    }
};
