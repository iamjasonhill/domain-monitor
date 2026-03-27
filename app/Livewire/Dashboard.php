<?php

namespace App\Livewire;

use App\Models\Domain;
use App\Models\DomainCheck;
use App\Services\DashboardIssueQueueService;
use App\Services\DomainMonitorSettings;
use Illuminate\Support\Collection;
use Livewire\Component;

class Dashboard extends Component
{
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

        $recentDomains = Domain::where('is_active', true)
            ->orderBy('updated_at', 'desc')
            ->limit(5)
            ->get();

        $recentChecks = DomainCheck::with('domain')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return view('livewire.dashboard', [
            'stats' => $stats,
            'mustFixDomains' => new Collection($mustFixDomains),
            'shouldFixDomains' => new Collection($shouldFixDomains),
            'recentDomains' => $recentDomains,
            'recentChecks' => $recentChecks,
            'recentFailuresHours' => $recentFailuresHours,
        ]);
    }
}
