<?php

namespace App\Console\Commands;

use App\Models\Domain;
use App\Services\HttpHealthCheck;
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
    public function handle(HttpHealthCheck $httpCheck): int
    {
        $domainOption = $this->option('domain');
        $allOption = $this->option('all');
        $type = $this->option('type');

        if (! in_array($type, ['http', 'ssl', 'dns', 'uptime'])) {
            $this->error("Invalid check type: {$type}. Must be one of: http, ssl, dns, uptime");

            return Command::FAILURE;
        }

        if ($domainOption) {
            $domain = Domain::where('domain', $domainOption)->first();

            if (! $domain) {
                $this->error("Domain '{$domainOption}' not found.");

                return Command::FAILURE;
            }

            return $this->runCheckForDomain($domain, $type, $httpCheck);
        }

        if ($allOption) {
            $domains = Domain::where('is_active', true)->get();

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
                if ($this->runCheckForDomain($domain, $type, $httpCheck, false) === Command::SUCCESS) {
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
    private function runCheckForDomain(Domain $domain, string $type, HttpHealthCheck $httpCheck, bool $verbose = true): int
    {
        if ($verbose) {
            $this->info("Running {$type} check for: {$domain->domain}");
        }

        try {
            $startedAt = now();

            // For now, only HTTP checks are implemented
            if ($type === 'http') {
                $result = $httpCheck->check($domain->domain);

                $status = $result['is_up'] ? 'ok' : 'fail';
                if ($result['status_code'] && $result['status_code'] >= 400 && $result['status_code'] < 500) {
                    $status = 'warn';
                }

                $check = $domain->checks()->create([
                    'check_type' => 'http',
                    'status' => $status,
                    'response_code' => $result['status_code'],
                    'started_at' => $startedAt,
                    'finished_at' => now(),
                    'duration_ms' => $result['duration_ms'],
                    'error_message' => $result['error_message'],
                    'payload' => $result['payload'],
                    'retry_count' => 0,
                ]);

                // Update domain's last_checked_at
                $domain->update(['last_checked_at' => now()]);

                if ($verbose) {
                    $this->line("  Status: {$status}");
                    if ($result['status_code']) {
                        $this->line("  Response Code: {$result['status_code']}");
                    }
                    $this->line("  Duration: {$result['duration_ms']}ms");
                    if ($result['error_message']) {
                        $this->line("  Error: {$result['error_message']}");
                    }
                }

                return Command::SUCCESS;
            }

            // Placeholder for other check types
            if ($verbose) {
                $this->warn("  Check type '{$type}' not yet implemented");
            }

            return Command::FAILURE;
        } catch (\Exception $e) {
            if ($verbose) {
                $this->error("  Failed: {$e->getMessage()}");
            }

            return Command::FAILURE;
        }
    }
}
