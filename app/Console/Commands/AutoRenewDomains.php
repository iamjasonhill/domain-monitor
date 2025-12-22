<?php

namespace App\Console\Commands;

use App\Models\Domain;
use App\Models\SynergyCredential;
use App\Services\SynergyWholesaleClient;
use Brain\Client\BrainEventClient;
use Illuminate\Console\Command;

class AutoRenewDomains extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'domains:auto-renew
                            {--dry-run : Show what would be renewed without actually renewing}
                            {--domain= : Renew a specific domain}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically renew domains with auto_renew=true that are expiring in 30 days';

    /**
     * Execute the console command.
     */
    public function handle(BrainEventClient $brain): int
    {
        $dryRun = $this->option('dry-run');
        $specificDomain = $this->option('domain');

        // Get Synergy credentials
        $credential = SynergyCredential::where('is_active', true)->first();

        if (! $credential) {
            $this->error('No active Synergy Wholesale credentials found.');
            $this->info('Please create credentials first using the SynergyCredential model.');

            return Command::FAILURE;
        }

        $client = SynergyWholesaleClient::fromEncryptedCredentials(
            $credential->reseller_id,
            $credential->api_key_encrypted,
            $credential->api_url
        );

        // Check account balance
        $balanceResult = $client->getBalance();
        if (! $balanceResult || $balanceResult['status'] !== 'OK' || $balanceResult['balance'] === null) {
            $this->error('Failed to retrieve account balance. Cannot proceed with auto-renewal.');
            $this->info('Error: '.($balanceResult['error_message'] ?? 'Unknown error'));

            return Command::FAILURE;
        }

        $balance = $balanceResult['balance'];
        $this->info("Account balance: \${$balance}");
        $this->newLine();

        if ($balance < 10) {
            $this->warn('Account balance is low. Renewals may fail if insufficient funds.');
            $this->newLine();
        }

        // Find domains to renew
        $now = now();
        $targetDate = $now->copy()->addDays(30);

        if ($specificDomain) {
            $domains = Domain::where('domain', $specificDomain)->get();
            if ($domains->isEmpty()) {
                $this->error("Domain '{$specificDomain}' not found.");

                return Command::FAILURE;
            }
        } else {
            // Find domains expiring in 30 days (±1 day window)
            $domains = Domain::where('is_active', true)
                ->where('auto_renew', true)
                ->whereNotNull('expires_at')
                ->whereBetween('expires_at', [
                    $targetDate->copy()->subDay()->startOfDay(),
                    $targetDate->copy()->addDay()->endOfDay(),
                ])
                ->get()
                ->filter(function ($domain) {
                    // Only process Australian TLDs
                    return SynergyWholesaleClient::isAustralianTld($domain->domain);
                });
        }

        if ($domains->isEmpty()) {
            $this->info('No domains found that need renewal.');

            return Command::SUCCESS;
        }

        $this->info("Found {$domains->count()} domain(s) to renew:");
        $this->newLine();

        $renewedCount = 0;
        $failedCount = 0;

        foreach ($domains as $domain) {
            // Skip if not Australian TLD
            if (! SynergyWholesaleClient::isAustralianTld($domain->domain)) {
                $this->line("  {$domain->domain} - Skipped (not an Australian TLD)");

                continue;
            }

            // Skip if auto_renew is not enabled
            if (! $domain->auto_renew) {
                $this->line("  {$domain->domain} - Skipped (auto_renew not enabled)");

                continue;
            }

            // Skip if already renewed (expiry is more than 30 days away)
            $daysUntilExpiry = $now->diffInDays($domain->expires_at, false);
            if ($daysUntilExpiry > 31) {
                $this->line("  {$domain->domain} - Skipped (already renewed, expires in {$daysUntilExpiry} days)");

                continue;
            }

            $this->line("  {$domain->domain} - Expires: {$domain->expires_at->format('Y-m-d')} ({$daysUntilExpiry} days)");

            if ($dryRun) {
                $this->line('    [DRY RUN] Would renew for 1 year');
                $renewedCount++;

                continue;
            }

            // Attempt renewal
            $result = $client->renewDomain($domain->domain, 1);

            if ($result && $result['status'] === 'OK') {
                // Update expiry date (add 1 year)
                $newExpiryDate = $domain->expires_at->copy()->addYear();
                $domain->expires_at = $newExpiryDate;
                $domain->renewed_at = now();
                $domain->renewed_by = 'auto-renew';
                $domain->save();

                // Send Brain event
                $this->sendRenewalEvent($brain, $domain, true, null);

                $this->info("    ✓ Renewed successfully. New expiry: {$newExpiryDate->format('Y-m-d')}");
                $renewedCount++;
            } else {
                $errorMessage = $result['error_message'] ?? 'Unknown error';
                $this->error("    ✗ Renewal failed: {$errorMessage}");

                // Send Brain event for failure
                $this->sendRenewalEvent($brain, $domain, false, $errorMessage);
                $failedCount++;
            }
        }

        $this->newLine();

        if ($dryRun) {
            $this->info("Dry run complete. Would renew {$renewedCount} domain(s).");
        } else {
            if ($renewedCount > 0) {
                $this->info("Successfully renewed {$renewedCount} domain(s).");
            }
            if ($failedCount > 0) {
                $this->warn("Failed to renew {$failedCount} domain(s).");
            }
        }

        return Command::SUCCESS;
    }

    /**
     * Send renewal event to Brain
     */
    private function sendRenewalEvent(BrainEventClient $brain, Domain $domain, bool $success, ?string $errorMessage): void
    {
        $eventType = $success ? 'domain.renewed' : 'domain.renewal_failed';
        $fingerprint = "domain.renewal:{$domain->domain}";

        $message = $success
            ? "Domain {$domain->domain} was automatically renewed for 1 year"
            : "Domain {$domain->domain} auto-renewal failed: {$errorMessage}";

        $payload = [
            'domain' => $domain->domain,
            'domain_id' => $domain->id,
            'expires_at' => $domain->expires_at->toIso8601String(),
            'auto_renew' => $domain->auto_renew,
            'registrar' => $domain->registrar,
        ];

        if (! $success) {
            $payload['error_message'] = $errorMessage;
        }

        // Send event asynchronously with all metadata in payload
        $payload['severity'] = $success ? 'info' : 'error';
        $payload['fingerprint'] = $fingerprint;
        $payload['message'] = $message;
        $payload['context'] = [
            'domain' => $domain->domain,
            'domain_id' => $domain->id,
            'success' => $success,
        ];

        $brain->sendAsync($eventType, $payload);
    }
}
