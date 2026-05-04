<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('print_profiles', function (Blueprint $table) {
            $table->string('tray_source')->nullable()->after('default_printer')
                ->comment('Printer tray/paper source (auto, tray1, tray2, manual, envelope)');
            $table->string('color_mode')->default('color')->after('tray_source')
                ->comment('Color mode: color, monochrome');
            $table->string('print_quality')->default('normal')->after('color_mode')
                ->comment('Print quality: draft, normal, high');
            $table->integer('scaling_percentage')->default(100)->after('print_quality')
                ->comment('Print scaling percentage (1-400)');
            $table->string('media_type')->nullable()->after('scaling_percentage')
                ->comment('Media type: plain, glossy, envelope, label, continuous_feed');
            $table->boolean('collate')->default(true)->after('media_type')
                ->comment('Collate multiple copies');
            $table->boolean('reverse_order')->default(false)->after('collate')
                ->comment('Print in reverse page order');
        });
    }

    public function down(): void
    {
        Schema::table('print_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'tray_source',
                'color_mode',
                'print_quality',
                'scaling_percentage',
                'media_type',
                'collate',
                'reverse_order',
            ]);
        });
    }
};
