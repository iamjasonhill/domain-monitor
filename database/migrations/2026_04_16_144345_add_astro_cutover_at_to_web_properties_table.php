<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('web_properties', function (Blueprint $table) {
            $table->timestamp('astro_cutover_at')->nullable()->after('target_platform')->index();
        });
    }

    public function down(): void
    {
        Schema::table('web_properties', function (Blueprint $table) {
            $table->dropColumn('astro_cutover_at');
        });
    }
};
