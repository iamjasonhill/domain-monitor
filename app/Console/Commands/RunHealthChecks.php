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
    public function handle(HttpHealthCheck $httpCheck, SslHealthCheck $sslCheck, DnsHealthCheck $dnsCheck, \App\Services\EmailSecurityHealthCheck $emailSecurityCheck, \App\Services\ReputationHealthCheck $reputationCheck): int
    {
        $domainOption = $this->option('domain');
        $allOption = $this->option('all');
        $type = $this->option('type');

        if (! in_array($type, ['http', 'ssl', 'dns', 'uptime', 'email_security', 'reputation'])) {
            $this->error("Invalid check type: {$type}. Must be one of: http, ssl, dns, uptime, email_security, reputation");

            return Command::FAILURE;
        }

        if ($domainOption) {
            $domain = Domain::with('platform')->where('domain', $domainOption)->first();

            if (! $domain) {
                $this->error("Domain '{$domainOption}' not found.");

                return Command::FAILURE;
            }

            return $this->runCheckForDomain($domain, $type, $httpCheck, $sslCheck, $dnsCheck, $emailSecurityCheck, $reputationCheck);
        }

        if ($allOption) {
            $excludeEmailOnly = in_array($type, ['http', 'ssl'], true);

            $domains = Domain::with('platform')
                ->where('is_active', true)
                ->excludeParked(true)
                ->excludeEmailOnly($excludeEmailOnly)
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
                if ($this->runCheckForDomain($domain, $type, $httpCheck, $sslCheck, $dnsCheck, $emailSecurityCheck, $reputationCheck, false) === Command::SUCCESS) {
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
    private function runCheckForDomain(Domain $domain, string $type, HttpHealthCheck $httpCheck, SslHealthCheck $sslCheck, DnsHealthCheck $dnsCheck, \App\Services\EmailSecurityHealthCheck $emailSecurityCheck, \App\Services\ReputationHealthCheck $reputationCheck, bool $verbose = true): int
    {
        if ($verbose) {
            $this->info("Running {$type} check for: {$domain->domain}");
        }

        try {
            // Ensure platform relationship is available for accurate parked detection
            $domain->loadMissing('platform');

            if ($domain->isParked() && $type !== 'reputation') {
                if ($verbose) {
                    $this->line('  Skipped: domain is marked as parked');
                }

                return Command::SUCCESS;
            }

            if ($domain->isEmailOnly() && in_array($type, ['http', 'ssl'], true)) {
                if ($verbose) {
                    $this->line('  Skipped: domain is marked as email-only (HTTP/SSL checks disabled)');
                }

                return Command::SUCCESS;
            }

            $startedAt = now();
            $status = 'fail';
            $responseCode = null;
            $errorMessage = null;
            $payload = [];

            $httpResult = null;
            $sslResult = null;
            $dnsResult = null;
            $emailSecurityResult = null;
            $reputationResult = null;

            if ($type === 'http') {
                $httpResult = $httpCheck->check($domain->domain);
                $status = $httpResult['is_up'] ? 'ok' : 'fail';
                if ($httpResult['status_code'] && $httpResult['status_code'] >= 400 && $httpResult['status_code'] < 500) {
                    $status = 'warn';
                }
                $responseCode = $httpResult['status_code'];
                $errorMessage = $httpResult['error_message'];
                $payload = $httpResult['payload'];
            } elseif ($type === 'ssl') {
                $sslResult = $sslCheck->check($domain->domain);
                $status = $sslResult['is_valid'] ? 'ok' : 'fail';
                // Warn if certificate expires within 30 days
                if ($sslResult['is_valid'] && $sslResult['days_until_expiry'] !== null && $sslResult['days_until_expiry'] <= 30) {
                    $status = 'warn';
                }
                $errorMessage = $sslResult['error_message'];
                $payload = $sslResult['payload'];
            } elseif ($type === 'dns') {
                $dnsResult = $dnsCheck->check($domain->domain);
                $status = $dnsResult['is_valid'] ? 'ok' : 'fail';
                $errorMessage = $dnsResult['error_message'];
                $payload = $dnsResult['payload'];
            } elseif ($type === 'email_security') {
                $emailSecurityResult = $emailSecurityCheck->check($domain->domain);
                $status = $emailSecurityResult['is_valid'] ? 'ok' : 'fail';
                $errorMessage = $emailSecurityResult['error_message'];
                $payload = $emailSecurityResult['payload'];
            } elseif ($type === 'reputation') {
                $reputationResult = $reputationCheck->check($domain->domain);
                $status = $reputationResult['is_valid'] ? 'ok' : 'fail';
                $errorMessage = $reputationResult['error_message'];
                $payload = $reputationResult['payload'];
            } else {
                if ($verbose) {
                    $this->warn("  Check type '{$type}' not yet implemented");
                }

                return Command::FAILURE;
            }

            $duration = (isset($payload['duration_ms']) && is_int($payload['duration_ms']))
                ? $payload['duration_ms']
                : (int) ((microtime(true) - $startedAt->getTimestamp()) * 1000);

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
                if ($sslResult && isset($sslResult['days_until_expiry'])) {
                    $this->line("  Days Until Expiry: {$sslResult['days_until_expiry']}");
                    if ($sslResult['issuer']) {
                        $this->line("  Issuer: {$sslResult['issuer']}");
                    }
                }
                if ($dnsResult) {
                    $this->line('  Has A Record: '.($dnsResult['has_a_record'] ? 'Yes' : 'No'));
                    $this->line('  Has MX Record: '.($dnsResult['has_mx_record'] ? 'Yes' : 'No'));
                    if (! empty($dnsResult['nameservers'])) {
                        $this->line('  Nameservers: '.count($dnsResult['nameservers']));
                    }
                }
                if ($emailSecurityResult) {
                    $this->line('  SPF: '.($emailSecurityResult['spf']['valid'] ? 'Pass' : 'Fail'));
                    $this->line('  DMARC: '.($emailSecurityResult['dmarc']['valid'] ? 'Pass' : 'Fail'));
                }
                if ($reputationResult) {
                    $this->line('  Safe Browsing: '.($reputationResult['google_safe_browsing']['safe'] ? 'Safe' : 'Unsafe'));
                    $this->line('  Spamhaus: '.($reputationResult['dnsbl']['spamhaus']['listed'] ? 'Listed' : 'Clean'));
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
