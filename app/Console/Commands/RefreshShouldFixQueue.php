<?php

namespace App\Console\Commands;

use App\Models\Domain;
use App\Services\DashboardIssueQueueService;
use Illuminate\Console\Command;

class RefreshShouldFixQueue extends Command
{
    protected $signature = 'domains:refresh-should-fix';

    protected $description = 'Refresh health checks for domains currently in the dashboard should-fix queue';

    /**
     * @var array<int, string>
     */
    private const REFRESHABLE_CHECK_TYPES = [
        'uptime',
        'http',
        'ssl',
        'dns',
        'email_security',
        'security_headers',
        'seo',
        'reputation',
        'broken_links',
    ];

    public function handle(DashboardIssueQueueService $queueService): int
    {
        $domains = Domain::query()
            ->where('is_active', true)
            ->with([
                'platform',
                'webProperties:id,slug,name,property_type,status',
                'webProperties.repositories:id,web_property_id,repo_name,local_path,is_primary',
            ])
            ->withLatestCheckStatuses()
            ->withCount([
                'alerts as open_critical_alerts_count' => fn ($query) => $query
                    ->whereNull('resolved_at')
                    ->whereIn('severity', ['critical', 'error']),
                'alerts as open_warning_alerts_count' => fn ($query) => $query
                    ->whereNull('resolved_at')
                    ->whereIn('severity', ['warn', 'warning', 'info']),
            ])
            ->get()
            ->keyBy('id');

        [, $shouldFixQueue] = $queueService->buildIssueQueues($domains->values());

        if ($shouldFixQueue->isEmpty()) {
            $this->info('No should-fix domains are currently queued.');

            return self::SUCCESS;
        }

        $domainsRefreshed = 0;
        $checksRun = 0;

        foreach ($shouldFixQueue as $item) {
            $domain = $domains->get($item['id'] ?? null);

            if (! $domain instanceof Domain) {
                continue;
            }

            $checkTypes = $this->refreshableCheckTypesForDomain($domain);

            if ($checkTypes === []) {
                continue;
            }

            $domainsRefreshed++;

            foreach ($checkTypes as $checkType) {
                $this->line("Refreshing {$checkType} for {$domain->domain}");

                $exitCode = $this->callSilently('domains:health-check', [
                    '--domain' => $domain->domain,
                    '--type' => $checkType,
                ]);

                if ($exitCode !== 0) {
                    $this->warn("  {$checkType} refresh failed for {$domain->domain}");

                    continue;
                }

                $checksRun++;
            }
        }

        $this->info("Refreshed {$checksRun} check(s) across {$domainsRefreshed} should-fix domain(s).");

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function refreshableCheckTypesForDomain(Domain $domain): array
    {
        $checkTypes = [];

        foreach (self::REFRESHABLE_CHECK_TYPES as $checkType) {
            if ($domain->shouldSkipMonitoringCheck($checkType)) {
                continue;
            }

            $status = $domain->{'latest_'.$checkType.'_status'} ?? null;

            if (in_array($status, ['warn', 'fail'], true)) {
                $checkTypes[] = $checkType;
            }
        }

        return $checkTypes;
    }
}
