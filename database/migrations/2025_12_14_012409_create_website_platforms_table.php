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
        if (! Schema::hasTable('website_platforms')) {
            Schema::create('website_platforms', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('domain_id')->unique();
                $table->string('platform_type', 50)->nullable(); // WordPress, Laravel, Next.js, Shopify, Static, Other
                $table->string('platform_version', 50)->nullable();
                $table->text('admin_url')->nullable();
                $table->string('detection_confidence', 20)->nullable(); // high, medium, low
                $table->timestamp('last_detected')->nullable();
                $table->timestamps();

                // Foreign key with cascade delete
                $table->foreign('domain_id')->references('id')->on('domains')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('website_platforms');
    }
};
