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
            // Transfer lock status (add near auto_renew)
            if (! Schema::hasColumn('domains', 'transfer_lock')) {
                $table->boolean('transfer_lock')->nullable()->after('auto_renew')->comment('Domain transfer lock status');
            }

            // Renewal status (add after transfer_lock)
            if (! Schema::hasColumn('domains', 'renewal_required')) {
                $table->boolean('renewal_required')->nullable()->after('transfer_lock')->comment('Whether domain renewal is required');
            }
            if (! Schema::hasColumn('domains', 'can_renew')) {
                $table->boolean('can_renew')->nullable()->after('renewal_required')->comment('Whether domain can be renewed');
            }

            // DNS configuration (add after dns_config_name)
            if (! Schema::hasColumn('domains', 'dns_config_id')) {
                $table->unsignedInteger('dns_config_id')->nullable()->after('dns_config_name')->comment('DNS configuration ID');
            }

            // Australian domain compliance fields (add after eligibility_last_check)
            if (! Schema::hasColumn('domains', 'au_policy_id')) {
                $table->string('au_policy_id')->nullable()->after('eligibility_last_check')->comment('Policy ID for .au domains');
            }
            if (! Schema::hasColumn('domains', 'au_policy_desc')) {
                $table->text('au_policy_desc')->nullable()->after('au_policy_id')->comment('Policy description for .au domains');
            }
            if (! Schema::hasColumn('domains', 'au_compliance_reason')) {
                $table->text('au_compliance_reason')->nullable()->after('au_policy_desc')->comment('Compliance reason if non-compliant');
            }
            if (! Schema::hasColumn('domains', 'au_association_id')) {
                $table->string('au_association_id')->nullable()->after('au_compliance_reason')->comment('Association ID for .au domains');
            }

            // Registry and domain identifiers (add after au_association_id)
            if (! Schema::hasColumn('domains', 'domain_roid')) {
                $table->string('domain_roid')->nullable()->after('au_association_id')->comment('Registry Object ID (unique identifier)');
            }
            if (! Schema::hasColumn('domains', 'registry_id')) {
                $table->string('registry_id')->nullable()->after('domain_roid')->comment('Registry identifier');
            }

            // ID protection (add after registry_id)
            if (! Schema::hasColumn('domains', 'id_protect')) {
                $table->string('id_protect')->nullable()->after('registry_id')->comment('ID protection status');
            }

            // Domain categories (add after id_protect)
            if (! Schema::hasColumn('domains', 'categories')) {
                $table->json('categories')->nullable()->after('id_protect')->comment('Domain categories array');
            }
        });

        // Add indexes
        Schema::table('domains', function (Blueprint $table) {
            $indexes = [
                'domains_domain_roid_index' => 'domain_roid',
                'domains_transfer_lock_index' => 'transfer_lock',
                'domains_renewal_required_index' => 'renewal_required',
                'domains_can_renew_index' => 'can_renew',
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

            // Add unique index for domain_roid if it doesn't exist
            if (Schema::hasColumn('domains', 'domain_roid')) {
                try {
                    $table->unique('domain_roid', 'domains_domain_roid_unique');
                } catch (\Exception $e) {
                    // Unique index might already exist, continue
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
            // Drop indexes
            $indexes = [
                'domains_domain_roid_index',
                'domains_domain_roid_unique',
                'domains_transfer_lock_index',
                'domains_renewal_required_index',
                'domains_can_renew_index',
            ];

            foreach ($indexes as $index) {
                try {
                    $table->dropIndex($index);
                } catch (\Exception $e) {
                    // Index doesn't exist, continue
                }
            }

            // Drop columns
            $columns = [
                'domain_roid',
                'registry_id',
                'dns_config_id',
                'au_policy_id',
                'au_policy_desc',
                'au_compliance_reason',
                'au_association_id',
                'id_protect',
                'categories',
                'transfer_lock',
                'renewal_required',
                'can_renew',
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
