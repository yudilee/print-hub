<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('print_template_data_schema', function (Blueprint $table) {
            $table->foreignId('print_template_id')->constrained()->cascadeOnDelete();
            $table->foreignId('data_schema_id')->constrained()->cascadeOnDelete();
            $table->string('alias', 100)->nullable()->comment('Contextual alias, e.g. "CRM", "Accounting"');
            $table->timestamps();

            $table->unique(['print_template_id', 'data_schema_id'], 'pk_template_schema');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('print_template_data_schema');
    }
};
