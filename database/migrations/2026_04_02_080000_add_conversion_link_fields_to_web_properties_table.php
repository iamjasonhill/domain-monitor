<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('web_properties', function (Blueprint $table): void {
            $table->string('current_household_quote_url')->nullable()->after('notes');
            $table->string('current_household_booking_url')->nullable()->after('current_household_quote_url');
            $table->string('current_vehicle_quote_url')->nullable()->after('current_household_booking_url');
            $table->string('current_vehicle_booking_url')->nullable()->after('current_vehicle_quote_url');
            $table->string('target_household_quote_url')->nullable()->after('current_vehicle_booking_url');
            $table->string('target_household_booking_url')->nullable()->after('target_household_quote_url');
            $table->string('target_vehicle_quote_url')->nullable()->after('target_household_booking_url');
            $table->string('target_vehicle_booking_url')->nullable()->after('target_vehicle_quote_url');
            $table->timestamp('conversion_links_scanned_at')->nullable()->after('target_vehicle_booking_url');
        });
    }

    public function down(): void
    {
        Schema::table('web_properties', function (Blueprint $table): void {
            $table->dropColumn([
                'current_household_quote_url',
                'current_household_booking_url',
                'current_vehicle_quote_url',
                'current_vehicle_booking_url',
                'target_household_quote_url',
                'target_household_booking_url',
                'target_vehicle_quote_url',
                'target_vehicle_booking_url',
                'conversion_links_scanned_at',
            ]);
        });
    }
};
