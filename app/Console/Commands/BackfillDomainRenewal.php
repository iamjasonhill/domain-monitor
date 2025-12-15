<?php

namespace App\Console\Commands;

use App\Models\Domain;
use Illuminate\Console\Command;

class BackfillDomainRenewal extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'domains:backfill-renewal
                            {domain : The domain name to backfill renewal info for}
                            {--date= : The renewal date (Y-m-d H:i:s format, defaults to now)}
                            {--method=manual : The renewal method (auto-renew or manual)}
                            {--create : Create the domain if it does not exist}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill renewal information for a domain';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $domainName = $this->argument('domain');
        $renewalDate = $this->option('date') ? now()->parse($this->option('date')) : now();
        $renewalMethod = $this->option('method');

        $domain = Domain::where('domain', $domainName)->first();

        if (! $domain) {
            $this->error("Domain '{$domainName}' not found in database.");
            $this->info('Please import the domain first using: php artisan domains:import-synergy');

            return Command::FAILURE;
        }

        $this->info("Found domain: {$domain->domain}");
        $this->info('Current expiry: '.($domain->expires_at ? $domain->expires_at->format('Y-m-d H:i:s') : 'Not set'));
        $this->info('Current renewed_at: '.($domain->renewed_at ? $domain->renewed_at->format('Y-m-d H:i:s') : 'Not set'));
        $this->info('Current renewed_by: '.($domain->renewed_by ?? 'Not set'));
        $this->newLine();

        $domain->renewed_at = $renewalDate;
        $domain->renewed_by = $renewalMethod;
        $domain->save();

        $this->info('✓ Successfully updated renewal information:');
        $this->info("  Renewed at: {$renewalDate->format('Y-m-d H:i:s')}");
        $this->info("  Renewed by: {$renewalMethod}");

        return Command::SUCCESS;
    }
}
