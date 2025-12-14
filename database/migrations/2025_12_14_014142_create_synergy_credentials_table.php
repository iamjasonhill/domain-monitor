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
        if (! Schema::hasTable('synergy_credentials')) {
            Schema::create('synergy_credentials', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('reseller_id');
                $table->text('api_key_encrypted'); // Encrypted API key
                $table->string('api_url')->nullable(); // Optional custom API URL
                $table->boolean('is_active')->default(true);
                $table->timestamp('last_sync_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('synergy_credentials');
    }
};
