<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('web_properties', function (Blueprint $table): void {
            $table->string('site_key', 100)->nullable()->unique()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('web_properties', function (Blueprint $table): void {
            $table->dropUnique(['site_key']);
            $table->dropColumn('site_key');
        });
    }
};
