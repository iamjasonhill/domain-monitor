<?php

namespace App\Livewire;

use App\Models\Domain;
use App\Models\DomainCheck;
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

        $domains = Domain::query()
            ->where('is_active', true)
            ->with('platform')
            ->withLatestCheckStatuses()
            ->withCount([
                'alerts as open_critical_alerts_count' => fn ($query) => $query
                    ->whereNull('resolved_at')
                    ->whereIn('severity', ['critical', 'error']),
                'alerts as open_warning_alerts_count' => fn ($query) => $query
                    ->whereNull('resolved_at')
                    ->whereIn('severity', ['warn', 'warning', 'info']),
            ])
            ->get();

        [$mustFixDomains, $shouldFixDomains] = $this->buildIssueQueues($domains);

        $stats['must_fix'] = $mustFixDomains->count();
        $stats['should_fix'] = $shouldFixDomains->count();

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
            'mustFixDomains' => $mustFixDomains,
            'shouldFixDomains' => $shouldFixDomains,
            'recentDomains' => $recentDomains,
            'recentChecks' => $recentChecks,
            'recentFailuresHours' => $recentFailuresHours,
        ]);
    }

    /**
     * @param  Collection<int, Domain>  $domains
     * @return array{0: Collection<int, array<string, mixed>>, 1: Collection<int, array<string, mixed>>}
     */
    private function buildIssueQueues(Collection $domains): array
    {
        $mustFixDomains = collect();
        $shouldFixDomains = collect();

        foreach ($domains as $domain) {
            if ($domain->isParkedForHosting()) {
                continue;
            }

            [$mustFixReasons, $shouldFixReasons] = $this->issueReasonsForDomain($domain);

            if ($mustFixReasons !== []) {
                $mustFixDomains->push($this->makeQueueItem($domain, $mustFixReasons, $shouldFixReasons));

                continue;
            }

            if ($shouldFixReasons !== []) {
                $shouldFixDomains->push($this->makeQueueItem($domain, $shouldFixReasons));
            }
        }

        $sorter = fn (array $left, array $right): int => ($right['primary_reason_count'] <=> $left['primary_reason_count'])
            ?: ($right['secondary_reason_count'] <=> $left['secondary_reason_count'])
            ?: (strtotime($right['updated_at_iso']) <=> strtotime($left['updated_at_iso']));

        return [
            $mustFixDomains->sort($sorter)->values(),
            $shouldFixDomains->sort($sorter)->values(),
        ];
    }

    /**
     * @return array{0: array<int, string>, 1: array<int, string>}
     */
    private function issueReasonsForDomain(Domain $domain): array
    {
        $mustFix = [];
        $shouldFix = [];

        if ((int) ($domain->open_critical_alerts_count ?? 0) > 0) {
            $mustFix[] = $this->formatAlertReason((int) $domain->open_critical_alerts_count, 'critical');
        }

        if ($domain->eligibility_valid === false) {
            $mustFix[] = 'Eligibility or compliance has failed';
        }

        $mustFix = array_merge($mustFix, $this->statusReasonSet($domain, [
            'uptime' => ['fail' => 'Uptime check is failing', 'warn' => 'Uptime is unstable'],
            'http' => ['fail' => 'HTTP check is failing', 'warn' => 'HTTP check needs review'],
            'ssl' => ['fail' => 'SSL is failing', 'warn' => 'SSL needs review'],
            'dns' => ['fail' => 'DNS check is failing', 'warn' => 'DNS needs review'],
        ], ['fail']));

        $shouldFix = array_merge($shouldFix, $this->statusReasonSet($domain, [
            'uptime' => ['warn' => 'Uptime is unstable'],
            'http' => ['warn' => 'HTTP check needs review'],
            'ssl' => ['warn' => 'SSL needs review'],
            'dns' => ['warn' => 'DNS needs review'],
            'email_security' => ['fail' => 'Email security is missing baseline protection', 'warn' => 'Email security needs review'],
            'security_headers' => ['fail' => 'Security headers are missing or invalid', 'warn' => 'Security headers need review'],
            'seo' => ['fail' => 'SEO checks are failing', 'warn' => 'SEO checks need review'],
            'reputation' => ['fail' => 'Reputation checks are failing', 'warn' => 'Reputation needs review'],
            'broken_links' => ['fail' => 'Broken links were detected', 'warn' => 'Broken links need review'],
        ], ['warn', 'fail']));

        if ((int) ($domain->open_warning_alerts_count ?? 0) > 0) {
            $shouldFix[] = $this->formatAlertReason((int) $domain->open_warning_alerts_count, 'open');
        }

        if ($domain->expires_at && $domain->expires_at->isFuture() && $domain->expires_at->lte(now()->addDays(30)->endOfDay())) {
            $daysUntilExpiry = max(0, now()->startOfDay()->diffInDays($domain->expires_at->copy()->startOfDay(), false));
            $shouldFix[] = "Domain expires in {$daysUntilExpiry} days";
        }

        return [
            array_values(array_unique($mustFix)),
            array_values(array_unique($shouldFix)),
        ];
    }

    /**
     * @param  array<string, array<string, string>>  $definitions
     * @param  array<int, string>  $matchingStatuses
     * @return array<int, string>
     */
    private function statusReasonSet(Domain $domain, array $definitions, array $matchingStatuses): array
    {
        $reasons = [];

        foreach ($definitions as $checkType => $messages) {
            $status = $domain->{'latest_'.$checkType.'_status'} ?? null;

            if (! is_string($status) || ! in_array($status, $matchingStatuses, true)) {
                continue;
            }

            if (isset($messages[$status])) {
                $reasons[] = $messages[$status];
            }
        }

        return $reasons;
    }

    /**
     * @param  array<int, string>  $primaryReasons
     * @param  array<int, string>  $secondaryReasons
     * @return array<string, mixed>
     */
    private function makeQueueItem(Domain $domain, array $primaryReasons, array $secondaryReasons = []): array
    {
        return [
            'id' => $domain->id,
            'domain' => $domain->domain,
            'hosting_provider' => $domain->hosting_provider,
            'primary_reasons' => $primaryReasons,
            'secondary_reasons' => $secondaryReasons,
            'primary_reason_count' => count($primaryReasons),
            'secondary_reason_count' => count($secondaryReasons),
            'updated_at_human' => $domain->updated_at?->diffForHumans(),
            'updated_at_iso' => $domain->updated_at?->toIso8601String() ?? now()->toIso8601String(),
        ];
    }

    private function formatAlertReason(int $count, string $label): string
    {
        $suffix = $count === 1 ? 'alert' : 'alerts';

        return "{$count} {$label} {$suffix}";
    }
}
