<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('search_console_issue_snapshots', function (Blueprint $table) {
            $table->index(
                [
                    'web_property_id',
                    'issue_class',
                    'capture_method',
                    'captured_at',
                    'created_at',
                    'updated_at',
                ],
                'sc_issue_snapshot_latest_bucket_lookup_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('search_console_issue_snapshots', function (Blueprint $table) {
            $table->dropIndex('sc_issue_snapshot_latest_bucket_lookup_idx');
        });
    }
};
