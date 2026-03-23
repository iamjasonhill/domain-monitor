<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('web_properties')) {
            Schema::create('web_properties', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('slug')->unique();
                $table->string('name');
                $table->string('property_type', 50);
                $table->string('status', 20)->default('active')->index();
                $table->uuid('primary_domain_id')->nullable();
                $table->string('production_url')->nullable();
                $table->string('staging_url')->nullable();
                $table->string('platform')->nullable();
                $table->string('target_platform')->nullable();
                $table->string('owner')->nullable();
                $table->unsignedTinyInteger('priority')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->foreign('primary_domain_id')->references('id')->on('domains')->nullOnDelete();
                $table->index(['property_type', 'status']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('web_properties');
    }
};
