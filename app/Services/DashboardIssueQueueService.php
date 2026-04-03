<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\DomainCheck;
use App\Models\PropertyRepository;
use App\Models\WebProperty;
use App\Models\WebsitePlatform;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class DashboardIssueQueueService
{
    public function __construct(
        private readonly DetectedIssueIdentityService $issueIdentityService,
        private readonly DetectedIssueVerificationService $issueVerificationService,
        private readonly SearchConsoleIssueEvidenceService $issueEvidenceService,
    ) {}

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
                'webProperties:id,slug,name,property_type,status,current_household_quote_url,current_household_booking_url,current_vehicle_quote_url,current_vehicle_booking_url,target_household_quote_url,target_household_booking_url,target_vehicle_quote_url,target_vehicle_booking_url,target_moveroo_subdomain_url,target_contact_us_page_url,conversion_links_scanned_at',
                'webProperties.repositories:id,web_property_id,repo_name,repo_url,local_path,framework,repo_provider,deployment_provider,deployment_project_name,deployment_project_id,is_primary,is_controller',
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
        $issueEvidence = $this->issueEvidenceService->evidenceMapForQueueItems([
            ...$mustFixDomains->all(),
            ...$shouldFixDomains->all(),
        ]);
        [$mustFixDomains, $shouldFixDomains] = $this->applyIssueVerificationQueues(
            $mustFixDomains->concat($shouldFixDomains),
            $issueEvidence
        );

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
     * @param  Collection<int, array<string, mixed>>  $items
     * @param  array<string, array<string, array<string, mixed>>>  $issueEvidence
     * @return array{0: Collection<int, array<string, mixed>>, 1: Collection<int, array<string, mixed>>}
     */
    private function applyIssueVerificationQueues(Collection $items, array $issueEvidence): array
    {
        if ($items->isEmpty()) {
            return [collect(), collect()];
        }

        $issueIds = [];

        foreach ($items as $item) {
            $domainId = (string) ($item['id'] ?? '');
            $propertySlug = is_string($item['web_property_slug'] ?? null) ? $item['web_property_slug'] : null;

            foreach ((array) ($item['issue_entries'] ?? []) as $entry) {
                $issueFamily = is_string($entry['issue_family'] ?? null) ? $entry['issue_family'] : null;

                if ($issueFamily === null) {
                    continue;
                }

                $issueIds[] = $this->issueIdentityService->makeIssueId($domainId, $propertySlug, $issueFamily);
            }
        }

        $verificationMap = $this->issueVerificationService->latestMapForIssueIds($issueIds);
        $mustFix = collect();
        $shouldFix = collect();
        $sorter = fn (array $left, array $right): int => ($right['primary_reason_count'] <=> $left['primary_reason_count'])
            ?: ($right['secondary_reason_count'] <=> $left['secondary_reason_count'])
            ?: (strtotime($right['updated_at_iso']) <=> strtotime($left['updated_at_iso']));

        foreach ($items as $item) {
            $propertySlug = is_string($item['web_property_slug'] ?? null) ? $item['web_property_slug'] : null;
            $evidenceKey = $propertySlug ?: (string) ($item['id'] ?? '');
            $filteredItem = $this->filterVerifiedIssueEntries(
                $item,
                $verificationMap,
                $issueEvidence[$evidenceKey] ?? []
            );

            if ($filteredItem === null) {
                continue;
            }

            $queueBucket = is_string($filteredItem['queue_bucket'] ?? null) ? $filteredItem['queue_bucket'] : 'must_fix';
            unset($filteredItem['queue_bucket']);

            if ($queueBucket === 'must_fix') {
                if ((int) $filteredItem['primary_reason_count'] > 0) {
                    $mustFix->push($filteredItem);

                    continue;
                }

                if ((int) $filteredItem['secondary_reason_count'] > 0) {
                    $shouldFix->push($filteredItem);
                }

                continue;
            }

            if ((int) $filteredItem['primary_reason_count'] > 0 || (int) $filteredItem['secondary_reason_count'] > 0) {
                $shouldFix->push($filteredItem);
            }
        }

        return [
            $mustFix->sort($sorter)->values(),
            $shouldFix->sort($sorter)->values(),
        ];
    }

    /**
     * @param  array<string, \App\Models\DetectedIssueVerification>  $verificationMap
     * @param  array<string, array<string, mixed>>  $propertyIssueEvidence
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>|null
     */
    private function filterVerifiedIssueEntries(array $item, array $verificationMap, array $propertyIssueEvidence): ?array
    {
        $domainId = (string) ($item['id'] ?? '');
        $propertySlug = is_string($item['web_property_slug'] ?? null) ? $item['web_property_slug'] : null;
        $visiblePrimaryRecords = [];
        $visibleSecondaryRecords = [];

        foreach ((array) ($item['primary_issue_records'] ?? []) as $record) {
            if ($this->shouldKeepVerifiedRecord($record, $item, $domainId, $propertySlug, $verificationMap, $propertyIssueEvidence)) {
                $visiblePrimaryRecords[] = $record;
            }
        }

        foreach ((array) ($item['secondary_issue_records'] ?? []) as $record) {
            if ($this->shouldKeepVerifiedRecord($record, $item, $domainId, $propertySlug, $verificationMap, $propertyIssueEvidence)) {
                $visibleSecondaryRecords[] = $record;
            }
        }

        $visibleRecordKeys = array_flip(array_map(
            fn (array $record): string => $this->issueRecordKey($record),
            array_merge($visiblePrimaryRecords, $visibleSecondaryRecords)
        ));
        $visibleEntries = array_values(array_filter(
            (array) ($item['issue_entries'] ?? []),
            fn (array $entry): bool => isset($visibleRecordKeys[$this->issueRecordKey($entry)])
        ));

        if ($visibleEntries === []) {
            return null;
        }

        if ($visiblePrimaryRecords === [] && $visibleSecondaryRecords === []) {
            return null;
        }

        $canonicalIssue = $visibleEntries[0] ?? null;

        $item['primary_issue_records'] = $visiblePrimaryRecords;
        $item['secondary_issue_records'] = $visibleSecondaryRecords;
        $item['issue_entries'] = $visibleEntries;
        $item['primary_reasons'] = array_map(
            static fn (array $record): string => $record['reason'],
            $visiblePrimaryRecords
        );
        $item['secondary_reasons'] = array_map(
            static fn (array $record): string => $record['reason'],
            $visibleSecondaryRecords
        );
        $item['primary_reason_count'] = count($visiblePrimaryRecords);
        $item['secondary_reason_count'] = count($visibleSecondaryRecords);
        $item['issue_family'] = is_array($canonicalIssue) ? $canonicalIssue['issue_family'] : null;
        $item['control_id'] = is_array($canonicalIssue) ? $canonicalIssue['control_id'] : null;
        $item['rollout_scope'] = is_array($canonicalIssue) ? $canonicalIssue['rollout_scope'] : 'domain_only';
        $item['baseline_surface'] = is_array($canonicalIssue) ? $canonicalIssue['baseline_surface'] : null;
        $item['issue_families'] = array_unique(array_map(
            static fn (array $entry): string => $entry['issue_family'],
            $visibleEntries
        ));
        $item['primary_issue_families'] = array_unique(array_map(
            static fn (array $record): string => $record['issue_family'],
            $visiblePrimaryRecords
        ));
        $item['secondary_issue_families'] = array_unique(array_map(
            static fn (array $record): string => $record['issue_family'],
            $visibleSecondaryRecords
        ));
        $item['is_standard_gap'] = ($item['rollout_scope'] ?? 'domain_only') === 'fleet';
        unset($item['baseline_captured_at']);

        return $item;
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  array<string, mixed>  $item
     * @param  array<string, \App\Models\DetectedIssueVerification>  $verificationMap
     * @param  array<string, array<string, mixed>>  $propertyIssueEvidence
     */
    private function shouldKeepVerifiedRecord(
        array $record,
        array $item,
        string $domainId,
        ?string $propertySlug,
        array $verificationMap,
        array $propertyIssueEvidence
    ): bool {
        $issueFamily = is_string($record['issue_family'] ?? null) ? $record['issue_family'] : null;

        if ($issueFamily === null) {
            return false;
        }

        $issueId = $this->issueIdentityService->makeIssueId($domainId, $propertySlug, $issueFamily);
        $verification = $verificationMap[$issueId] ?? null;
        $issueEvidenceForRecord = $this->queueIssueEvidence($item, $issueFamily, $propertyIssueEvidence[$issueFamily] ?? []);
        $issue = [
            'issue_id' => $issueId,
            'detected_at' => $this->queueIssueDetectedAt($item, $issueFamily, $issueEvidenceForRecord),
            'evidence' => $issueEvidenceForRecord,
        ];

        return ! ($verification instanceof \App\Models\DetectedIssueVerification
            && $this->issueVerificationService->isCurrentlySuppressed($issue, $verification));
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function issueRecordKey(array $record): string
    {
        $issueFamily = is_string($record['issue_family'] ?? null) ? $record['issue_family'] : null;
        $reason = is_string($record['reason'] ?? null) ? $record['reason'] : '';
        $severity = is_string($record['severity'] ?? null) ? $record['severity'] : '';

        return sprintf('%s|%s|%s', $issueFamily, $severity, $reason);
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, mixed>  $issueEvidence
     * @return array<string, string>
     */
    private function queueIssueEvidence(array $item, string $issueFamily, array $issueEvidence): array
    {
        if (array_key_exists($issueFamily, config('domain_monitor.search_console_issue_catalog', []))) {
            $evidence = [];

            if (is_string($issueEvidence['captured_at'] ?? null) && $issueEvidence['captured_at'] !== '') {
                $evidence['captured_at'] = $issueEvidence['captured_at'];
            } elseif (is_string($item['baseline_captured_at'] ?? null) && $item['baseline_captured_at'] !== '') {
                $evidence['captured_at'] = $item['baseline_captured_at'];
            }

            if (is_string($issueEvidence['api_captured_at'] ?? null) && $issueEvidence['api_captured_at'] !== '') {
                $evidence['api_captured_at'] = $issueEvidence['api_captured_at'];
            }

            return $evidence;
        }

        return is_string($item['updated_at_iso'] ?? null) && $item['updated_at_iso'] !== ''
            ? ['captured_at' => $item['updated_at_iso']]
            : [];
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, mixed>  $issueEvidence
     */
    private function queueIssueDetectedAt(array $item, string $issueFamily, array $issueEvidence): ?string
    {
        if (array_key_exists($issueFamily, config('domain_monitor.search_console_issue_catalog', []))) {
            foreach (['api_captured_at', 'captured_at'] as $key) {
                if (is_string($issueEvidence[$key] ?? null) && $issueEvidence[$key] !== '') {
                    return $issueEvidence[$key];
                }
            }
        }

        return is_string($item['updated_at_iso'] ?? null) && $item['updated_at_iso'] !== ''
            ? $item['updated_at_iso']
            : null;
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
                    'must_fix',
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
                    'should_fix',
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

        foreach ([
            'page_with_redirect_in_sitemap',
            'blocked_by_robots_in_indexing',
            'duplicate_without_user_selected_canonical',
            'alternate_with_canonical',
            'not_found_404',
            'crawled_currently_not_indexed',
            'discovered_currently_not_indexed',
        ] as $issueClass) {
            $count = $baseline->issueCount($issueClass);

            if ($count === null || $count <= 0) {
                continue;
            }

            $reason = $this->searchConsoleReason($issueClass, $count);
            $severity = $this->searchConsoleSeverity($issueClass);

            if ($severity === 'must_fix') {
                $mustFix[] = $reason;
                $mustFixIssues[] = $this->issueRecord($issueClass, $reason, $severity);

                continue;
            }

            $shouldFix[] = $reason;
            $shouldFixIssues[] = $this->issueRecord($issueClass, $reason, $severity);
        }

        return [$mustFix, $shouldFix, $mustFixIssues, $shouldFixIssues];
    }

    private function searchConsoleReason(string $issueClass, int $count): string
    {
        $label = data_get(config('domain_monitor.search_console_issue_catalog.'.$issueClass), 'label', $issueClass);

        return sprintf(
            'Search Console reports %s (%d URLs)',
            Str::of((string) $label)->lower()->toString(),
            $count
        );
    }

    private function searchConsoleSeverity(string $issueClass): string
    {
        return in_array($issueClass, ['page_with_redirect_in_sitemap', 'blocked_by_robots_in_indexing'], true)
            ? 'must_fix'
            : 'should_fix';
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
        string $queueBucket,
        array $primaryReasons,
        array $secondaryReasons = [],
        array $primaryIssueRecords = [],
        array $secondaryIssueRecords = []
    ): array {
        $baseline = $property?->latestPropertySeoBaselineRecord();

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
            'queue_bucket' => $queueBucket,
            'coverage_required' => $this->requiresControlCoverage($domain, $property),
            'coverage_status' => $coverageStatus,
            'coverage_gap' => in_array($coverageStatus, ['missing_property', 'missing_repository', 'missing_local_path'], true),
            'updated_at_human' => $domain->updated_at?->diffForHumans(),
            'updated_at_iso' => $domain->updated_at?->toIso8601String() ?? now()->toIso8601String(),
            'baseline_captured_at' => $baseline?->captured_at?->toIso8601String(),
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
        $coverageStatus = is_string($item['coverage_status'] ?? null) ? $item['coverage_status'] : 'not_required';
        $executionReadiness = $this->executionReadinessForQueue($property, $coverageStatus, $platformProfile);

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
        $item['control_state'] = $executionReadiness['control_state'];
        $item['execution_surface'] = $executionReadiness['execution_surface'];
        $item['fleet_managed'] = $executionReadiness['fleet_managed'];
        $item['controller_repo'] = $executionReadiness['controller_repo'];
        $item['controller_repo_url'] = $executionReadiness['controller_repo_url'];
        $item['controller_local_path'] = $executionReadiness['controller_local_path'];
        $item['deployment_provider'] = $executionReadiness['deployment_provider'];
        $item['deployment_project_name'] = $executionReadiness['deployment_project_name'];
        $item['deployment_project_id'] = $executionReadiness['deployment_project_id'];
        $item['conversion_links'] = $property?->conversionLinkSummary();

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

        $controllerRepository = $this->controllerRepositoryForProperty($property);

        if (! $controllerRepository instanceof PropertyRepository) {
            return 'missing_repository';
        }

        $hasControllerPath = $this->repositoryHasControllerPath($controllerRepository);

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
     * @return array{
     *   control_state:string,
     *   execution_surface:string|null,
     *   fleet_managed:bool,
     *   controller_repo:string|null,
     *   controller_repo_url:string|null,
     *   controller_local_path:string|null,
     *   deployment_provider:string|null,
     *   deployment_project_name:string|null,
     *   deployment_project_id:string|null
     * }
     */
    private function executionReadinessForQueue(?WebProperty $property, string $coverageStatus, string $platformProfile): array
    {
        $controllerRepository = $this->controllerRepositoryForProperty($property);
        $controllerRepositorySummary = $this->controllerRepositorySummary($controllerRepository);

        if ($coverageStatus === 'not_required') {
            return [
                'control_state' => 'not_applicable',
                'execution_surface' => null,
                'fleet_managed' => false,
                ...$controllerRepositorySummary,
            ];
        }

        if ($coverageStatus !== 'controlled') {
            return [
                'control_state' => 'uncontrolled',
                'execution_surface' => null,
                'fleet_managed' => false,
                ...$controllerRepositorySummary,
            ];
        }

        $executionSurface = $this->executionSurfaceForQueue($controllerRepository, $platformProfile);

        return [
            'control_state' => 'controlled',
            'execution_surface' => $executionSurface,
            'fleet_managed' => $property?->isFleetManagedExecutionSurface($executionSurface) ?? false,
            ...$controllerRepositorySummary,
        ];
    }

    private function controllerRepositoryForProperty(?WebProperty $property): ?PropertyRepository
    {
        if (! $property instanceof WebProperty) {
            return null;
        }

        $repositories = $property->relationLoaded('repositories')
            ? $property->getRelation('repositories')
            : $property->repositories()->get();
        $orderedRepositories = $repositories
            ->sortByDesc(fn (PropertyRepository $repository) => $repository->is_controller)
            ->sortByDesc(fn (PropertyRepository $repository) => $repository->is_primary)
            ->values();

        $explicitController = $orderedRepositories->first(
            fn (PropertyRepository $repository) => $repository->is_controller
        );

        if ($explicitController instanceof PropertyRepository) {
            return $explicitController;
        }

        return $orderedRepositories->first(
            fn (PropertyRepository $repository) => $this->repositoryHasControllerPath($repository)
        ) ?? $orderedRepositories->first();
    }

    /**
     * @return array{
     *   controller_repo:string|null,
     *   controller_repo_url:string|null,
     *   controller_local_path:string|null,
     *   deployment_provider:string|null,
     *   deployment_project_name:string|null,
     *   deployment_project_id:string|null
     * }
     */
    private function controllerRepositorySummary(?PropertyRepository $repository): array
    {
        return [
            'controller_repo' => $repository?->repo_name,
            'controller_repo_url' => $repository?->repo_url,
            'controller_local_path' => $repository?->local_path,
            'deployment_provider' => $repository?->deployment_provider,
            'deployment_project_name' => $repository?->deployment_project_name,
            'deployment_project_id' => $repository?->deployment_project_id,
        ];
    }

    private function repositoryHasControllerPath(PropertyRepository $repository): bool
    {
        return is_string($repository->local_path) && trim($repository->local_path) !== '';
    }

    private function executionSurfaceForQueue(?PropertyRepository $repository, string $platformProfile): string
    {
        if ($repository?->repo_name === '_wp-house') {
            return 'fleet_wordpress_controlled';
        }

        if ($platformProfile === 'astro_marketing_managed') {
            return 'astro_repo_controlled';
        }

        return 'repository_controlled';
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
