<?php

namespace App\Jobs;

use App\Models\Domain;
use App\Models\DomainAlert;
use App\Models\DomainComplianceCheck;
use App\Models\SynergyCredential;
use App\Services\SynergyWholesaleClient;
use Brain\Client\BrainEventClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CheckComplianceJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public $backoff = 120;

    /**
     * Execute the job.
     */
    public function handle(BrainEventClient $brain): void
    {
        $credential = SynergyCredential::where('is_active', true)->first();

        if (! $credential) {
            Log::warning('CheckComplianceJob: No active Synergy credentials found');

            return;
        }

        $client = SynergyWholesaleClient::fromEncryptedCredentials(
            $credential->reseller_id,
            $credential->api_key_encrypted,
            $credential->api_url
        );

        Log::info('CheckComplianceJob: Checking compliance for all .au domains');

        try {
            $nonCompliantDomains = $client->listNonCompliantAuDomains();

            if ($nonCompliantDomains === null) {
                Log::warning('CheckComplianceJob: Could not retrieve compliance data from Synergy Wholesale');

                return;
            }

            $checkedAt = now();
            $nonCompliantCount = 0;
            $newAlerts = 0;

            // Get all Australian TLD domains
            $allAuDomains = Domain::where('is_active', true)
                ->get()
                ->filter(function ($domain) {
                    return SynergyWholesaleClient::isAustralianTld($domain->domain);
                });

            // Create a map of non-compliant domains for quick lookup
            $nonCompliantMap = [];
            foreach ($nonCompliantDomains as $ncDomain) {
                $nonCompliantMap[$ncDomain['domain']] = $ncDomain['reason'] ?? null;
            }

            // Check each domain
            foreach ($allAuDomains as $domain) {
                $isCompliant = ! isset($nonCompliantMap[$domain->domain]);
                $complianceReason = $nonCompliantMap[$domain->domain] ?? null;

                // Store compliance check history
                DomainComplianceCheck::create([
                    'domain_id' => $domain->id,
                    'is_compliant' => $isCompliant,
                    'compliance_reason' => $complianceReason,
                    'source' => 'synergy',
                    'checked_at' => $checkedAt,
                    'payload' => [
                        'domain' => $domain->domain,
                        'non_compliant_reason' => $complianceReason,
                    ],
                ]);

                // Update domain's compliance reason if non-compliant
                if (! $isCompliant) {
                    $domain->update([
                        'au_compliance_reason' => $complianceReason,
                    ]);
                    $nonCompliantCount++;

                    // Check if we need to create an alert
                    $existingAlert = DomainAlert::where('domain_id', $domain->id)
                        ->where('alert_type', 'compliance_issue')
                        ->whereNull('resolved_at')
                        ->first();

                    if (! $existingAlert) {
                        // Create new alert
                        DomainAlert::create([
                            'domain_id' => $domain->id,
                            'alert_type' => 'compliance_issue',
                            'severity' => 'critical',
                            'triggered_at' => $checkedAt,
                            'auto_resolve' => false,
                            'payload' => [
                                'reason' => $complianceReason,
                                'domain' => $domain->domain,
                            ],
                        ]);

                        // Send Brain event
                        $this->sendComplianceEvent($brain, $domain, $complianceReason);
                        $newAlerts++;
                    }
                } else {
                    // Domain is compliant - resolve any existing alerts
                    DomainAlert::where('domain_id', $domain->id)
                        ->where('alert_type', 'compliance_issue')
                        ->whereNull('resolved_at')
                        ->update([
                            'resolved_at' => $checkedAt,
                        ]);

                    // Clear compliance reason if domain is now compliant
                    if ($domain->au_compliance_reason) {
                        $domain->update([
                            'au_compliance_reason' => null,
                        ]);
                    }
                }
            }

            Log::info('CheckComplianceJob: Compliance check completed', [
                'total_domains_checked' => $allAuDomains->count(),
                'non_compliant_count' => $nonCompliantCount,
                'new_alerts_created' => $newAlerts,
            ]);
        } catch (\Exception $e) {
            Log::error('CheckComplianceJob: Failed to check compliance', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e; // Re-throw to trigger retry
        }
    }

    /**
     * Send compliance event to Brain
     */
    private function sendComplianceEvent(BrainEventClient $brain, Domain $domain, ?string $reason): void
    {
        $baseUrl = config('services.brain.base_url');
        $apiKey = config('services.brain.api_key');

        if (! $baseUrl || ! $apiKey) {
            Log::debug('Brain not configured, skipping compliance event');

            return;
        }

        $eventType = 'domain.compliance.issue';
        $payload = [
            'domain' => $domain->domain,
            'domain_id' => $domain->id,
            'reason' => $reason ?? 'Unknown compliance issue',
            'severity' => 'critical',
            'checked_at' => now()->toIso8601String(),
        ];

        $brain->sendAsync($eventType, $payload);
    }
}
