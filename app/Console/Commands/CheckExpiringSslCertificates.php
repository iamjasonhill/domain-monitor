<?php

namespace App\Console\Commands;

use App\Models\Domain;
use App\Models\DomainAlert;
use App\Models\DomainCheck;
use Brain\Client\BrainEventClient;
use Illuminate\Console\Command;

class CheckExpiringSslCertificates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'domains:check-expiring-ssl';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for expiring SSL certificates and send Brain events at 30, 14, 7, 3 days before expiry';

    /**
     * Execute the console command.
     */
    public function handle(BrainEventClient $brain): int
    {
        $thresholds = [30, 14, 7, 3];
        $now = now();
        $totalAlerts = 0;

        $this->info('Checking for expiring SSL certificates...');
        $this->newLine();

        foreach ($thresholds as $days) {
            $targetDate = $now->copy()->addDays($days);

            // Find domains with SSL checks expiring around this threshold
            $sslChecks = DomainCheck::where('check_type', 'ssl')
                ->where('status', 'ok')
                ->whereNotNull('payload->expires_at')
                ->whereHas('domain', function ($query) {
                    $query->where('is_active', true)
                        ->where(function ($q) {
                            $q->whereNull('parked_override')
                                ->orWhere('parked_override', false);
                        });
                })
                ->get()
                ->filter(function ($check) use ($targetDate) {
                    $expiresAt = $check->payload['expires_at'] ?? null;
                    if (! $expiresAt) {
                        return false;
                    }
                    $expiry = \Carbon\Carbon::parse($expiresAt);
                    $diff = now()->diffInDays($expiry, false);

                    return $diff >= ($targetDate->diffInDays(now()) - 1) && $diff <= ($targetDate->diffInDays(now()) + 1);
                })
                ->unique('domain_id');

            if ($sslChecks->isEmpty()) {
                $this->line("  No SSL certificates expiring in {$days} days.");

                continue;
            }

            $this->info("Found {$sslChecks->count()} SSL certificate(s) expiring in ~{$days} days:");

            foreach ($sslChecks as $check) {
                $domain = $check->domain;
                $expiresAt = \Carbon\Carbon::parse($check->payload['expires_at']);
                $daysUntilExpiry = (int) $now->diffInDays($expiresAt, false);

                // Check if we've already sent an alert for this threshold
                $existingAlert = DomainAlert::where('domain_id', $domain->id)
                    ->where('alert_type', 'ssl_expiring')
                    ->where('severity', $this->getSeverityForDays($days))
                    ->whereNull('resolved_at')
                    ->whereDate('triggered_at', $now->toDateString())
                    ->first();

                if ($existingAlert) {
                    $this->line("    {$domain->domain} - Alert already sent today");

                    continue;
                }

                // Send Brain event
                $this->sendSslExpiryEvent($brain, $domain, $daysUntilExpiry, $days, $expiresAt);

                // Create alert record
                DomainAlert::create([
                    'domain_id' => $domain->id,
                    'alert_type' => 'ssl_expiring',
                    'severity' => $this->getSeverityForDays($days),
                    'triggered_at' => $now,
                    'payload' => [
                        'days_until_expiry' => $daysUntilExpiry,
                        'expires_at' => $expiresAt->toIso8601String(),
                        'threshold' => $days,
                    ],
                ]);

                $this->line("    ✓ {$domain->domain} - SSL expires in {$daysUntilExpiry} days (alert sent)");
                $totalAlerts++;
            }

            $this->newLine();
        }

        if ($totalAlerts > 0) {
            $this->info("Sent {$totalAlerts} SSL expiry alert(s) to Brain.");
        } else {
            $this->info('No new SSL expiry alerts to send.');
        }

        return Command::SUCCESS;
    }

    /**
     * Send SSL expiry event to Brain
     */
    private function sendSslExpiryEvent(BrainEventClient $brain, Domain $domain, int $daysUntilExpiry, int $threshold, \Carbon\Carbon $expiresAt): void
    {
        $severity = $this->getSeverityForDays($threshold);
        $eventType = 'domain.ssl.expiring';

        $fingerprint = "domain.ssl.expiring:{$domain->domain}:{$threshold}";

        $message = sprintf(
            'SSL certificate for %s expires in %d day(s)',
            $domain->domain,
            $daysUntilExpiry
        );

        $context = [
            'domain' => $domain->domain,
            'domain_id' => $domain->id,
            'days_until_expiry' => $daysUntilExpiry,
            'expires_at' => $expiresAt->toIso8601String(),
            'threshold' => $threshold,
        ];

        $payload = [
            'domain' => $domain->domain,
            'domain_id' => $domain->id,
            'days_until_expiry' => $daysUntilExpiry,
            'expires_at' => $expiresAt->toIso8601String(),
            'threshold' => $threshold,
            'severity' => $severity,
            'fingerprint' => $fingerprint,
            'message' => $message,
            'context' => $context,
        ];

        $brain->sendAsync($eventType, $payload);
    }

    /**
     * Get severity level based on days until expiry
     */
    private function getSeverityForDays(int $days): string
    {
        return match ($days) {
            30, 14 => 'warning',
            7, 3 => 'error',
            default => 'info',
        };
    }
}
