<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('web_properties', function (Blueprint $table): void {
            $table->string('target_legacy_bookings_replacement_url', 2048)->nullable()->after('target_contact_us_page_url');
            $table->string('target_legacy_payments_replacement_url', 2048)->nullable()->after('target_legacy_bookings_replacement_url');
            $table->json('legacy_moveroo_endpoint_scan')->nullable()->after('conversion_links_scanned_at');
        });
    }

    public function down(): void
    {
        Schema::table('web_properties', function (Blueprint $table): void {
            $table->dropColumn([
                'target_legacy_bookings_replacement_url',
                'target_legacy_payments_replacement_url',
                'legacy_moveroo_endpoint_scan',
            ]);
        });
    }
};
