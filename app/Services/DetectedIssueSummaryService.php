<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\DomainCheck;
use App\Models\WebProperty;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;

class DetectedIssueSummaryService
{
    public function __construct(
        private readonly DashboardIssueQueueService $queueService,
        private readonly SearchConsoleIssueEvidenceService $issueEvidenceService,
        private readonly DetectedIssueIdentityService $issueIdentityService,
        private readonly DetectedIssueVerificationService $issueVerificationService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        $snapshotData = $this->buildSnapshotData();
        $queueSnapshot = $snapshotData['queue_snapshot'];
        $issues = $snapshotData['issues'];
        $mustFix = $issues->where('severity', 'must_fix')->values();
        $shouldFix = $issues->where('severity', 'should_fix')->values();

        return [
            'source_system' => 'domain-monitor-issues',
            'contract_version' => 1,
            'generated_at' => $queueSnapshot['generated_at'] ?? now()->toIso8601String(),
            'stats' => [
                'open' => $issues->count(),
                'must_fix' => $mustFix->count(),
                'should_fix' => $shouldFix->count(),
                'issue_class_counts' => $this->countByStringKey($issues, 'issue_class'),
                'control_counts' => $this->countByStringKey($issues, 'control_id'),
                'rollout_scope_counts' => $this->countByStringKey($issues, 'rollout_scope'),
            ],
            'issues' => $issues->all(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $issueId): ?array
    {
        /** @var array<int, array<string, mixed>> $issues */
        $issues = $this->buildSnapshotData(includeSuppressed: true, includeExpectedExclusions: true)['issues']->all();
        /** @var array<string, mixed>|null $issue */
        $issue = collect($issues)->firstWhere('issue_id', $issueId);

        if (is_array($issue)) {
            return $issue;
        }

        return $this->issueFromStoredVerification($issueId);
    }

    /**
     * @return array{queue_snapshot: array<string, mixed>, issues: Collection<int, array<string, mixed>>}
     */
    private function buildSnapshotData(bool $includeSuppressed = false, bool $includeExpectedExclusions = false): array
    {
        $queueSnapshot = $this->queueService->snapshot($includeExpectedExclusions);
        $queueItems = [
            ...($queueSnapshot['must_fix'] ?? []),
            ...($queueSnapshot['should_fix'] ?? []),
        ];
        $issueEvidence = $this->issueEvidenceService->evidenceMapForQueueItems($queueItems);
        $brokenLinksEvidence = $this->brokenLinksEvidenceMapForQueueItems($queueItems);
        $issues = $this->flattenIssues($queueSnapshot['must_fix'] ?? [], 'must_fix', $issueEvidence, $brokenLinksEvidence)
            ->concat($this->flattenIssues($queueSnapshot['should_fix'] ?? [], 'should_fix', $issueEvidence, $brokenLinksEvidence))
            ->pipe(function (Collection $issues) use ($includeExpectedExclusions, $includeSuppressed): Collection {
                $issueIds = $issues
                    ->pluck('issue_id')
                    ->filter(fn (mixed $issueId): bool => is_string($issueId) && $issueId !== '')
                    ->values()
                    ->all();
                $verificationMap = $this->issueVerificationService->latestMapForIssueIds($issueIds);

                return $issues
                    ->map(fn (array $issue): array => $this->issueVerificationService->annotateIssue(
                        $issue,
                        $verificationMap[$issue['issue_id']] ?? null
                    ))
                    ->reject(fn (array $issue): bool => ! $includeSuppressed
                        && (bool) data_get($issue, 'verification.is_currently_suppressed', false))
                    ->reject(fn (array $issue): bool => ! $includeExpectedExclusions
                        && is_array(data_get($issue, 'evidence.expected_exclusion')))
                    ->values();
            })
            ->values();

        return [
            'queue_snapshot' => $queueSnapshot,
            'issues' => $issues,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function issueFromStoredVerification(string $issueId): ?array
    {
        $verification = $this->issueVerificationService->latestForIssueId($issueId);

        if ($verification === null) {
            return null;
        }

        $propertyName = null;
        $conversionLinks = null;
        $canonicalOrigin = null;
        $siteIdentity = null;
        $platformProfile = null;
        $baselineSurface = null;

        if (is_string($verification->property_slug) && $verification->property_slug !== '') {
            $property = WebProperty::query()
                ->with(['propertyDomains.domain:id,domain'])
                ->select([
                    'id',
                    'slug',
                    'name',
                    'site_identity_site_name',
                    'site_identity_legal_name',
                    'property_type',
                    'production_url',
                    'canonical_origin_scheme',
                    'canonical_origin_host',
                    'canonical_origin_policy',
                    'canonical_origin_enforcement_eligible',
                    'canonical_origin_excluded_subdomains',
                    'canonical_origin_sitemap_policy_known',
                    'current_household_quote_url',
                    'current_household_booking_url',
                    'current_vehicle_quote_url',
                    'current_vehicle_booking_url',
                    'target_household_quote_url',
                    'target_household_booking_url',
                    'target_vehicle_quote_url',
                    'target_vehicle_booking_url',
                    'target_moveroo_subdomain_url',
                    'target_contact_us_page_url',
                    'target_legacy_bookings_replacement_url',
                    'target_legacy_payments_replacement_url',
                    'conversion_links_scanned_at',
                    'legacy_moveroo_endpoint_scan',
                ])
                ->where('slug', $verification->property_slug)
                ->first();

            if ($property instanceof WebProperty) {
                $propertyName = $property->name;
                $conversionLinks = $property->conversionLinkSummary();
                $canonicalOrigin = $property->canonicalOriginSummary();
                $siteIdentity = $property->siteIdentitySummary();
            }
        }

        $issueClass = is_string($verification->issue_class) ? $verification->issue_class : 'unclassified';
        $controlId = $this->controlIdForIssueClass($issueClass);
        $configuredRolloutScope = $this->configuredRolloutScopeForControl($controlId);
        $brokenLinksEvidence = $issueClass === 'seo.broken_links'
            ? $this->brokenLinksEvidenceForDomainName($verification->domain)
            : [];

        return $this->issueVerificationService->annotateIssue([
            'issue_id' => $issueId,
            'property_slug' => $verification->property_slug,
            'property_name' => $propertyName,
            'domain' => $verification->domain,
            'issue_class' => $issueClass,
            'severity' => $this->defaultSeverityForIssueClass($issueClass),
            'detector' => 'domain_monitor.priority_queue',
            'status' => 'open',
            'detected_at' => $verification->verified_at->toIso8601String(),
            'rollout_scope' => $configuredRolloutScope ?? 'domain_only',
            'control_id' => $controlId,
            'platform_profile' => $platformProfile,
            'host_profile' => null,
            'control_profile' => null,
            'control_state' => null,
            'execution_surface' => null,
            'fleet_managed' => false,
            'controller_repo' => null,
            'controller_repo_url' => null,
            'conversion_links' => $conversionLinks,
            'canonical_origin' => $canonicalOrigin,
            'site_identity' => $siteIdentity,
            'evidence' => [
                'primary_reasons' => [],
                'secondary_reasons' => [],
                'related_issue_classes' => [$issueClass],
                'coverage_required' => false,
                'coverage_status' => null,
                'coverage_gap' => false,
                'property_match_confidence' => $propertyName !== null ? 'high' : 'none',
                'baseline_surface' => $configuredRolloutScope === 'domain_only' ? null : $baselineSurface,
                'source_domain_id' => null,
                ...$brokenLinksEvidence,
            ],
        ], $verification);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<string, array<string, array<string, mixed>>>  $issueEvidence
     * @param  array<string, array<string, mixed>>  $brokenLinksEvidence
     * @return Collection<int, array<string, mixed>>
     */
    private function flattenIssues(array $items, string $severity, array $issueEvidence, array $brokenLinksEvidence): Collection
    {
        return collect($items)
            ->flatMap(fn (array $item): array => $this->makeIssues($item, $severity, $issueEvidence, $brokenLinksEvidence))
            ->values();
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, array<string, array<string, mixed>>>  $issueEvidence
     * @param  array<string, array<string, mixed>>  $brokenLinksEvidence
     * @return array<int, array<string, mixed>>
     */
    private function makeIssues(array $item, string $severity, array $issueEvidence, array $brokenLinksEvidence): array
    {
        $domainId = (string) ($item['id'] ?? '');
        $domain = is_string($item['domain'] ?? null) ? $item['domain'] : null;
        $propertySlug = is_string($item['web_property_slug'] ?? null) ? $item['web_property_slug'] : null;
        $evidenceKey = $propertySlug ?: $domainId;
        $queueIssueEntries = $this->normalizedIssueEntries($item, $severity);
        $issueEntries = array_merge(
            $queueIssueEntries,
            $this->supplementalIssueEntries($item, $issueEvidence[$evidenceKey] ?? [], $queueIssueEntries)
        );
        $relatedIssueClasses = array_values(array_unique(array_map(
            static fn (array $entry): string => $entry['issue_class'],
            $issueEntries
        )));
        sort($relatedIssueClasses);
        $issues = [];

        foreach ($issueEntries as $issueEntry) {
            $issueClass = $issueEntry['issue_class'];
            $issueId = sprintf(
                '%s',
                $this->issueIdentityService->makeIssueId(
                    $domainId,
                    $propertySlug,
                    $issueClass
                )
            );

            $issues[] = [
                'issue_id' => $issueId,
                'property_slug' => $propertySlug,
                'property_name' => is_string($item['web_property_name'] ?? null) ? $item['web_property_name'] : null,
                'domain' => $domain,
                'issue_class' => $issueClass,
                'severity' => $issueEntry['severity'],
                'detector' => 'domain_monitor.priority_queue',
                'status' => 'open',
                'detected_at' => $this->issueDetectedAt($issueClass, $item, $issueEvidence[$evidenceKey][$issueClass] ?? []),
                'rollout_scope' => $issueEntry['rollout_scope'],
                'control_id' => $issueEntry['control_id'],
                'platform_profile' => is_string($item['platform_profile'] ?? null) ? $item['platform_profile'] : null,
                'host_profile' => is_string($item['host_profile'] ?? null) ? $item['host_profile'] : null,
                'control_profile' => is_string($item['control_profile'] ?? null) ? $item['control_profile'] : null,
                'control_state' => is_string($item['control_state'] ?? null) ? $item['control_state'] : null,
                'execution_surface' => is_string($item['execution_surface'] ?? null) ? $item['execution_surface'] : null,
                'fleet_managed' => (bool) ($item['fleet_managed'] ?? false),
                'controller_repo' => is_string($item['controller_repo'] ?? null) ? $item['controller_repo'] : null,
                'controller_repo_url' => is_string($item['controller_repo_url'] ?? null) ? $item['controller_repo_url'] : null,
                'conversion_links' => is_array($item['conversion_links'] ?? null) ? $item['conversion_links'] : null,
                'canonical_origin' => is_array($item['canonical_origin'] ?? null) ? $item['canonical_origin'] : null,
                'site_identity' => is_array($item['site_identity'] ?? null) ? $item['site_identity'] : null,
                'evidence' => [
                    'primary_reasons' => [$issueEntry['reason']],
                    'secondary_reasons' => array_values(array_filter(
                        array_map(
                            static fn (array $entry): ?string => $entry['issue_class'] !== $issueClass ? $entry['reason'] : null,
                            $issueEntries
                        ),
                        'is_string'
                    )),
                    'related_issue_classes' => $relatedIssueClasses,
                    'coverage_required' => (bool) ($item['coverage_required'] ?? false),
                    'coverage_status' => is_string($item['coverage_status'] ?? null) ? $item['coverage_status'] : null,
                    'coverage_gap' => (bool) ($item['coverage_gap'] ?? false),
                    'property_match_confidence' => is_string($item['property_match_confidence'] ?? null) ? $item['property_match_confidence'] : null,
                    'baseline_surface' => $issueEntry['baseline_surface'],
                    'source_domain_id' => $domainId,
                    ...($brokenLinksEvidence[$issueId] ?? []),
                    ...($issueEvidence[$evidenceKey][$issueClass] ?? []),
                ],
            ];
        }

        return $issues;
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, array<string, mixed>>  $propertyEvidence
     * @param  array<int, array{issue_class:string, reason:string, severity:string, control_id:?string, rollout_scope:string, baseline_surface:?string}>  $existingIssueEntries
     * @return array<int, array{issue_class:string, reason:string, severity:string, control_id:?string, rollout_scope:string, baseline_surface:?string}>
     */
    private function supplementalIssueEntries(array $item, array $propertyEvidence, array $existingIssueEntries): array
    {
        $existingIssueClasses = array_values(array_unique(array_map(
            static fn (array $entry): string => $entry['issue_class'],
            $existingIssueEntries
        )));
        $entries = [];

        foreach ($propertyEvidence as $issueClass => $evidence) {
            if (in_array($issueClass, $existingIssueClasses, true)) {
                continue;
            }

            if (! $this->shouldEmitSupplementalIssue($issueClass, $evidence)) {
                continue;
            }

            $controlId = $this->controlIdForIssueClass($issueClass);
            $baselineSurface = $this->defaultBaselineSurfaceForPlatformProfile(
                is_string($item['platform_profile'] ?? null) ? $item['platform_profile'] : null
            );
            $configuredRolloutScope = $this->configuredRolloutScopeForControl($controlId);

            $entries[] = [
                'issue_class' => $issueClass,
                'reason' => $this->supplementalIssueReason($issueClass, $evidence),
                'severity' => $this->defaultSeverityForIssueClass($issueClass),
                'control_id' => $controlId,
                'rollout_scope' => $configuredRolloutScope
                    ?? (($controlId && $baselineSurface) ? 'fleet' : 'domain_only'),
                'baseline_surface' => ($configuredRolloutScope === 'domain_only' || ! $controlId || ! $baselineSurface)
                    ? null
                    : $baselineSurface,
            ];
        }

        return $entries;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<int, array{issue_class:string, reason:string, severity:string, control_id:?string, rollout_scope:string, baseline_surface:?string}>
     */
    private function normalizedIssueEntries(array $item, string $fallbackSeverity): array
    {
        $issueEntries = is_array($item['issue_entries'] ?? null) ? $item['issue_entries'] : [];

        if ($issueEntries !== []) {
            return array_values(array_filter(array_map(function (mixed $entry): ?array {
                if (! is_array($entry)) {
                    return null;
                }

                $issueClass = is_string($entry['issue_family'] ?? null) ? $entry['issue_family'] : null;
                $severity = is_string($entry['severity'] ?? null) ? $entry['severity'] : null;
                $rolloutScope = is_string($entry['rollout_scope'] ?? null) ? $entry['rollout_scope'] : null;

                if ($issueClass === null || $severity === null || $rolloutScope === null) {
                    return null;
                }

                return [
                    'issue_class' => $issueClass,
                    'reason' => (string) $entry['reason'],
                    'severity' => $severity,
                    'control_id' => is_string($entry['control_id'] ?? null) ? $entry['control_id'] : null,
                    'rollout_scope' => $rolloutScope,
                    'baseline_surface' => is_string($entry['baseline_surface'] ?? null) ? $entry['baseline_surface'] : null,
                ];
            }, $issueEntries)));
        }

        $fallbackIssueClass = is_string($item['issue_family'] ?? null) && $item['issue_family'] !== ''
            ? $item['issue_family']
            : 'unclassified';

        return [[
            'issue_class' => $fallbackIssueClass,
            'reason' => is_string($item['primary_reasons'][0] ?? null) ? $item['primary_reasons'][0] : $fallbackIssueClass,
            'severity' => $fallbackSeverity,
            'control_id' => is_string($item['control_id'] ?? null) ? $item['control_id'] : null,
            'rollout_scope' => is_string($item['rollout_scope'] ?? null) ? $item['rollout_scope'] : 'domain_only',
            'baseline_surface' => is_string($item['baseline_surface'] ?? null) ? $item['baseline_surface'] : null,
        ]];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $issues
     * @return array<string, int>
     */
    private function countByStringKey(Collection $issues, string $key): array
    {
        $counts = [];

        foreach ($issues as $issue) {
            $value = $issue[$key] ?? null;

            if (! is_string($value) || $value === '') {
                continue;
            }

            $counts[$value] = ($counts[$value] ?? 0) + 1;
        }

        ksort($counts);

        return $counts;
    }

    /**
     * @param  array<string, mixed>  $evidence
     */
    private function shouldEmitSupplementalIssue(string $issueClass, array $evidence): bool
    {
        if (! is_array(config('domain_monitor.search_console_issue_catalog.'.$issueClass))) {
            return false;
        }

        if (is_numeric($evidence['affected_url_count'] ?? null) && (int) $evidence['affected_url_count'] > 0) {
            return true;
        }

        foreach (['affected_urls', 'examples', 'url_inspection', 'sitemaps', 'referring_urls', 'canonical_state', 'search_analytics'] as $key) {
            if (! empty($evidence[$key])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $evidence
     */
    private function supplementalIssueReason(string $issueClass, array $evidence): string
    {
        $label = data_get(config('domain_monitor.search_console_issue_catalog.'.$issueClass), 'label', $issueClass);
        $count = is_numeric($evidence['active_affected_url_count'] ?? null)
            ? (int) $evidence['active_affected_url_count']
            : (is_numeric($evidence['affected_url_count'] ?? null) ? (int) $evidence['affected_url_count'] : null);

        if ($count !== null && $count > 0) {
            return sprintf('Search Console reports %s (%d URLs)', strtolower((string) $label), $count);
        }

        return sprintf('Search Console reports %s', strtolower((string) $label));
    }

    private function defaultSeverityForIssueClass(string $issueClass): string
    {
        return in_array($issueClass, ['page_with_redirect_in_sitemap', 'blocked_by_robots_in_indexing'], true)
            ? 'must_fix'
            : 'should_fix';
    }

    private function controlIdForIssueClass(string $issueClass): ?string
    {
        $controls = data_get(config('domain_monitor.priority_queue_standards'), 'controls', []);

        if (! is_array($controls)) {
            return null;
        }

        foreach ($controls as $controlId => $controlConfig) {
            $mappedFamilies = data_get($controlConfig, 'issue_families', []);

            if (is_array($mappedFamilies) && in_array($issueClass, $mappedFamilies, true)) {
                return is_string($controlId) ? $controlId : null;
            }
        }

        return null;
    }

    private function configuredRolloutScopeForControl(?string $controlId): ?string
    {
        return is_string($controlId)
            ? data_get(config('domain_monitor.priority_queue_standards'), 'controls.'.$controlId.'.rollout_scope')
            : null;
    }

    private function defaultBaselineSurfaceForPlatformProfile(?string $platformProfile): ?string
    {
        return is_string($platformProfile)
            ? data_get(config('domain_monitor.priority_queue_standards'), 'platform_profiles.'.$platformProfile.'.baseline_surface')
            : null;
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, mixed>  $issueEvidence
     */
    private function issueDetectedAt(string $issueClass, array $item, array $issueEvidence): string
    {
        if (array_key_exists($issueClass, config('domain_monitor.search_console_issue_catalog', []))) {
            foreach (['api_captured_at', 'captured_at'] as $key) {
                if (is_string($issueEvidence[$key] ?? null) && $issueEvidence[$key] !== '') {
                    return $issueEvidence[$key];
                }
            }
        }

        if (is_string($item['updated_at_iso'] ?? null) && $item['updated_at_iso'] !== '') {
            return $item['updated_at_iso'];
        }

        return now()->toIso8601String();
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<string, array<string, mixed>>
     */
    private function brokenLinksEvidenceMapForQueueItems(array $items): array
    {
        $issueToDomainMap = [];

        foreach ($items as $item) {
            $domain = is_string($item['domain'] ?? null) ? $item['domain'] : null;
            $domainId = (string) ($item['id'] ?? '');
            $propertySlug = is_string($item['web_property_slug'] ?? null) ? $item['web_property_slug'] : null;

            if ($domain === null || $domainId === '') {
                continue;
            }

            foreach ((array) ($item['issue_entries'] ?? []) as $entry) {
                $issueClass = is_string($entry['issue_family'] ?? null) ? $entry['issue_family'] : null;

                if ($issueClass !== 'seo.broken_links') {
                    continue;
                }

                $issueId = $this->issueIdentityService->makeIssueId($domainId, $propertySlug, $issueClass);
                $issueToDomainMap[$issueId] = $domain;
            }
        }

        if ($issueToDomainMap === []) {
            return [];
        }

        $evidenceByDomain = $this->brokenLinksEvidenceByDomainName(array_values(array_unique(array_values($issueToDomainMap))));
        $evidenceByIssueId = [];

        foreach ($issueToDomainMap as $issueId => $domainName) {
            if (isset($evidenceByDomain[$domainName])) {
                $evidenceByIssueId[$issueId] = $evidenceByDomain[$domainName];
            }
        }

        return $evidenceByIssueId;
    }

    /**
     * @return array<string, mixed>
     */
    private function brokenLinksEvidenceForDomainName(?string $domainName): array
    {
        if (! is_string($domainName) || $domainName === '') {
            return [];
        }

        return $this->brokenLinksEvidenceByDomainName([$domainName])[$domainName] ?? [];
    }

    /**
     * @param  array<int, string>  $domainNames
     * @return array<string, array<string, mixed>>
     */
    private function brokenLinksEvidenceByDomainName(array $domainNames): array
    {
        if ($domainNames === []) {
            return [];
        }

        $domains = Domain::query()
            ->select(['id', 'domain'])
            ->whereIn('domain', $domainNames)
            ->get();

        if ($domains->isEmpty()) {
            return [];
        }

        /** @var array<string, string> $domainNamesById */
        $domainNamesById = $domains->pluck('domain', 'id')->all();
        $checks = $this->latestBrokenLinksChecksForDomainIds(array_keys($domainNamesById));
        $evidence = [];

        foreach ($checks as $domainId => $check) {
            $domainName = $domainNamesById[$domainId] ?? null;

            if (! is_string($domainName)) {
                continue;
            }

            $normalized = $this->normalizeBrokenLinksEvidence($check->payload);

            if ($normalized !== null) {
                $evidence[$domainName] = $normalized;
            }
        }

        return $evidence;
    }

    /**
     * @param  array<int, string>  $domainIds
     * @return Collection<string, DomainCheck>
     */
    private function latestBrokenLinksChecksForDomainIds(array $domainIds): Collection
    {
        if ($domainIds === []) {
            return collect();
        }

        $latestCheckTimestamps = DomainCheck::query()
            ->selectRaw('domain_id, MAX(created_at) as latest_created_at')
            ->where('check_type', 'broken_links')
            ->whereIn('domain_id', $domainIds)
            ->groupBy('domain_id');

        /** @var Collection<string, DomainCheck> $checks */
        $checks = DomainCheck::query()
            ->from('domain_checks as checks')
            ->joinSub($latestCheckTimestamps, 'latest_checks', function (JoinClause $join): void {
                $join->on('checks.domain_id', '=', 'latest_checks.domain_id')
                    ->on('checks.created_at', '=', 'latest_checks.latest_created_at');
            })
            ->where('checks.check_type', 'broken_links')
            ->get(['checks.id', 'checks.domain_id', 'checks.payload', 'checks.created_at'])
            ->unique('domain_id')
            ->keyBy('domain_id');

        return $checks;
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @return array<string, mixed>|null
     */
    private function normalizeBrokenLinksEvidence(?array $payload): ?array
    {
        if (! is_array($payload)) {
            return null;
        }

        $payloadBrokenLinks = $payload['broken_links'] ?? null;

        if (! is_array($payloadBrokenLinks)) {
            return null;
        }

        $brokenLinks = collect($payloadBrokenLinks)
            ->filter(fn (mixed $entry): bool => is_array($entry))
            ->map(function (array $entry): ?array {
                $url = $this->sanitizeEvidenceUrl($entry['url'] ?? null);
                $status = is_numeric($entry['status'] ?? null) ? (int) $entry['status'] : null;
                $foundOn = $this->sanitizeEvidenceUrl($entry['found_on'] ?? null);

                if ($url === null || $status === null || $foundOn === null) {
                    return null;
                }

                return [
                    'url' => $url,
                    'status' => $status,
                    'found_on' => $foundOn,
                ];
            })
            ->filter()
            ->values()
            ->all();

        if ($brokenLinks === []) {
            return null;
        }

        return [
            'broken_links_count' => count($brokenLinks),
            'pages_scanned' => is_numeric($payload['pages_scanned'] ?? null)
                ? (int) $payload['pages_scanned']
                : null,
            'broken_links' => $brokenLinks,
        ];
    }

    private function sanitizeEvidenceUrl(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        $parts = parse_url($value);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return preg_replace('/[?#].*$/', '', $value);
        }

        $sanitized = $parts['scheme'].'://'.$parts['host'];

        if (isset($parts['port'])) {
            $sanitized .= ':'.$parts['port'];
        }

        $sanitized .= $parts['path'] ?? '';

        return $sanitized;
    }
}
