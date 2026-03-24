<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('domains')) {
            return;
        }

        Schema::table('domains', function (Blueprint $table) {
            if (! Schema::hasColumn('domains', 'hosting_detection_confidence')) {
                $table->string('hosting_detection_confidence')->nullable()->after('hosting_admin_url');
            }

            if (! Schema::hasColumn('domains', 'hosting_detection_source')) {
                $table->string('hosting_detection_source')->nullable()->after('hosting_detection_confidence');
            }

            if (! Schema::hasColumn('domains', 'hosting_detected_at')) {
                $table->timestamp('hosting_detected_at')->nullable()->after('hosting_detection_source');
            }

            if (! Schema::hasColumn('domains', 'hosting_review_status')) {
                $table->string('hosting_review_status')->nullable()->after('hosting_detected_at');
            }

            if (! Schema::hasColumn('domains', 'hosting_reviewed_at')) {
                $table->timestamp('hosting_reviewed_at')->nullable()->after('hosting_review_status');
            }
        });

        DB::table('domains')
            ->whereNotNull('hosting_provider')
            ->where('hosting_provider', '!=', '')
            ->whereNull('hosting_review_status')
            ->update([
                'hosting_detection_source' => DB::raw("COALESCE(hosting_detection_source, 'legacy')"),
                'hosting_review_status' => 'pending',
                'hosting_detected_at' => DB::raw('COALESCE(hosting_detected_at, CURRENT_TIMESTAMP)'),
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('domains')) {
            return;
        }

        Schema::table('domains', function (Blueprint $table) {
            $columns = [
                'hosting_detection_confidence',
                'hosting_detection_source',
                'hosting_detected_at',
                'hosting_review_status',
                'hosting_reviewed_at',
            ];

            $existing = array_values(array_filter($columns, fn (string $column) => Schema::hasColumn('domains', $column)));

            if ($existing !== []) {
                $table->dropColumn($existing);
            }
        });
    }
};
