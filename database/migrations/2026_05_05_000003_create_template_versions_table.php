<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('template_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('print_template_id')->constrained()->cascadeOnDelete();
            $table->integer('version_number')->unsigned();
            $table->json('elements');           // full elements JSON snapshot
            $table->json('styles')->nullable(); // styles JSON snapshot
            $table->json('properties')->nullable(); // template properties (paper size, margins, etc.)
            $table->string('label')->nullable();    // user-defined label like "Pre-release v2"
            $table->text('changelog')->nullable();  // description of changes
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['print_template_id', 'version_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('template_versions');
    }
};
