<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\DomainCheck;
use App\Models\WebProperty;
use App\Models\WebsitePlatform;
use Illuminate\Support\Collection;

class DashboardIssueQueueService
{
    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
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
            ->with([
                'platform',
                'webProperties:id,slug,name,property_type,status',
                'webProperties.repositories:id,web_property_id,repo_name,local_path,is_primary',
                'webProperties.latestSeoBaselineForProperty',
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
            ->get();

        [$mustFixDomains, $shouldFixDomains] = $this->buildIssueQueues($domains);

        $stats['must_fix'] = $mustFixDomains->count();
        $stats['should_fix'] = $shouldFixDomains->count();

        return [
            'source_system' => 'domain-monitor-priority-queue',
            'contract_version' => 2,
            'generated_at' => now()->toIso8601String(),
            'recent_failures_hours' => $recentFailuresHours,
            'stats' => $stats,
            'must_fix' => $mustFixDomains->all(),
            'should_fix' => $shouldFixDomains->all(),
            'derived' => $this->buildDerivedSummary($mustFixDomains, $shouldFixDomains),
        ];
    }

    /**
     * @param  Collection<int, Domain>  $domains
     * @return array{0: Collection<int, array<string, mixed>>, 1: Collection<int, array<string, mixed>>}
     */
    public function buildIssueQueues(Collection $domains): array
    {
        $mustFixDomains = collect();
        $shouldFixDomains = collect();

        foreach ($domains as $domain) {
            if ($domain->isParkedForHosting()) {
                continue;
            }

            $property = $this->primaryProperty($domain);
            $coverageStatus = $this->controlCoverageStatus($domain, $property);
            [$mustFixReasons, $shouldFixReasons, $mustFixIssueRecords, $shouldFixIssueRecords] = $this->issueReasonsForDomain(
                $domain,
                $property,
                $this->controlCoverageReasonForStatus($coverageStatus)
            );

            if ($mustFixReasons !== []) {
                $mustFixDomains->push($this->makeQueueItem(
                    $domain,
                    $property,
                    $coverageStatus,
                    $mustFixReasons,
                    $shouldFixReasons,
                    $mustFixIssueRecords,
                    $shouldFixIssueRecords
                ));

                continue;
            }

            if ($shouldFixReasons !== []) {
                $shouldFixDomains->push($this->makeQueueItem(
                    $domain,
                    $property,
                    $coverageStatus,
                    $shouldFixReasons,
                    [],
                    $shouldFixIssueRecords,
                    []
                ));
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
     * @return array{
     *   0: array<int, string>,
     *   1: array<int, string>,
     *   2: array<int, array{issue_family:string, reason:string, severity:string}>,
     *   3: array<int, array{issue_family:string, reason:string, severity:string}>
     * }
     */
    private function issueReasonsForDomain(Domain $domain, ?WebProperty $property, ?string $coverageReason): array
    {
        $mustFix = [];
        $shouldFix = [];
        $mustFixIssues = [];
        $shouldFixIssues = [];

        if ((int) ($domain->open_critical_alerts_count ?? 0) > 0) {
            $reason = $this->formatAlertReason((int) $domain->open_critical_alerts_count, 'critical');
            $mustFix[] = $reason;
            $mustFixIssues[] = $this->issueRecord('alerts.open', $reason, 'must_fix');
        }

        if ($domain->eligibility_valid === false) {
            $reason = 'Eligibility or compliance has failed';
            $mustFix[] = $reason;
            $mustFixIssues[] = $this->issueRecord('domain.eligibility', $reason, 'must_fix');
        }

        $mustFixStatusIssues = $this->statusIssueSet($domain, [
            'uptime' => ['fail' => 'Uptime check is failing', 'warn' => 'Uptime is unstable'],
            'http' => ['fail' => 'HTTP check is failing', 'warn' => 'HTTP check needs review'],
            'ssl' => ['fail' => 'SSL is failing', 'warn' => 'SSL needs review'],
            'dns' => ['fail' => 'DNS check is failing', 'warn' => 'DNS needs review'],
        ], ['fail']);
        $mustFix = array_merge($mustFix, array_column($mustFixStatusIssues, 'reason'));
        $mustFixIssues = array_merge($mustFixIssues, $mustFixStatusIssues);

        $shouldFixStatusIssues = $this->statusIssueSet($domain, [
            'uptime' => ['warn' => 'Uptime is unstable'],
            'http' => ['warn' => 'HTTP check needs review'],
            'ssl' => ['warn' => 'SSL needs review'],
            'dns' => ['warn' => 'DNS needs review'],
            'email_security' => ['fail' => 'Email security is missing baseline protection', 'warn' => 'Email security needs review'],
            'security_headers' => ['fail' => 'Security headers are missing or invalid', 'warn' => 'Security headers need review'],
            'seo' => ['fail' => 'SEO checks are failing', 'warn' => 'SEO checks need review'],
            'reputation' => ['fail' => 'Reputation checks are failing', 'warn' => 'Reputation needs review'],
            'broken_links' => ['fail' => 'Broken links were detected', 'warn' => 'Broken links need review'],
        ], ['warn', 'fail']);
        $shouldFix = array_merge($shouldFix, array_column($shouldFixStatusIssues, 'reason'));
        $shouldFixIssues = array_merge($shouldFixIssues, $shouldFixStatusIssues);

        if ((int) ($domain->open_warning_alerts_count ?? 0) > 0) {
            $reason = $this->formatAlertReason((int) $domain->open_warning_alerts_count, 'open');
            $shouldFix[] = $reason;
            $shouldFixIssues[] = $this->issueRecord('alerts.open', $reason, 'should_fix');
        }

        if ($domain->expires_at && $domain->expires_at->isFuture() && $domain->expires_at->lte(now()->addDays(30)->endOfDay())) {
            $daysUntilExpiry = max(0, now()->startOfDay()->diffInDays($domain->expires_at->copy()->startOfDay(), false));
            $reason = "Domain expires in {$daysUntilExpiry} days";
            $shouldFix[] = $reason;
            $shouldFixIssues[] = $this->issueRecord('domain.expiry', $reason, 'should_fix');
        }

        if ($coverageReason !== null) {
            $shouldFix[] = $coverageReason;
            $shouldFixIssues[] = $this->issueRecord('control.coverage_required', $coverageReason, 'should_fix');
        }

        if ($this->requiresControlCoverage($domain, $property) && ! $domain->shouldSkipMonitoringCheck('seo')) {
            [$baselineMustFixReasons, $baselineShouldFixReasons, $baselineMustFixIssues, $baselineShouldFixIssues] = $this->seoBaselineReasonSet($property);
            $mustFix = array_merge($mustFix, $baselineMustFixReasons);
            $shouldFix = array_merge($shouldFix, $baselineShouldFixReasons);
            $mustFixIssues = array_merge($mustFixIssues, $baselineMustFixIssues);
            $shouldFixIssues = array_merge($shouldFixIssues, $baselineShouldFixIssues);
        }

        return [
            array_values(array_unique($mustFix)),
            array_values(array_unique($shouldFix)),
            $this->uniqueIssueRecords($mustFixIssues),
            $this->uniqueIssueRecords($shouldFixIssues),
        ];
    }

    /**
     * @return array{
     *   0: array<int, string>,
     *   1: array<int, string>,
     *   2: array<int, array{issue_family:string, reason:string, severity:string}>,
     *   3: array<int, array{issue_family:string, reason:string, severity:string}>
     * }
     */
    private function seoBaselineReasonSet(?WebProperty $property): array
    {
        if (! $property instanceof WebProperty) {
            return [[], [], [], []];
        }

        $baseline = $property->latestPropertySeoBaselineRecord();

        if ($baseline === null) {
            return [[], [], [], []];
        }

        $mustFix = [];
        $shouldFix = [];
        $mustFixIssues = [];
        $shouldFixIssues = [];

        if ((int) ($baseline->pages_with_redirect ?? 0) > 0) {
            $reason = sprintf(
                'Search Console reports page with redirect (%d URLs)',
                (int) $baseline->pages_with_redirect
            );
            $mustFix[] = $reason;
            $mustFixIssues[] = $this->issueRecord('page_with_redirect_in_sitemap', $reason, 'must_fix');
        }

        if ((int) ($baseline->blocked_by_robots ?? 0) > 0) {
            $reason = sprintf(
                'Search Console reports blocked by robots.txt (%d URLs)',
                (int) $baseline->blocked_by_robots
            );
            $mustFix[] = $reason;
            $mustFixIssues[] = $this->issueRecord('blocked_by_robots_in_indexing', $reason, 'must_fix');
        }

        if ((int) ($baseline->duplicate_without_user_selected_canonical ?? 0) > 0) {
            $reason = sprintf(
                'Search Console reports duplicate without user-selected canonical (%d URLs)',
                (int) $baseline->duplicate_without_user_selected_canonical
            );
            $shouldFix[] = $reason;
            $shouldFixIssues[] = $this->issueRecord('duplicate_without_user_selected_canonical', $reason, 'should_fix');
        }

        return [$mustFix, $shouldFix, $mustFixIssues, $shouldFixIssues];
    }

    /**
     * @param  array<string, array<string, string>>  $definitions
     * @param  array<int, string>  $matchingStatuses
     * @return array<int, array{issue_family:string, reason:string, severity:string}>
     */
    private function statusIssueSet(Domain $domain, array $definitions, array $matchingStatuses): array
    {
        $issues = [];

        foreach ($definitions as $checkType => $messages) {
            if ($domain->shouldSkipMonitoringCheck($checkType)) {
                continue;
            }

            $status = $domain->{'latest_'.$checkType.'_status'} ?? null;

            if (! is_string($status) || ! in_array($status, $matchingStatuses, true)) {
                continue;
            }

            if (isset($messages[$status])) {
                $issues[] = $this->issueRecord(
                    $this->issueFamilyForCheckType($checkType),
                    $messages[$status],
                    in_array($status, ['fail'], true) ? 'must_fix' : 'should_fix'
                );
            }
        }

        return $issues;
    }

    /**
     * @param  array<int, string>  $primaryReasons
     * @param  array<int, string>  $secondaryReasons
     * @param  array<int, array{issue_family:string, reason:string, severity:string}>  $primaryIssueRecords
     * @param  array<int, array{issue_family:string, reason:string, severity:string}>  $secondaryIssueRecords
     * @return array<string, mixed>
     */
    private function makeQueueItem(
        Domain $domain,
        ?WebProperty $property,
        string $coverageStatus,
        array $primaryReasons,
        array $secondaryReasons = [],
        array $primaryIssueRecords = [],
        array $secondaryIssueRecords = []
    ): array {
        return $this->enrichQueueItem($domain, $property, [
            'id' => $domain->id,
            'domain' => $domain->domain,
            'web_property_slug' => $property?->slug,
            'web_property_name' => $property?->name,
            'platform' => $this->domainPlatform($domain),
            'hosting_provider' => $domain->hosting_provider,
            'is_email_only' => $domain->isEmailOnly(),
            'is_parked' => $domain->isParkedForHosting(),
            'primary_reasons' => $primaryReasons,
            'secondary_reasons' => $secondaryReasons,
            'primary_reason_count' => count($primaryReasons),
            'secondary_reason_count' => count($secondaryReasons),
            'primary_issue_records' => $primaryIssueRecords,
            'secondary_issue_records' => $secondaryIssueRecords,
            'coverage_required' => $this->requiresControlCoverage($domain, $property),
            'coverage_status' => $coverageStatus,
            'coverage_gap' => in_array($coverageStatus, ['missing_property', 'missing_repository', 'missing_local_path'], true),
            'updated_at_human' => $domain->updated_at?->diffForHumans(),
            'updated_at_iso' => $domain->updated_at?->toIso8601String() ?? now()->toIso8601String(),
        ]);
    }

    private function formatAlertReason(int $count, string $label): string
    {
        $suffix = $count === 1 ? 'alert' : 'alerts';

        return "{$count} {$label} {$suffix}";
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $mustFixDomains
     * @param  Collection<int, array<string, mixed>>  $shouldFixDomains
     * @return array<string, mixed>
     */
    private function buildDerivedSummary(Collection $mustFixDomains, Collection $shouldFixDomains): array
    {
        $issueFamilyCounts = [];
        $controlCounts = [];
        $platformProfileCounts = [];
        $standardGapCandidates = 0;
        $coverageGapCandidates = 0;

        foreach ($mustFixDomains->concat($shouldFixDomains) as $item) {
            $issueFamily = is_string($item['issue_family'] ?? null) ? $item['issue_family'] : null;
            $controlId = is_string($item['control_id'] ?? null) ? $item['control_id'] : null;
            $platformProfile = is_string($item['platform_profile'] ?? null) ? $item['platform_profile'] : null;

            if (($item['is_standard_gap'] ?? false) === true) {
                $standardGapCandidates++;
            }

            if (($item['coverage_gap'] ?? false) === true) {
                $coverageGapCandidates++;
            }

            $this->incrementCount($issueFamilyCounts, $issueFamily);
            $this->incrementCount($controlCounts, $controlId);
            $this->incrementCount($platformProfileCounts, $platformProfile);
        }

        return [
            'standard_gap_candidates' => $standardGapCandidates,
            'coverage_gap_candidates' => $coverageGapCandidates,
            'issue_family_counts' => $issueFamilyCounts,
            'control_counts' => $controlCounts,
            'platform_profile_counts' => $platformProfileCounts,
        ];
    }

    /**
     * @param  array<string, int>  $counts
     */
    private function incrementCount(array &$counts, ?string $key): void
    {
        if (! is_string($key) || $key === '') {
            return;
        }

        $counts[$key] = ($counts[$key] ?? 0) + 1;
    }

    private function primaryProperty(Domain $domain): ?WebProperty
    {
        $canonicalProperties = $domain->webProperties
            ->filter(fn (WebProperty $entry): bool => (bool) data_get($entry, 'pivot.is_canonical', false));

        /** @var WebProperty|null $property */
        $property = ($canonicalProperties->isNotEmpty() ? $canonicalProperties : $domain->webProperties)
            ->sortBy('name')
            ->first();

        return $property;
    }

    private function domainPlatform(Domain $domain): ?string
    {
        $platformRelation = $domain->relationLoaded('platform') ? $domain->getRelation('platform') : null;

        if ($platformRelation instanceof WebsitePlatform && is_string($platformRelation->platform_type) && $platformRelation->platform_type !== '') {
            return $platformRelation->platform_type;
        }

        return $domain->platform;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function enrichQueueItem(Domain $domain, ?WebProperty $property, array $item): array
    {
        $standards = config('domain_monitor.priority_queue_standards', []);
        $hostProfile = $this->deriveHostProfile($domain);
        $platformProfile = $this->derivePlatformProfile($domain, $hostProfile);
        $controlProfile = $this->deriveControlProfile($property, $platformProfile);
        $defaultBaselineSurface = is_string(data_get($standards, 'platform_profiles.'.$platformProfile.'.baseline_surface'))
            ? data_get($standards, 'platform_profiles.'.$platformProfile.'.baseline_surface')
            : null;
        $issueEntries = $this->buildIssueEntries($item, is_array($standards) ? $standards : [], $defaultBaselineSurface);
        $issueFamilies = array_values(array_unique(array_map(
            static fn (array $entry): string => $entry['issue_family'],
            $issueEntries
        )));
        $primaryIssueFamilies = array_values(array_unique(array_map(
            static fn (array $entry): string => $entry['issue_family'],
            array_filter($issueEntries, static fn (array $entry): bool => $entry['severity'] === 'must_fix')
        )));
        $secondaryIssueFamilies = array_values(array_unique(array_map(
            static fn (array $entry): string => $entry['issue_family'],
            array_filter($issueEntries, static fn (array $entry): bool => $entry['severity'] === 'should_fix')
        )));
        $canonicalIssue = $issueEntries[0] ?? null;
        $issueFamily = is_array($canonicalIssue) ? $canonicalIssue['issue_family'] : null;
        $controlId = is_array($canonicalIssue) ? $canonicalIssue['control_id'] : null;
        $rolloutScope = is_array($canonicalIssue) ? $canonicalIssue['rollout_scope'] : 'domain_only';
        $baselineSurface = is_array($canonicalIssue) ? $canonicalIssue['baseline_surface'] : null;

        $item['issue_family'] = $issueFamily;
        $item['issue_families'] = $issueFamilies;
        $item['primary_issue_families'] = $primaryIssueFamilies;
        $item['secondary_issue_families'] = $secondaryIssueFamilies;
        $item['issue_entries'] = $issueEntries;
        $item['control_id'] = $controlId;
        $item['platform_profile'] = $platformProfile;
        $item['host_profile'] = $hostProfile;
        $item['control_profile'] = $controlProfile;
        $item['baseline_surface'] = $baselineSurface;
        $item['property_match_confidence'] = $property ? 'high' : 'none';
        $item['rollout_scope'] = $rolloutScope;
        $item['is_standard_gap'] = $rolloutScope === 'fleet';

        return $item;
    }

    private function requiresControlCoverage(Domain $domain, ?WebProperty $property): bool
    {
        if ($domain->isParkedForHosting() || $domain->isEmailOnly()) {
            return false;
        }

        if ($property === null) {
            return true;
        }

        return $property->status === 'active'
            && in_array($property->property_type, ['marketing_site', 'website', 'app'], true);
    }

    private function controlCoverageStatus(Domain $domain, ?WebProperty $property): string
    {
        if (! $this->requiresControlCoverage($domain, $property)) {
            return 'not_required';
        }

        if ($property === null) {
            return 'missing_property';
        }

        $repositories = $property->relationLoaded('repositories')
            ? $property->getRelation('repositories')
            : $property->repositories()->get();

        if ($repositories->isEmpty()) {
            return 'missing_repository';
        }

        $hasControllerPath = $repositories->contains(
            fn ($repository): bool => is_string($repository->local_path) && trim($repository->local_path) !== ''
        );

        return $hasControllerPath ? 'controlled' : 'missing_local_path';
    }

    private function controlCoverageReasonForStatus(string $coverageStatus): ?string
    {
        return match ($coverageStatus) {
            'missing_property' => 'Public site is not linked to a web property',
            'missing_repository' => 'Fleet controller access is missing',
            'missing_local_path' => 'Fleet controller path is missing',
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, mixed>  $standards
     * @return array<int, array{issue_family:string, reason:string, severity:string, control_id:?string, rollout_scope:string, baseline_surface:?string}>
     */
    private function buildIssueEntries(array $item, array $standards, ?string $defaultBaselineSurface): array
    {
        $primaryRecords = is_array($item['primary_issue_records'] ?? null) ? $item['primary_issue_records'] : [];
        $secondaryRecords = is_array($item['secondary_issue_records'] ?? null) ? $item['secondary_issue_records'] : [];
        $records = array_merge($primaryRecords, $secondaryRecords);
        $entries = [];

        foreach ($records as $record) {
            $issueFamily = is_string($record['issue_family'] ?? null) ? $record['issue_family'] : null;
            $reason = is_string($record['reason'] ?? null) ? $record['reason'] : null;
            $severity = is_string($record['severity'] ?? null) ? $record['severity'] : null;

            if ($issueFamily === null || $reason === null || $severity === null) {
                continue;
            }

            $controlId = $this->controlIdForIssueFamilies([$issueFamily], $standards);
            $controlConfig = is_string($controlId) && isset($standards['controls'][$controlId]) && is_array($standards['controls'][$controlId])
                ? $standards['controls'][$controlId]
                : [];
            $configuredRolloutScope = is_string($controlConfig['rollout_scope'] ?? null)
                ? $controlConfig['rollout_scope']
                : null;
            $rolloutScope = $configuredRolloutScope
                ?? (($controlId && $defaultBaselineSurface) ? 'fleet' : 'domain_only');

            $entries[] = [
                'issue_family' => $issueFamily,
                'reason' => $reason,
                'severity' => $severity,
                'control_id' => $controlId,
                'rollout_scope' => $rolloutScope,
                'baseline_surface' => $rolloutScope === 'fleet' ? $defaultBaselineSurface : null,
            ];
        }

        return $entries;
    }

    /**
     * @param  array<int, array{issue_family:string, reason:string, severity:string}>  $issues
     * @return array<int, array{issue_family:string, reason:string, severity:string}>
     */
    private function uniqueIssueRecords(array $issues): array
    {
        $unique = [];

        foreach ($issues as $issue) {
            $key = sprintf('%s|%s|%s', $issue['issue_family'], $issue['severity'], $issue['reason']);
            $unique[$key] = $issue;
        }

        return array_values($unique);
    }

    /**
     * @return array{issue_family:string, reason:string, severity:string}
     */
    private function issueRecord(string $issueFamily, string $reason, string $severity): array
    {
        return [
            'issue_family' => $issueFamily,
            'reason' => $reason,
            'severity' => $severity,
        ];
    }

    private function issueFamilyForCheckType(string $checkType): string
    {
        return match ($checkType) {
            'uptime' => 'health.uptime',
            'http' => 'health.http',
            'ssl' => 'transport.tls',
            'dns' => 'dns.health',
            'email_security' => 'email.security_baseline',
            'security_headers' => 'security.headers_baseline',
            'seo' => 'seo.fundamentals',
            'reputation' => 'reputation.health',
            'broken_links' => 'seo.broken_links',
            default => 'unclassified',
        };
    }

    /**
     * @param  array<int, string>  $issueFamilies
     * @param  array<string, mixed>  $standards
     */
    private function controlIdForIssueFamilies(array $issueFamilies, array $standards): ?string
    {
        $controls = data_get($standards, 'controls', []);

        if (! is_array($controls)) {
            return null;
        }

        foreach ($issueFamilies as $family) {
            foreach ($controls as $controlId => $controlConfig) {
                $mappedFamilies = data_get($controlConfig, 'issue_families', []);

                if (is_array($mappedFamilies) && in_array($family, $mappedFamilies, true)) {
                    return is_string($controlId) ? $controlId : null;
                }
            }
        }

        return null;
    }

    private function deriveHostProfile(Domain $domain): string
    {
        $combined = strtolower(trim(implode(' ', array_filter([
            $domain->hosting_provider,
            $this->domainPlatform($domain),
        ]))));

        if (str_contains($combined, 'vercel')) {
            return 'vercel_astro';
        }

        if (str_contains($combined, 'cloudflare')) {
            return 'cloudflare_static';
        }

        if (str_contains($combined, 'synergy wholesale') || str_contains($combined, 'litespeed') || str_contains($combined, 'ventra')) {
            return 'ventra_litespeed_wordpress';
        }

        return 'unknown_hosting';
    }

    private function derivePlatformProfile(Domain $domain, string $hostProfile): string
    {
        $platform = strtolower((string) ($this->domainPlatform($domain) ?? ''));

        if (str_contains($platform, 'astro') || $hostProfile === 'vercel_astro') {
            return 'astro_marketing_managed';
        }

        if (str_contains($platform, 'wordpress')) {
            return $hostProfile === 'ventra_litespeed_wordpress'
                ? 'wordpress_house_managed'
                : 'wordpress_legacy_unmanaged';
        }

        return 'external_static_unmanaged';
    }

    private function deriveControlProfile(?WebProperty $property, string $platformProfile): ?string
    {
        if ($platformProfile === 'astro_marketing_managed') {
            return 'astro_core';
        }

        if (in_array($platformProfile, ['wordpress_house_managed', 'wordpress_legacy_unmanaged'], true)) {
            return 'leadgen_wordpress_core';
        }

        if ($property && in_array($property->property_type, ['marketing_site', 'website', 'app'], true)) {
            return 'marketing_site_core';
        }

        return null;
    }
}
