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
            $table->timestamp('renewed_at')->nullable()->after('expires_at');
            $table->string('renewed_by')->nullable()->after('renewed_at');
            $table->index('renewed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->dropIndex(['renewed_at']);
            $table->dropColumn(['renewed_at', 'renewed_by']);
        });
    }
};
