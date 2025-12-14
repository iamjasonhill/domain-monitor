<?php

namespace App\Console\Commands;

use App\Models\Domain;
use App\Services\PlatformDetector;
use Illuminate\Console\Command;

class DetectPlatforms extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'domains:detect-platforms 
                            {--domain= : Specific domain to detect (optional)}
                            {--all : Detect for all active domains}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Detect platform for domains (WordPress, Laravel, Next.js, Shopify, etc.)';

    /**
     * Execute the console command.
     */
    public function handle(PlatformDetector $detector): int
    {
        $domainOption = $this->option('domain');
        $allOption = $this->option('all');

        if ($domainOption) {
            $domain = Domain::where('domain', $domainOption)->first();

            if (! $domain) {
                $this->error("Domain '{$domainOption}' not found.");

                return Command::FAILURE;
            }

            return $this->detectForDomain($domain, $detector);
        }

        if ($allOption) {
            $domains = Domain::where('is_active', true)->get();

            if ($domains->isEmpty()) {
                $this->warn('No active domains found.');

                return Command::SUCCESS;
            }

            $this->info("Detecting platforms for {$domains->count()} domain(s)...");
            $this->newLine();

            $bar = $this->output->createProgressBar($domains->count());
            $bar->start();

            $successCount = 0;
            foreach ($domains as $domain) {
                if ($this->detectForDomain($domain, $detector, false) === Command::SUCCESS) {
                    $successCount++;
                }
                $bar->advance();
            }

            $bar->finish();
            $this->newLine(2);
            $this->info("Successfully detected platforms for {$successCount}/{$domains->count()} domain(s).");

            return Command::SUCCESS;
        }

        $this->error('Please specify --domain=<domain> or --all');

        return Command::FAILURE;
    }

    /**
     * Detect platform for a single domain
     */
    private function detectForDomain(Domain $domain, PlatformDetector $detector, bool $verbose = true): int
    {
        if ($verbose) {
            $this->info("Detecting platform for: {$domain->domain}");
        }

        try {
            $result = $detector->detect($domain->domain);

            $platform = $domain->platform()->updateOrCreate(
                ['domain_id' => $domain->id],
                [
                    'platform_type' => $result['platform_type'],
                    'platform_version' => $result['platform_version'],
                    'admin_url' => $result['admin_url'],
                    'detection_confidence' => $result['detection_confidence'],
                    'last_detected' => now(),
                ]
            );

            if ($verbose) {
                $this->line("  Platform: {$platform->platform_type} ({$platform->detection_confidence} confidence)");
                if ($platform->platform_version) {
                    $this->line("  Version: {$platform->platform_version}");
                }
                if ($platform->admin_url) {
                    $this->line("  Admin URL: {$platform->admin_url}");
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
