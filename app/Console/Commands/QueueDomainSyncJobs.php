<?php

namespace App\Console\Commands;

use App\Jobs\ImportSynergyDomainsJob;
use App\Jobs\SyncDnsRecordsJob;
use App\Jobs\SyncDomainContactsJob;
use App\Jobs\SyncDomainInfoJob;
use App\Models\Domain;
use App\Services\SynergyWholesaleClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class QueueDomainSyncJobs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'domains:queue-sync-jobs
                            {--type=all : Type of sync to queue (all, info, dns, contacts, import)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Queue domain sync jobs for processing via Horizon (prevents gateway timeouts)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $type = $this->option('type');

        if ($type === 'all' || $type === 'info') {
            $this->queueDomainInfoSync();
        }

        if ($type === 'all' || $type === 'dns') {
            $this->queueDnsRecordsSync();
        }

        if ($type === 'all' || $type === 'contacts') {
            $this->queueDomainContactsSync();
        }

        if ($type === 'all' || $type === 'import') {
            $this->queueDomainImport();
        }

        return Command::SUCCESS;
    }

    /**
     * Queue domain info sync jobs
     */
    private function queueDomainInfoSync(): void
    {
        $domains = Domain::where('is_active', true)
            ->get()
            ->filter(function ($domain) {
                return SynergyWholesaleClient::isAustralianTld($domain->domain);
            });

        if ($domains->isEmpty()) {
            $this->warn('No active Australian TLD domains found for info sync.');

            return;
        }

        $this->info("Queueing domain info sync for {$domains->count()} domain(s)...");

        $delay = 0;
        $queued = 0;
        foreach ($domains as $domain) {
            SyncDomainInfoJob::dispatch($domain->id)
                ->delay(now()->addSeconds($delay));
            $delay += 5; // 5 second delay between each domain
            $queued++;
        }

        $this->info("✅ Queued {$queued} domain info sync job(s).");
        Log::info('QueueDomainSyncJobs: Queued domain info sync jobs', [
            'count' => $queued,
        ]);
    }

    /**
     * Queue DNS records sync jobs
     */
    private function queueDnsRecordsSync(): void
    {
        $domains = Domain::where('is_active', true)
            ->get()
            ->filter(function ($domain) {
                return SynergyWholesaleClient::isAustralianTld($domain->domain);
            });

        if ($domains->isEmpty()) {
            $this->warn('No active Australian TLD domains found for DNS sync.');

            return;
        }

        $this->info("Queueing DNS records sync for {$domains->count()} domain(s)...");

        $delay = 0;
        $queued = 0;
        foreach ($domains as $domain) {
            SyncDnsRecordsJob::dispatch($domain->id)
                ->delay(now()->addSeconds($delay));
            $delay += 5; // 5 second delay between each domain
            $queued++;
        }

        $this->info("✅ Queued {$queued} DNS records sync job(s).");
        Log::info('QueueDomainSyncJobs: Queued DNS records sync jobs', [
            'count' => $queued,
        ]);
    }

    /**
     * Queue domain import job
     */
    private function queueDomainImport(): void
    {
        $this->info('Queueing domain import job...');

        ImportSynergyDomainsJob::dispatch();

        $this->info('✅ Queued domain import job.');
        Log::info('QueueDomainSyncJobs: Queued domain import job');
    }

    /**
     * Queue domain contacts sync jobs
     */
    private function queueDomainContactsSync(): void
    {
        $domains = Domain::where('is_active', true)
            ->get()
            ->filter(function ($domain) {
                return SynergyWholesaleClient::isAustralianTld($domain->domain);
            });

        if ($domains->isEmpty()) {
            $this->warn('No active Australian TLD domains found for contacts sync.');

            return;
        }

        $this->info("Queueing domain contacts sync for {$domains->count()} domain(s)...");

        $delay = 0;
        $queued = 0;
        foreach ($domains as $domain) {
            SyncDomainContactsJob::dispatch($domain->id)
                ->delay(now()->addSeconds($delay));
            $delay += 5; // 5 second delay between each domain
            $queued++;
        }

        $this->info("✅ Queued {$queued} domain contacts sync job(s).");
        Log::info('QueueDomainSyncJobs: Queued domain contacts sync jobs', [
            'count' => $queued,
        ]);
    }
}
