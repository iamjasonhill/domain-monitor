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
        if (! Schema::hasTable('web_property_domains')) {
            Schema::create('web_property_domains', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('web_property_id');
                $table->uuid('domain_id');
                $table->string('usage_type', 30)->default('primary');
                $table->boolean('is_canonical')->default(false);
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->foreign('web_property_id')->references('id')->on('web_properties')->cascadeOnDelete();
                $table->foreign('domain_id')->references('id')->on('domains')->cascadeOnDelete();
                $table->unique(['web_property_id', 'domain_id']);
                $table->index(['usage_type', 'is_canonical']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('web_property_domains');
    }
};
