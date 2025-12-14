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
        Schema::create('subdomains', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('domain_id');
            $table->string('subdomain'); // e.g., 'www', 'api', 'blog'
            $table->string('full_domain'); // e.g., 'www.example.com'
            $table->string('ip_address')->nullable();
            $table->timestamp('ip_checked_at')->nullable();

            // IP-API.com data (cached)
            $table->string('ip_isp')->nullable();
            $table->string('ip_organization')->nullable();
            $table->string('ip_as_number')->nullable();
            $table->string('ip_country')->nullable();
            $table->string('ip_city')->nullable();
            $table->boolean('ip_hosting_flag')->nullable();

            // Hosting info
            $table->string('hosting_provider')->nullable();
            $table->text('hosting_admin_url')->nullable();

            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('domain_id')->references('id')->on('domains')->onDelete('cascade');
            $table->index(['domain_id', 'is_active']);
            $table->unique(['domain_id', 'subdomain']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subdomains');
    }
};
