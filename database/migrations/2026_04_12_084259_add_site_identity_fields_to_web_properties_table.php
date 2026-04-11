<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('web_properties', function (Blueprint $table) {
            $table->string('site_identity_site_name')->nullable()->after('name');
            $table->string('site_identity_legal_name')->nullable()->after('site_identity_site_name');
        });
    }

    public function down(): void
    {
        Schema::table('web_properties', function (Blueprint $table) {
            $table->dropColumn([
                'site_identity_site_name',
                'site_identity_legal_name',
            ]);
        });
    }
};
