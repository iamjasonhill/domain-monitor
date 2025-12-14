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
        Schema::table('domains', function (Blueprint $table) {
            // Store detailed nameserver information (hostname, IP, subdomain, etc.)
            $table->json('nameserver_details')->nullable()->after('nameservers')->comment('Detailed nameserver information with IP addresses and subdomains');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->dropColumn('nameserver_details');
        });
    }
};
