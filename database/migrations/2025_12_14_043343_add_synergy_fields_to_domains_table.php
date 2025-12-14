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
            // Basic info
            $table->timestamp('created_at_synergy')->nullable()->after('expires_at')->comment('Domain creation date from Synergy Wholesale');

            // Status & renewal
            $table->string('domain_status')->nullable()->after('created_at_synergy')->comment('Domain status from Synergy Wholesale');
            $table->boolean('auto_renew')->nullable()->after('domain_status')->comment('Auto-renewal setting');

            // DNS & Nameservers
            $table->json('nameservers')->nullable()->after('auto_renew')->comment('Nameservers array');
            $table->string('dns_config_name')->nullable()->after('nameservers')->comment('DNS configuration name');

            // Australian domain specific
            $table->string('registrant_name')->nullable()->after('dns_config_name')->comment('Registrant name/company');
            $table->string('registrant_id_type')->nullable()->after('registrant_name')->comment('Registrant ID type (ACN, ABN, etc.)');
            $table->string('registrant_id')->nullable()->after('registrant_id_type')->comment('Registrant ID number');
            $table->string('eligibility_type')->nullable()->after('registrant_id')->comment('Eligibility type (Company, etc.)');
            $table->boolean('eligibility_valid')->nullable()->after('eligibility_type')->comment('Is eligibility valid?');
            $table->date('eligibility_last_check')->nullable()->after('eligibility_valid')->comment('Last eligibility check date');

            // Indexes
            $table->index('domain_status');
            $table->index('auto_renew');
            $table->index('eligibility_valid');
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
