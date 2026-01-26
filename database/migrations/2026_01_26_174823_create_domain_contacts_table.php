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
        Schema::create('domain_contacts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('domain_id');
            $table->string('contact_type'); // 'registrant', 'admin', 'tech', 'billing'
            $table->string('name')->nullable();
            $table->text('email_encrypted')->nullable(); // Encrypted email
            $table->text('phone_encrypted')->nullable(); // Encrypted phone
            $table->string('organization')->nullable();
            $table->text('address_encrypted')->nullable(); // Encrypted address
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country')->nullable();
            $table->timestamp('synced_at');
            $table->json('raw_data')->nullable(); // Store full API response for audit
            $table->timestamps();

            // Foreign key with cascade delete
            $table->foreign('domain_id')->references('id')->on('domains')->onDelete('cascade');

            // Indexes
            $table->index(['domain_id', 'contact_type']);
            $table->index(['domain_id', 'synced_at']);
            $table->unique(['domain_id', 'contact_type', 'synced_at'], 'domain_contact_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('domain_contacts');
    }
};
