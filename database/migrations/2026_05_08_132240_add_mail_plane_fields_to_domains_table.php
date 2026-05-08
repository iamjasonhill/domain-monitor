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
            $table->string('mail_plane_type')->nullable()->after('email_usage')->index();
            $table->string('mail_provider')->nullable()->after('mail_plane_type')->index();
            $table->jsonb('mail_dns_requirements')->nullable()->after('mail_provider');
            $table->jsonb('mail_provider_verification')->nullable()->after('mail_dns_requirements');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->dropColumn([
                'mail_plane_type',
                'mail_provider',
                'mail_dns_requirements',
                'mail_provider_verification',
            ]);
        });
    }
};
