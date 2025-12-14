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
        Schema::create('dns_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('domain_id');
            $table->string('host')->index();
            $table->string('type', 10)->index(); // A, AAAA, CNAME, MX, NS, SOA, TXT, etc.
            $table->text('value');
            $table->integer('ttl')->nullable();
            $table->integer('priority')->nullable(); // For MX records
            $table->string('record_id')->nullable()->comment('API record ID from Synergy Wholesale');
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->foreign('domain_id')->references('id')->on('domains')->onDelete('cascade');
            $table->index(['domain_id', 'type']);
            $table->index(['domain_id', 'host']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dns_records');
    }
};
