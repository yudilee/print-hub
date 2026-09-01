<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds polymorphic notifiable columns (notifiable_type, notifiable_id) to the
     * notifications table to comply with Laravel's DatabaseChannel notification standard.
     */
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            if (!Schema::hasColumn('notifications', 'notifiable_type')) {
                $table->string('notifiable_type')->default('App\\Models\\User')->after('id');
            }
            if (!Schema::hasColumn('notifications', 'notifiable_id')) {
                $table->unsignedBigInteger('notifiable_id')->nullable()->after('notifiable_type');
            }
        });

        // Sync existing records if any
        if (Schema::hasColumn('notifications', 'user_id')) {
            DB::table('notifications')
                ->whereNull('notifiable_id')
                ->whereNotNull('user_id')
                ->update([
                    'notifiable_id' => DB::raw('user_id'),
                    'notifiable_type' => 'App\\Models\\User'
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropColumn(['notifiable_type', 'notifiable_id']);
        });
    }
};
