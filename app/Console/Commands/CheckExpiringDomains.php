<?php

namespace App\Console\Commands;

use App\Models\Domain;
use App\Models\DomainAlert;
use Brain\Client\BrainEventClient;
use Illuminate\Console\Command;

class CheckExpiringDomains extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'domains:check-expiring
                            {--days= : Check domains expiring within this many days (default: checks 30, 14, 7)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for expiring domains and domains requiring renewal, send Brain events at 30, 14, and 7 days before expiry';

    /**
     * Execute the console command.
     */
    public function handle(BrainEventClient $brain): int
    {
        $thresholds = [30, 14, 7];
        $now = now();
        $totalAlerts = 0;

        $this->info('Checking for expiring domains...');
        $this->newLine();

        // Check for domains with renewal_required = true
        $this->checkRenewalRequiredDomains($brain, $now, $totalAlerts);
        $this->newLine();

        foreach ($thresholds as $days) {
            // Calculate the target expiry date (X days from now)
            $targetDate = $now->copy()->addDays($days);

            // Find domains expiring on or around this threshold date (within ±1 day to account for timezone differences)
            $domains = Domain::where('is_active', true)
                ->whereNotNull('expires_at')
                ->whereBetween('expires_at', [
                    $targetDate->copy()->subDay()->startOfDay(),
                    $targetDate->copy()->addDay()->endOfDay(),
                ])
                ->get();

            if ($domains->isEmpty()) {
                $this->line("  No domains expiring in {$days} days.");

                continue;
            }

            $this->info("Found {$domains->count()} domain(s) expiring in {$days} days:");

            foreach ($domains as $domain) {
                // Check if we've already sent an alert for this threshold
                $existingAlert = DomainAlert::where('domain_id', $domain->id)
                    ->where('alert_type', 'domain_expiring')
                    ->where('severity', $this->getSeverityForDays($days))
                    ->whereNull('resolved_at')
                    ->whereDate('triggered_at', $now->toDateString())
                    ->first();

                if ($existingAlert) {
                    $this->line("    {$domain->domain} - Alert already sent today");

                    continue;
                }

                // Calculate exact days until expiry
                $daysUntilExpiry = (int) $now->diffInDays($domain->expires_at, false);

                // Send Brain event
                $this->sendExpiryEvent($brain, $domain, $daysUntilExpiry, $days);

                // Create alert record
                DomainAlert::create([
                    'domain_id' => $domain->id,
                    'alert_type' => 'domain_expiring',
                    'severity' => $this->getSeverityForDays($days),
                    'triggered_at' => $now,
                    'payload' => [
                        'days_until_expiry' => $daysUntilExpiry,
                        'expires_at' => $domain->expires_at->toIso8601String(),
                        'threshold' => $days,
                    ],
                ]);

                $this->line("    ✓ {$domain->domain} - {$daysUntilExpiry} days until expiry (alert sent)");
                $totalAlerts++;
            }

            $this->newLine();
        }

        if ($totalAlerts > 0) {
            $this->info("Sent {$totalAlerts} alert(s) to Brain.");
        } else {
            $this->info('No new alerts to send.');
        }

        return Command::SUCCESS;
    }

    /**
     * Send expiry event to Brain
     */
    private function sendExpiryEvent(BrainEventClient $brain, Domain $domain, int $daysUntilExpiry, int $threshold): void
    {
        $severity = $this->getSeverityForDays($threshold);
        $eventType = 'domain.expiring';

        $fingerprint = "domain.expiring:{$domain->domain}:{$threshold}";

        $message = sprintf(
            'Domain %s expires in %d day(s)',
            $domain->domain,
            $daysUntilExpiry
        );

        $context = [
            'domain' => $domain->domain,
            'domain_id' => $domain->id,
            'days_until_expiry' => $daysUntilExpiry,
            'expires_at' => $domain->expires_at->toIso8601String(),
            'threshold' => $threshold,
        ];

        $payload = [
            'domain' => $domain->domain,
            'domain_id' => $domain->id,
            'days_until_expiry' => $daysUntilExpiry,
            'expires_at' => $domain->expires_at->toIso8601String(),
            'threshold' => $threshold,
            'auto_renew' => $domain->auto_renew ?? false,
            'registrar' => $domain->registrar,
        ];

        // Send event asynchronously with all metadata in payload
        $payload['severity'] = $severity;
        $payload['fingerprint'] = $fingerprint;
        $payload['message'] = $message;
        $payload['context'] = $context;

        $brain->sendAsync($eventType, $payload);
    }

    /**
     * Get severity level based on days until expiry
     */
    private function getSeverityForDays(int $days): string
    {
        return match ($days) {
            30 => 'warning',
            14 => 'warning',
            7 => 'error',
            default => 'info',
        };
    }

    /**
     * Check for domains with renewal_required = true
     */
    private function checkRenewalRequiredDomains(BrainEventClient $brain, \Illuminate\Support\Carbon $now, int &$totalAlerts): void
    {
        $domains = Domain::where('is_active', true)
            ->where('renewal_required', true)
            ->get();

        if ($domains->isEmpty()) {
            $this->line('  No domains requiring renewal.');

            return;
        }

        $this->info("Found {$domains->count()} domain(s) requiring renewal:");

        foreach ($domains as $domain) {
            // Check if we've already sent an alert for this domain today
            $existingAlert = DomainAlert::where('domain_id', $domain->id)
                ->where('alert_type', 'renewal_required')
                ->whereNull('resolved_at')
                ->whereDate('triggered_at', $now->toDateString())
                ->first();

            if ($existingAlert) {
                $this->line("    {$domain->domain} - Alert already sent today");

                continue;
            }

            // Calculate days until expiry if available
            $daysUntilExpiry = null;
            if ($domain->expires_at) {
                $daysUntilExpiry = (int) $now->diffInDays($domain->expires_at, false);
            }

            // Send Brain event
            $this->sendRenewalRequiredEvent($brain, $domain, $daysUntilExpiry);

            // Create alert record
            DomainAlert::create([
                'domain_id' => $domain->id,
                'alert_type' => 'renewal_required',
                'severity' => $domain->can_renew ? 'warning' : 'error',
                'triggered_at' => $now,
                'payload' => [
                    'renewal_required' => true,
                    'can_renew' => $domain->can_renew ?? false,
                    'days_until_expiry' => $daysUntilExpiry,
                    'expires_at' => $domain->expires_at?->toIso8601String(),
                    'auto_renew' => $domain->auto_renew ?? false,
                ],
            ]);

            $status = $domain->can_renew ? 'can be renewed' : 'cannot be renewed';
            $this->line("    ✓ {$domain->domain} - Renewal required ({$status})");
            $totalAlerts++;
        }
    }

    /**
     * Send renewal required event to Brain
     */
    private function sendRenewalRequiredEvent(BrainEventClient $brain, Domain $domain, ?int $daysUntilExpiry): void
    {
        $severity = $domain->can_renew ? 'warning' : 'error';
        $eventType = 'domain.renewal.required';

        $fingerprint = "domain.renewal.required:{$domain->domain}";

        $message = sprintf(
            'Domain %s requires renewal%s',
            $domain->domain,
            $daysUntilExpiry !== null ? " (expires in {$daysUntilExpiry} days)" : ''
        );

        $payload = [
            'domain' => $domain->domain,
            'domain_id' => $domain->id,
            'renewal_required' => true,
            'can_renew' => $domain->can_renew ?? false,
            'days_until_expiry' => $daysUntilExpiry,
            'expires_at' => $domain->expires_at?->toIso8601String(),
            'auto_renew' => $domain->auto_renew ?? false,
            'registrar' => $domain->registrar,
            'severity' => $severity,
            'fingerprint' => $fingerprint,
            'message' => $message,
        ];

        $brain->sendAsync($eventType, $payload);
    }
}
