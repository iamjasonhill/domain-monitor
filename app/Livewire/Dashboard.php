<?php

namespace App\Livewire;

use App\Models\Domain;
use App\Models\DomainCheck;
use App\Models\Subdomain;
use App\Services\DashboardIssueQueueService;
use App\Services\DomainMonitorSettings;
use App\Services\ManualCsvBacklogService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Livewire\Component;

class Dashboard extends Component
{
    public function refreshShouldFixQueue(): void
    {
        try {
            $exitCode = Artisan::call('domains:refresh-should-fix');

            if ($exitCode !== 0) {
                session()->flash('error', 'Should-fix refresh failed. Please check logs.');

                return;
            }

            session()->flash('message', trim(Artisan::output()) ?: 'Should-fix issues refreshed successfully.');
        } catch (\Throwable $e) {
            session()->flash('error', 'Should-fix refresh failed: '.$e->getMessage());
        }
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        $recentFailuresHours = app(DomainMonitorSettings::class)->recentFailuresHours();

        $stats = [
            'total_domains' => Domain::count(),
            'active_domains' => Domain::where('is_active', true)->count(),
            'expiring_soon' => Domain::where('is_active', true)
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', now()->addDays(30)->endOfDay())
                ->where('expires_at', '>', now())
                ->count(),
            'recent_failures' => DomainCheck::where('status', 'fail')
                ->where('created_at', '>=', now()->subHours($recentFailuresHours))
                ->distinct('domain_id')
                ->count('domain_id'),
            'failed_eligibility' => Domain::where('eligibility_valid', false)->count(),
        ];

        $queueSnapshot = app(DashboardIssueQueueService::class)->snapshot();

        /** @var array<int, array<string, mixed>> $mustFixDomains */
        $mustFixDomains = is_array($queueSnapshot['must_fix'] ?? null) ? $queueSnapshot['must_fix'] : [];
        /** @var array<int, array<string, mixed>> $shouldFixDomains */
        $shouldFixDomains = is_array($queueSnapshot['should_fix'] ?? null) ? $queueSnapshot['should_fix'] : [];

        $stats['must_fix'] = (int) data_get($queueSnapshot, 'stats.must_fix', 0);
        $stats['should_fix'] = (int) data_get($queueSnapshot, 'stats.should_fix', 0);

        $manualCsvBacklog = app(ManualCsvBacklogService::class);
        $manualCsvPendingItems = collect($manualCsvBacklog->pendingItems());
        $manualCsvPendingStats = $manualCsvBacklog->stats();
        $stats['manual_csv_pending'] = $manualCsvPendingStats['pending_properties'];

        $subdomains = Subdomain::with('domain')
            ->where('is_active', true)
            ->whereNotNull('ip_checked_at')
            ->get();

        $unresolvedWebSubdomains = $subdomains
            ->filter(fn (Subdomain $subdomain): bool => $subdomain->resolutionState() === 'unresolved')
            ->groupBy('domain_id')
            ->map(function (Collection $items): array {
                /** @var Subdomain|null $first */
                $first = $items->first();

                return [
                    'domain_id' => $first?->domain_id,
                    'domain' => $first?->domain->domain ?? 'Unknown domain',
                    'count' => $items->count(),
                    'hosts' => $items->pluck('full_domain')->take(5)->values()->all(),
                ];
            })
            ->sortByDesc('count')
            ->values();

        $stats['unresolved_web_subdomains'] = $unresolvedWebSubdomains->sum('count');

        return view('livewire.dashboard', [
            'stats' => $stats,
            'mustFixDomains' => new Collection($mustFixDomains),
            'shouldFixDomains' => new Collection($shouldFixDomains),
            'manualCsvPendingItems' => $manualCsvPendingItems->take(6),
            'manualCsvPendingStats' => $manualCsvPendingStats,
            'unresolvedWebSubdomainDomains' => $unresolvedWebSubdomains->take(8),
            'recentFailuresHours' => $recentFailuresHours,
        ]);
    }
}
