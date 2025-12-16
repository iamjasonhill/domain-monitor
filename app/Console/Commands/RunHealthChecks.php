<?php

namespace App\Console\Commands;

use App\Models\Domain;
use App\Services\DnsHealthCheck;
use App\Services\HttpHealthCheck;
use App\Services\SslHealthCheck;
use Illuminate\Console\Command;

class RunHealthChecks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'domains:health-check 
                            {--domain= : Specific domain to check (optional)}
                            {--type=http : Check type (http, ssl, dns, uptime)}
                            {--all : Check all active domains}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run health checks for domains (HTTP, SSL, DNS, Uptime)';

    /**
     * Execute the console command.
     */
    public function handle(HttpHealthCheck $httpCheck, SslHealthCheck $sslCheck, DnsHealthCheck $dnsCheck): int
    {
        $domainOption = $this->option('domain');
        $allOption = $this->option('all');
        $type = $this->option('type');

        if (! in_array($type, ['http', 'ssl', 'dns', 'uptime'])) {
            $this->error("Invalid check type: {$type}. Must be one of: http, ssl, dns, uptime");

            return Command::FAILURE;
        }

        if ($domainOption) {
            $domain = Domain::with('platform')->where('domain', $domainOption)->first();

            if (! $domain) {
                $this->error("Domain '{$domainOption}' not found.");

                return Command::FAILURE;
            }

            return $this->runCheckForDomain($domain, $type, $httpCheck, $sslCheck, $dnsCheck);
        }

        if ($allOption) {
            $domains = Domain::with('platform')
                ->where('is_active', true)
                ->excludeParked(true)
                ->get();

            if ($domains->isEmpty()) {
                $this->warn('No active domains found.');

                return Command::SUCCESS;
            }

            $this->info("Running {$type} checks for {$domains->count()} domain(s)...");
            $this->newLine();

            $bar = $this->output->createProgressBar($domains->count());
            $bar->start();

            $successCount = 0;
            foreach ($domains as $domain) {
                if ($this->runCheckForDomain($domain, $type, $httpCheck, $sslCheck, $dnsCheck, false) === Command::SUCCESS) {
                    $successCount++;
                }
                $bar->advance();
            }

            $bar->finish();
            $this->newLine(2);
            $this->info("Successfully completed {$type} checks for {$successCount}/{$domains->count()} domain(s).");

            return Command::SUCCESS;
        }

        $this->error('Please specify --domain=<domain> or --all');

        return Command::FAILURE;
    }

    /**
     * Run health check for a single domain
     */
    private function runCheckForDomain(Domain $domain, string $type, HttpHealthCheck $httpCheck, SslHealthCheck $sslCheck, DnsHealthCheck $dnsCheck, bool $verbose = true): int
    {
        if ($verbose) {
            $this->info("Running {$type} check for: {$domain->domain}");
        }

        try {
            // Ensure platform relationship is available for accurate parked detection
            $domain->loadMissing('platform');

            if ($domain->isParked()) {
                if ($verbose) {
                    $this->line('  Skipped: domain is marked as parked');
                }

                return Command::SUCCESS;
            }

            $startedAt = now();
            $result = null;
            $status = 'fail';
            $responseCode = null;
            $errorMessage = null;
            $payload = [];

            if ($type === 'http') {
                // Check if domain is parked or email-only - these should not fail HTTP checks
                // Check both string attribute and relationship
                $platformType = is_string($domain->platform)
                    ? $domain->platform
                    : ($domain->platform?->platform_type ?? $domain->getAttribute('platform'));
                $isParked = $platformType === 'Parked';
                $isEmailOnly = $platformType === 'Email Only';

                $result = $httpCheck->check($domain->domain);

                // For parked or email-only domains, if we get any response (even error), mark as ok
                // Parked domains often have SSL issues but still serve content
                // Email-only domains don't have web hosting, so HTTP failures are expected
                if ($isParked || $isEmailOnly) {
                    // For email-only domains, always mark as 'ok' - they don't have web hosting
                    if ($isEmailOnly) {
                        $status = 'ok';
                        // Still try to get response code for logging, but status is always ok
                        if ($result['status_code'] === null) {
                            // Try HTTP fallback to see if we can get any response
                            $result = $httpCheck->check('http://'.$domain->domain);
                        }
                    } else {
                        // For parked domains, if we got a status code (even if it's an error), consider it ok
                        // They're serving a parked page, which is expected behavior
                        if ($result['status_code'] !== null) {
                            $status = 'ok';
                        } else {
                            // No response at all - try HTTP fallback
                            $result = $httpCheck->check('http://'.$domain->domain);
                            if ($result['status_code'] !== null) {
                                $status = 'ok';
                            } else {
                                $status = 'warn'; // Can't reach even via HTTP, but don't fail
                            }
                        }
                    }
                } else {
                    $status = $result['is_up'] ? 'ok' : 'fail';
                    if ($result['status_code'] && $result['status_code'] >= 400 && $result['status_code'] < 500) {
                        $status = 'warn';
                    }
                }
                $responseCode = $result['status_code'];
                $errorMessage = $result['error_message'];
                $payload = $result['payload'];
            } elseif ($type === 'ssl') {
                $result = $sslCheck->check($domain->domain);
                $status = $result['is_valid'] ? 'ok' : 'fail';
                // Warn if certificate expires within 30 days
                if ($result['is_valid'] && $result['days_until_expiry'] !== null && $result['days_until_expiry'] <= 30) {
                    $status = 'warn';
                }
                $errorMessage = $result['error_message'];
                $payload = $result['payload'];
            } elseif ($type === 'dns') {
                $result = $dnsCheck->check($domain->domain);
                $status = $result['is_valid'] ? 'ok' : 'fail';
                $errorMessage = $result['error_message'];
                $payload = $result['payload'];
            } else {
                if ($verbose) {
                    $this->warn("  Check type '{$type}' not yet implemented");
                }

                return Command::FAILURE;
            }

            $duration = $payload['duration_ms'] ?? (int) ((microtime(true) - $startedAt->getTimestamp()) * 1000);

            $check = $domain->checks()->create([
                'check_type' => $type,
                'status' => $status,
                'response_code' => $responseCode,
                'started_at' => $startedAt,
                'finished_at' => now(),
                'duration_ms' => $duration,
                'error_message' => $errorMessage,
                'payload' => $payload,
                'retry_count' => 0,
            ]);

            // Update domain's last_checked_at
            $domain->update(['last_checked_at' => now()]);

            if ($verbose) {
                $this->line("  Status: {$status}");
                if ($type === 'http' && $responseCode) {
                    $this->line("  Response Code: {$responseCode}");
                }
                if ($type === 'ssl' && isset($result['days_until_expiry'])) {
                    $this->line("  Days Until Expiry: {$result['days_until_expiry']}");
                    if ($result['issuer']) {
                        $this->line("  Issuer: {$result['issuer']}");
                    }
                }
                if ($type === 'dns') {
                    $this->line('  Has A Record: '.($result['has_a_record'] ? 'Yes' : 'No'));
                    $this->line('  Has MX Record: '.($result['has_mx_record'] ? 'Yes' : 'No'));
                    if (! empty($result['nameservers'])) {
                        $this->line('  Nameservers: '.count($result['nameservers']));
                    }
                }
                $this->line("  Duration: {$duration}ms");
                if ($errorMessage) {
                    $this->line("  Error: {$errorMessage}");
                }
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            if ($verbose) {
                $this->error("  Failed: {$e->getMessage()}");
            }

            return Command::FAILURE;
        }
    }
}
