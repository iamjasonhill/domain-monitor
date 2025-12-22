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
            // Target platform for migration (e.g., 'Astro', 'Next.js')
            if (! Schema::hasColumn('domains', 'target_platform')) {
                $table->string('target_platform', 100)->nullable()->after('platform')
                    ->comment('Target platform for site migration');
            }

            // Migration tier (priority: 1=high, 2=medium, 3=low)
            if (! Schema::hasColumn('domains', 'migration_tier')) {
                $table->unsignedTinyInteger('migration_tier')->nullable()->after('target_platform')
                    ->comment('Migration priority tier (1=high, 2=medium, 3=low)');
            }

            // Scaffolding status tracking
            if (! Schema::hasColumn('domains', 'scaffolding_status')) {
                $table->string('scaffolding_status', 20)->nullable()->after('migration_tier')
                    ->comment('Scaffolding status: pending, in_progress, complete, failed');
            }

            if (! Schema::hasColumn('domains', 'scaffolded_at')) {
                $table->timestamp('scaffolded_at')->nullable()->after('scaffolding_status')
                    ->comment('When scaffolding was completed');
            }

            if (! Schema::hasColumn('domains', 'scaffolded_by')) {
                $table->string('scaffolded_by', 100)->nullable()->after('scaffolded_at')
                    ->comment('Tool or user that performed scaffolding');
            }
        });

        // Add index for filtering by scaffolding status
        Schema::table('domains', function (Blueprint $table) {
            if (Schema::hasColumn('domains', 'scaffolding_status')) {
                try {
                    $table->index('scaffolding_status');
                } catch (\Exception $e) {
                    // Index may already exist
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $columns = [
                'target_platform',
                'migration_tier',
                'scaffolding_status',
                'scaffolded_at',
                'scaffolded_by',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('domains', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
