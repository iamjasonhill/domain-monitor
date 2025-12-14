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
        if (! Schema::hasTable('domains')) {
            return;
        }

        Schema::table('domains', function (Blueprint $table) {
            // Basic info
            if (! Schema::hasColumn('domains', 'created_at_synergy')) {
                $table->timestamp('created_at_synergy')->nullable()->after('expires_at')->comment('Domain creation date from Synergy Wholesale');
            }

            // Status & renewal
            if (! Schema::hasColumn('domains', 'domain_status')) {
                $table->string('domain_status')->nullable()->after('created_at_synergy')->comment('Domain status from Synergy Wholesale');
            }
            if (! Schema::hasColumn('domains', 'auto_renew')) {
                $table->boolean('auto_renew')->nullable()->after('domain_status')->comment('Auto-renewal setting');
            }

            // DNS & Nameservers
            if (! Schema::hasColumn('domains', 'nameservers')) {
                $table->json('nameservers')->nullable()->after('auto_renew')->comment('Nameservers array');
            }
            if (! Schema::hasColumn('domains', 'dns_config_name')) {
                $table->string('dns_config_name')->nullable()->after('nameservers')->comment('DNS configuration name');
            }

            // Australian domain specific
            if (! Schema::hasColumn('domains', 'registrant_name')) {
                $table->string('registrant_name')->nullable()->after('dns_config_name')->comment('Registrant name/company');
            }
            if (! Schema::hasColumn('domains', 'registrant_id_type')) {
                $table->string('registrant_id_type')->nullable()->after('registrant_name')->comment('Registrant ID type (ACN, ABN, etc.)');
            }
            if (! Schema::hasColumn('domains', 'registrant_id')) {
                $table->string('registrant_id')->nullable()->after('registrant_id_type')->comment('Registrant ID number');
            }
            if (! Schema::hasColumn('domains', 'eligibility_type')) {
                $table->string('eligibility_type')->nullable()->after('registrant_id')->comment('Eligibility type (Company, etc.)');
            }
            if (! Schema::hasColumn('domains', 'eligibility_valid')) {
                $table->boolean('eligibility_valid')->nullable()->after('eligibility_type')->comment('Is eligibility valid?');
            }
            if (! Schema::hasColumn('domains', 'eligibility_last_check')) {
                $table->date('eligibility_last_check')->nullable()->after('eligibility_valid')->comment('Last eligibility check date');
            }
        });

        // Add indexes only if they don't exist
        Schema::table('domains', function (Blueprint $table) {
            $indexes = [
                'domains_domain_status_index' => 'domain_status',
                'domains_auto_renew_index' => 'auto_renew',
                'domains_eligibility_valid_index' => 'eligibility_valid',
            ];

            foreach ($indexes as $indexName => $column) {
                if (Schema::hasColumn('domains', $column)) {
                    try {
                        $table->index($column);
                    } catch (\Exception $e) {
                        // Index might already exist, continue
                    }
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
            // Drop indexes if they exist
            $indexes = ['domains_domain_status_index', 'domains_auto_renew_index', 'domains_eligibility_valid_index'];
            foreach ($indexes as $index) {
                try {
                    $table->dropIndex($index);
                } catch (\Exception $e) {
                    // Index doesn't exist, continue
                }
            }

            // Drop columns
            $columns = [
                'created_at_synergy',
                'domain_status',
                'auto_renew',
                'nameservers',
                'dns_config_name',
                'registrant_name',
                'registrant_id_type',
                'registrant_id',
                'eligibility_type',
                'eligibility_valid',
                'eligibility_last_check',
            ];

            foreach ($columns as $column) {
                try {
                    $table->dropColumn($column);
                } catch (\Exception $e) {
                    // Column doesn't exist, continue
                }
            }
        });
    }
};
