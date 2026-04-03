<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('web_properties', function (Blueprint $table): void {
            $table->string('target_moveroo_subdomain_url', 2048)->nullable()->after('target_vehicle_booking_url');
            $table->string('target_contact_us_page_url', 2048)->nullable()->after('target_moveroo_subdomain_url');
        });
    }

    public function down(): void
    {
        Schema::table('web_properties', function (Blueprint $table): void {
            $table->dropColumn([
                'target_moveroo_subdomain_url',
                'target_contact_us_page_url',
            ]);
        });
    }
};
