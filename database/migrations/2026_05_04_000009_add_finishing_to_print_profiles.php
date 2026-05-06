<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('print_profiles', function (Blueprint $table) {
            $table->string('finishing_staple')->nullable();
            $table->string('finishing_punch')->nullable();
            $table->boolean('finishing_booklet')->default(false);
            $table->string('finishing_fold')->nullable();
            $table->string('finishing_bind')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('print_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'finishing_staple',
                'finishing_punch',
                'finishing_booklet',
                'finishing_fold',
                'finishing_bind',
            ]);
        });
    }
};
