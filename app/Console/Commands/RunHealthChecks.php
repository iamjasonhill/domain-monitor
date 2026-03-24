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
    public function handle(HttpHealthCheck $httpCheck, SslHealthCheck $sslCheck, DnsHealthCheck $dnsCheck, \App\Services\EmailSecurityHealthCheck $emailSecurityCheck, \App\Services\ReputationHealthCheck $reputationCheck, \App\Services\SecurityHeadersHealthCheck $securityHeadersCheck, \App\Services\SeoHealthCheck $seoCheck, \App\Services\BrokenLinkHealthCheck $brokenLinkCheck, \App\Services\UptimeHealthCheck $uptimeCheck): int
    {
        $domainOption = $this->option('domain');
        $allOption = $this->option('all');
        $type = $this->option('type');

        if (! in_array($type, ['http', 'ssl', 'dns', 'uptime', 'email_security', 'reputation', 'security_headers', 'seo', 'broken_links'])) {
            $this->error("Invalid check type: {$type}. Must be one of: http, ssl, dns, uptime, email_security, reputation, security_headers, seo, broken_links");

            return Command::FAILURE;
        }

        if ($domainOption) {
            $domain = Domain::with('platform')->where('domain', $domainOption)->first();

            if (! $domain) {
                $this->error("Domain '{$domainOption}' not found.");

                return Command::FAILURE;
            }

            return $this->runCheckForDomain($domain, $type, $httpCheck, $sslCheck, $dnsCheck, $emailSecurityCheck, $reputationCheck, $securityHeadersCheck, $seoCheck, $brokenLinkCheck, $uptimeCheck);
        }

        if ($allOption) {
            $excludeEmailOnly = in_array($type, ['http', 'ssl', 'security_headers', 'seo', 'uptime', 'broken_links'], true);

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
                if ($this->runCheckForDomain($domain, $type, $httpCheck, $sslCheck, $dnsCheck, $emailSecurityCheck, $reputationCheck, $securityHeadersCheck, $seoCheck, $brokenLinkCheck, $uptimeCheck, false) === Command::SUCCESS) {
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
    private function runCheckForDomain(Domain $domain, string $type, HttpHealthCheck $httpCheck, SslHealthCheck $sslCheck, DnsHealthCheck $dnsCheck, \App\Services\EmailSecurityHealthCheck $emailSecurityCheck, \App\Services\ReputationHealthCheck $reputationCheck, \App\Services\SecurityHeadersHealthCheck $securityHeadersCheck, \App\Services\SeoHealthCheck $seoCheck, \App\Services\BrokenLinkHealthCheck $brokenLinkCheck, \App\Services\UptimeHealthCheck $uptimeCheck, bool $verbose = true): int
    {
        if ($verbose) {
            $this->info("Running {$type} check for: {$domain->domain}");
        }

        try {
            // Ensure platform relationship is available for accurate parked detection
            $domain->loadMissing('platform');

            $skipReason = $domain->monitoringSkipReason($type);

            if ($skipReason !== null) {
                if ($verbose) {
                    $this->line("  Skipped: {$skipReason}");
                }

                return Command::SUCCESS;
            }

            $startedAt = now();
            $status = 'fail';
            $responseCode = null;
            $errorMessage = null;
            $payload = [];

            $httpResult = null;
            $uptimeResult = null;
            $sslResult = null;
            $dnsResult = null;
            $emailSecurityResult = null;
            $reputationResult = null;
            $securityHeadersResult = null;
            $seoResult = null;
            $brokenLinkResult = null;

            if ($type === 'http') {
                $httpResult = $httpCheck->check($domain->domain);
                $status = $httpResult['is_up'] ? 'ok' : 'fail';
                if ($httpResult['status_code'] && $httpResult['status_code'] >= 400 && $httpResult['status_code'] < 500) {
                    $status = 'warn';
                }
                $responseCode = $httpResult['status_code'];
                $errorMessage = $httpResult['error_message'];
                $payload = $httpResult['payload'];
            } elseif ($type === 'uptime') {
                $uptimeResult = $uptimeCheck->check($domain->domain);
                $status = $uptimeResult['is_valid'] ? 'ok' : 'fail';
                $errorMessage = $uptimeResult['error_message'];
                $payload = $uptimeResult['payload'];
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
                /** @var array<int, string> $selectors */
                $selectors = $domain->dkim_selectors ?? [];
                $emailSecurityResult = $emailSecurityCheck->check($domain->domain, $selectors);
                $status = $this->determineEmailSecurityStatus($emailSecurityResult);
                $errorMessage = $emailSecurityResult['error_message'];
                $payload = $emailSecurityResult['payload'];
            } elseif ($type === 'reputation') {
                $reputationResult = $reputationCheck->check($domain->domain);
                $status = $this->determineReputationStatus($reputationResult);
                $errorMessage = $reputationResult['error_message'];
                $payload = $reputationResult['payload'];
            } elseif ($type === 'security_headers') {
                $securityHeadersResult = $securityHeadersCheck->check($domain->domain);
                $status = $this->determineSecurityHeadersStatus($securityHeadersResult);
                $errorMessage = $securityHeadersResult['error_message'];
                $payload = $securityHeadersResult['payload'];
            } elseif ($type === 'seo') {
                $seoResult = $seoCheck->check($domain->domain);
                $status = $this->determineSeoStatus($seoResult);
                $errorMessage = $seoResult['error_message'];
                $payload = $seoResult['payload'];
            } elseif ($type === 'broken_links') {
                $brokenLinkResult = $brokenLinkCheck->check($domain->domain);
                $status = $this->determineBrokenLinksStatus($brokenLinkResult);
                $errorMessage = $brokenLinkResult['error_message'];
                $payload = $brokenLinkResult['payload'];
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
                if ($uptimeResult) {
                    $this->line('  Uptime: '.($uptimeResult['is_valid'] ? 'Up' : 'Down'));
                    if ($uptimeResult['status_code']) {
                        $this->line("  Status Code: {$uptimeResult['status_code']}");
                    }
                }
                if ($sslResult && isset($sslResult['days_until_expiry'])) {
                    $this->line("  Days Until Expiry: {$sslResult['days_until_expiry']}");
                    if ($sslResult['issuer']) {
                        $this->line("  Issuer: {$sslResult['issuer']}");
                    }
                    $this->line('  Protocol: '.($sslResult['protocol'] ?? 'N/A'));
                    $this->line('  Cipher: '.($sslResult['cipher'] ?? 'N/A'));
                }
                if ($dnsResult) {
                    $this->line('  Has A Record: '.($dnsResult['has_a_record'] ? 'Yes' : 'No'));
                    $this->line('  Has MX Record: '.($dnsResult['has_mx_record'] ? 'Yes' : 'No'));
                    if (! empty($dnsResult['nameservers'])) {
                        $this->line('  Nameservers: '.count($dnsResult['nameservers']));
                    }
                }
                if ($emailSecurityResult) {
                    $this->line('  SPF: '.strtoupper($emailSecurityResult['spf']['status'] ?? 'unknown'));
                    $this->line('  DMARC: '.strtoupper($emailSecurityResult['dmarc']['status'] ?? 'unknown'));
                    if (! empty($emailSecurityResult['overall_assessment'])) {
                        $this->line('  Assessment: '.$emailSecurityResult['overall_assessment']);
                    }
                }
                if ($reputationResult) {
                    $this->line('  Safe Browsing: '.($reputationResult['google_safe_browsing']['safe'] ? 'Safe' : 'Unsafe'));
                    $this->line('  Spamhaus: '.($reputationResult['dnsbl']['spamhaus']['listed'] ? 'Listed' : 'Clean'));
                }
                if ($securityHeadersResult) {
                    $this->line("  Score: {$securityHeadersResult['score']}/100");
                    $this->line('  HSTS: '.($securityHeadersResult['headers']['strict-transport-security']['present'] ? 'Yes' : 'No'));
                    $this->line('  CSP: '.($securityHeadersResult['headers']['content-security-policy']['present'] ? 'Yes' : 'No'));
                }
                if ($seoResult) {
                    $this->line('  Robots.txt: '.($seoResult['results']['robots']['exists'] ? 'Found' : 'Missing'));
                    $this->line('  Sitemap.xml: '.($seoResult['results']['sitemap']['exists'] ? 'Found' : 'Missing'));
                }
                if ($brokenLinkResult) {
                    $this->line("  Pages Scanned: {$brokenLinkResult['pages_scanned']}");
                    $this->line("  Broken Links: {$brokenLinkResult['broken_links_count']}");
                    if (! empty($brokenLinkResult['broken_links'])) {
                        foreach (array_slice($brokenLinkResult['broken_links'], 0, 5) as $link) {
                            $this->line("    - {$link['url']} ({$link['status']}) on {$link['found_on']}");
                        }
                        if (count($brokenLinkResult['broken_links']) > 5) {
                            $this->line('    ... and '.(count($brokenLinkResult['broken_links']) - 5).' more');
                        }
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

    /**
     * @param  array<string, mixed>  $result
     */
    private function determineEmailSecurityStatus(array $result): string
    {
        if (is_string($result['overall_status'] ?? null)) {
            return $result['overall_status'];
        }

        if (($result['spf']['verified'] ?? false) !== true || ($result['dmarc']['verified'] ?? false) !== true) {
            return 'unknown';
        }

        if (($result['spf']['status'] ?? null) === 'fail' || ($result['dmarc']['status'] ?? null) === 'fail') {
            return 'fail';
        }

        if (($result['spf']['status'] ?? null) === 'warn' || ($result['dmarc']['status'] ?? null) === 'warn') {
            return 'warn';
        }

        return $result['is_valid'] ? 'ok' : 'unknown';
    }

    /**
     * @param  array{
     *     is_valid: bool,
     *     google_safe_browsing: array{verified?: bool},
     *     dnsbl: array{spamhaus: array{verified?: bool}}
     * }  $result
     */
    private function determineReputationStatus(array $result): string
    {
        if (($result['google_safe_browsing']['verified'] ?? false) !== true
            || ($result['dnsbl']['spamhaus']['verified'] ?? false) !== true) {
            return 'unknown';
        }

        return $result['is_valid'] ? 'ok' : 'fail';
    }

    /**
     * @param  array{is_valid: bool, verified?: bool}  $result
     */
    private function determineSecurityHeadersStatus(array $result): string
    {
        if (($result['verified'] ?? false) !== true) {
            return 'unknown';
        }

        return $result['is_valid'] ? 'ok' : 'warn';
    }

    /**
     * @param  array{is_valid: bool, verified?: bool}  $result
     */
    private function determineSeoStatus(array $result): string
    {
        if (($result['verified'] ?? false) !== true) {
            return 'unknown';
        }

        return $result['is_valid'] ? 'ok' : 'warn';
    }

    /**
     * @param  array{is_valid: bool, verified?: bool}  $result
     */
    private function determineBrokenLinksStatus(array $result): string
    {
        if (($result['verified'] ?? false) !== true) {
            return 'unknown';
        }

        return $result['is_valid'] ? 'ok' : 'fail';
    }
}
