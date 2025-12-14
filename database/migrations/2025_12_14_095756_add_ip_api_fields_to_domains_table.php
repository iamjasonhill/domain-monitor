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
        if (Schema::hasTable('domains')) {
            Schema::table('domains', function (Blueprint $table) {
                // IP address information
                $table->string('ip_address')->nullable()->after('hosting_admin_url');
                $table->timestamp('ip_checked_at')->nullable()->after('ip_address');

                // IP-API.com data (cached)
                $table->string('ip_isp')->nullable()->after('ip_checked_at');
                $table->string('ip_organization')->nullable()->after('ip_isp');
                $table->string('ip_as_number')->nullable()->after('ip_organization');
                $table->string('ip_country')->nullable()->after('ip_as_number');
                $table->string('ip_city')->nullable()->after('ip_country');
                $table->boolean('ip_hosting_flag')->nullable()->after('ip_city');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->dropColumn([
                'ip_address',
                'ip_checked_at',
                'ip_isp',
                'ip_organization',
                'ip_as_number',
                'ip_country',
                'ip_city',
                'ip_hosting_flag',
            ]);
        });
    }
};
