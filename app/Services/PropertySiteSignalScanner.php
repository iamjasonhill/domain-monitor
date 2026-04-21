<?php

namespace App\Services;

use App\Models\DomainCheck;
use App\Models\WebProperty;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class PropertySiteSignalScanner
{
    public function __construct(
        private readonly UptimeHealthCheck $uptimeHealthCheck,
        private readonly HttpHealthCheck $httpHealthCheck,
        private readonly SslHealthCheck $sslHealthCheck,
        private readonly PropertyConversionLinkScanner $conversionLinkScanner,
    ) {}

    /**
     * @return array{
     *   status: 'ok'|'fail',
     *   verdict: string,
     *   summary: string,
     *   evidence: array<string, mixed>
     * }
     */
    public function auditUptime(WebProperty $property, int $timeout = 10): array
    {
        $domain = $property->primaryDomainName();

        if (! is_string($domain) || trim($domain) === '') {
            return [
                'status' => 'fail',
                'verdict' => 'missing_primary_domain',
                'summary' => 'Property does not have a primary domain for uptime monitoring.',
                'evidence' => [
                    'verdict' => 'missing_primary_domain',
                    'property_slug' => $property->slug,
                ],
            ];
        }

        $result = $this->uptimeHealthCheck->check($domain, max(1, min($timeout, 10)));

        if ($result['is_valid']) {
            return [
                'status' => 'ok',
                'verdict' => 'uptime_ok',
                'summary' => 'Root uptime probe succeeded.',
                'evidence' => [
                    'verdict' => 'uptime_ok',
                    'domain' => $domain,
                    'details' => $result['payload'],
                ],
            ];
        }

        return [
            'status' => 'fail',
            'verdict' => 'uptime_failed',
            'summary' => 'Root uptime probe failed for the primary domain.',
            'evidence' => [
                'verdict' => 'uptime_failed',
                'domain' => $domain,
                'details' => $result['payload'],
                'error_message' => $result['error_message'],
                'status_code' => $result['status_code'],
            ],
        ];
    }

    /**
     * @return array{
     *   status: 'ok'|'fail',
     *   verdict: string,
     *   summary: string,
     *   evidence: array<string, mixed>
     * }
     */
    public function auditHttpResponse(WebProperty $property, int $timeout = 10): array
    {
        $domain = $property->primaryDomainName();

        if (! is_string($domain) || trim($domain) === '') {
            return [
                'status' => 'fail',
                'verdict' => 'missing_primary_domain',
                'summary' => 'Property does not have a primary domain for HTTP monitoring.',
                'evidence' => [
                    'verdict' => 'missing_primary_domain',
                    'property_slug' => $property->slug,
                ],
            ];
        }

        $result = $this->httpHealthCheck->check($domain, $timeout);
        $statusCode = $result['status_code'];
        $isHealthy = $result['is_up']
            && is_int($statusCode)
            && $statusCode >= 200
            && $statusCode < 400;

        if ($isHealthy) {
            return [
                'status' => 'ok',
                'verdict' => 'http_ok',
                'summary' => 'Root HTTP response succeeded for the primary domain.',
                'evidence' => [
                    'verdict' => 'http_ok',
                    'domain' => $domain,
                    'details' => $result['payload'],
                    'status_code' => $statusCode,
                ],
            ];
        }

        $verdict = is_int($statusCode) && $statusCode >= 400 && $statusCode < 500
            ? 'http_client_error'
            : 'http_unavailable';

        return [
            'status' => 'fail',
            'verdict' => $verdict,
            'summary' => 'Root HTTP response is failing or returning an unexpected status for the primary domain.',
            'evidence' => [
                'verdict' => $verdict,
                'domain' => $domain,
                'details' => $result['payload'],
                'error_message' => $result['error_message'],
                'status_code' => $statusCode,
            ],
        ];
    }

    /**
     * @return array{
     *   status: 'ok'|'fail',
     *   verdict: string,
     *   summary: string,
     *   evidence: array<string, mixed>
     * }
     */
    public function auditSsl(WebProperty $property, int $timeout = 10): array
    {
        $domain = $property->primaryDomainName();

        if (! is_string($domain) || trim($domain) === '') {
            return [
                'status' => 'fail',
                'verdict' => 'missing_primary_domain',
                'summary' => 'Property does not have a primary domain for SSL monitoring.',
                'evidence' => [
                    'verdict' => 'missing_primary_domain',
                    'property_slug' => $property->slug,
                ],
            ];
        }

        $result = $this->sslHealthCheck->check($domain, $timeout);
        $daysUntilExpiry = $result['days_until_expiry'];
        $isExpiringSoon = is_int($daysUntilExpiry) && $daysUntilExpiry <= 7;

        if ($result['is_valid'] && ! $isExpiringSoon) {
            return [
                'status' => 'ok',
                'verdict' => 'ssl_ok',
                'summary' => 'SSL certificate looks healthy for the primary domain.',
                'evidence' => [
                    'verdict' => 'ssl_ok',
                    'domain' => $domain,
                    'details' => $result['payload'],
                    'days_until_expiry' => $daysUntilExpiry,
                ],
            ];
        }

        $verdict = $isExpiringSoon ? 'ssl_expiring_soon' : 'ssl_invalid';
        $summary = $isExpiringSoon
            ? 'SSL certificate is expiring soon on the primary domain.'
            : 'SSL certificate is invalid or unavailable on the primary domain.';

        return [
            'status' => 'fail',
            'verdict' => $verdict,
            'summary' => $summary,
            'evidence' => [
                'verdict' => $verdict,
                'domain' => $domain,
                'details' => $result['payload'],
                'error_message' => $result['error_message'],
                'days_until_expiry' => $daysUntilExpiry,
            ],
        ];
    }

    /**
     * @return array{
     *   status: 'ok'|'fail',
     *   verdict: string,
     *   summary: string,
     *   evidence: array<string, mixed>
     * }
     */
    public function auditIndexability(WebProperty $property, int $timeout = 10): array
    {
        $probe = $this->firstSuccessfulProbe($this->candidateUrlsForProperty($property), $timeout);

        if ($probe === null) {
            return [
                'status' => 'fail',
                'verdict' => 'fetch_failed',
                'summary' => 'Could not fetch any live property URL to verify homepage indexability.',
                'evidence' => [
                    'verdict' => 'fetch_failed',
                    'urls' => $this->candidateUrlsForProperty($property)->all(),
                ],
            ];
        }

        $expectedOrigin = $this->expectedOrigin($property);
        $canonicalUrl = $this->extractCanonicalUrl($probe['html']);
        $noindex = $this->hasNoindexDirective($probe['html'], $probe['headers']);
        $robots = $this->fetchRobotsFile($probe['final_url'], $timeout);
        $sitemapReference = $robots['sitemap_url'] ?? null;
        $problems = [];

        if ($canonicalUrl === null) {
            $problems[] = 'missing_canonical';
        } elseif ($expectedOrigin !== null && ! $this->canonicalMatchesExpectedOrigin($canonicalUrl, $expectedOrigin, $probe['final_url'])) {
            $problems[] = 'wrong_canonical';
        }

        if ($noindex) {
            $problems[] = 'homepage_noindex';
        }

        if ($expectedOrigin !== null && ! $this->finalUrlMatchesExpectedOrigin($probe['final_url'], $expectedOrigin)) {
            $problems[] = 'preferred_host_mismatch';
        }

        if (! is_string($sitemapReference) || trim($sitemapReference) === '') {
            $problems[] = 'sitemap_not_referenced';
        }

        if ($problems === []) {
            return [
                'status' => 'ok',
                'verdict' => 'indexable',
                'summary' => 'Homepage canonical, robots, and sitemap signals look indexable.',
                'evidence' => [
                    'verdict' => 'indexable',
                    'best_url' => $probe['url'],
                    'final_url' => $probe['final_url'],
                    'canonical_url' => $canonicalUrl,
                    'expected_origin' => $expectedOrigin,
                    'has_noindex' => false,
                    'robots_url' => $robots['url'],
                    'robots_status' => $robots['status'],
                    'sitemap_url' => $sitemapReference,
                    'problems' => [],
                ],
            ];
        }

        $verdict = $problems[0];

        return [
            'status' => 'fail',
            'verdict' => $verdict,
            'summary' => $this->summaryForIndexabilityProblems($problems, $expectedOrigin),
            'evidence' => [
                'verdict' => $verdict,
                'best_url' => $probe['url'],
                'final_url' => $probe['final_url'],
                'canonical_url' => $canonicalUrl,
                'expected_origin' => $expectedOrigin,
                'has_noindex' => $noindex,
                'robots_url' => $robots['url'],
                'robots_status' => $robots['status'],
                'sitemap_url' => $sitemapReference,
                'problems' => $problems,
            ],
        ];
    }

    /**
     * @return array{
     *   status: 'ok'|'fail',
     *   verdict: string,
     *   summary: string,
     *   evidence: array<string, mixed>
     * }
     */
    public function auditStructuredData(WebProperty $property, int $timeout = 10): array
    {
        $probe = $this->firstSuccessfulProbe($this->candidateUrlsForProperty($property), $timeout);

        if ($probe === null) {
            return [
                'status' => 'fail',
                'verdict' => 'fetch_failed',
                'summary' => 'Could not fetch any live property URL to verify structured data.',
                'evidence' => [
                    'verdict' => 'fetch_failed',
                    'urls' => $this->candidateUrlsForProperty($property)->all(),
                ],
            ];
        }

        $scripts = $this->structuredDataScripts($probe['html']);
        $validScriptCount = collect($scripts)
            ->filter(fn (array $script): bool => $script['valid'])
            ->count();

        if ($scripts === []) {
            return [
                'status' => 'fail',
                'verdict' => 'missing_structured_data',
                'summary' => 'Homepage does not expose any JSON-LD structured data.',
                'evidence' => [
                    'verdict' => 'missing_structured_data',
                    'best_url' => $probe['url'],
                    'final_url' => $probe['final_url'],
                    'script_count' => 0,
                    'valid_script_count' => 0,
                ],
            ];
        }

        if ($validScriptCount === 0) {
            return [
                'status' => 'fail',
                'verdict' => 'invalid_structured_data',
                'summary' => 'Homepage exposes JSON-LD script tags, but none contain valid JSON.',
                'evidence' => [
                    'verdict' => 'invalid_structured_data',
                    'best_url' => $probe['url'],
                    'final_url' => $probe['final_url'],
                    'script_count' => count($scripts),
                    'valid_script_count' => 0,
                    'scripts' => $scripts,
                ],
            ];
        }

        return [
            'status' => 'ok',
            'verdict' => 'structured_data_present',
            'summary' => 'Homepage exposes valid JSON-LD structured data.',
            'evidence' => [
                'verdict' => 'structured_data_present',
                'best_url' => $probe['url'],
                'final_url' => $probe['final_url'],
                'script_count' => count($scripts),
                'valid_script_count' => $validScriptCount,
                'scripts' => $scripts,
            ],
        ];
    }

    /**
     * @return array{
     *   status: 'ok'|'fail',
     *   verdict: string,
     *   summary: string,
     *   evidence: array<string, mixed>
     * }
     */
    public function auditAgentReadiness(WebProperty $property, int $timeout = 10): array
    {
        $baseUrl = $this->baseUrlForProperty($property);
        $robots = $this->fetchTextFile($baseUrl.'/robots.txt', $timeout);
        $sitemap = $this->fetchSitemapFile($baseUrl, $timeout);
        $llms = $this->fetchTextFile($baseUrl.'/llms.txt', $timeout);
        $missing = collect([
            $robots['ok'] ? null : 'robots.txt',
            $sitemap['ok'] ? null : 'sitemap',
            $llms['ok'] ? null : 'llms.txt',
        ])->filter()->values();

        if ($missing->isEmpty()) {
            return [
                'status' => 'ok',
                'verdict' => 'agent_readiness_present',
                'summary' => 'robots.txt, sitemap, and llms.txt are all reachable.',
                'evidence' => [
                    'verdict' => 'agent_readiness_present',
                    'base_url' => $baseUrl,
                    'robots' => Arr::except($robots, ['body']),
                    'sitemap' => Arr::except($sitemap, ['body']),
                    'llms' => Arr::except($llms, ['body']),
                    'missing_files' => [],
                ],
            ];
        }

        return [
            'status' => 'fail',
            'verdict' => 'agent_readiness_missing',
            'summary' => sprintf(
                'Agent-readiness files are incomplete: missing %s.',
                $missing->implode(', ')
            ),
            'evidence' => [
                'verdict' => 'agent_readiness_missing',
                'base_url' => $baseUrl,
                'robots' => Arr::except($robots, ['body']),
                'sitemap' => Arr::except($sitemap, ['body']),
                'llms' => Arr::except($llms, ['body']),
                'missing_files' => $missing->all(),
            ],
        ];
    }

    /**
     * @return array{
     *   status: 'ok'|'fail',
     *   verdict: string,
     *   summary: string,
     *   evidence: array<string, mixed>
     * }
     */
    public function auditQuoteHandoffIntegrity(WebProperty $property): array
    {
        try {
            $scan = $this->conversionLinkScanner->scanForProperty($property);
        } catch (\Throwable $exception) {
            return [
                'status' => 'fail',
                'verdict' => 'scan_failed',
                'summary' => 'Could not scan the live homepage to verify quote handoff links.',
                'evidence' => [
                    'verdict' => 'scan_failed',
                    'property_slug' => $property->slug,
                    'error_message' => $exception->getMessage(),
                ],
            ];
        }

        $targets = $property->conversionLinkSummary()['target'];
        $current = [
            'household_quote' => $scan['current_household_quote_url'] ?? null,
            'household_booking' => $scan['current_household_booking_url'] ?? null,
            'vehicle_quote' => $scan['current_vehicle_quote_url'] ?? null,
            'vehicle_booking' => $scan['current_vehicle_booking_url'] ?? null,
        ];
        $mismatches = [];

        foreach (['household_quote', 'household_booking', 'vehicle_quote', 'vehicle_booking'] as $slot) {
            $expectedUrl = $this->normalizedComparableUrl($targets[$slot] ?? null);

            if ($expectedUrl === null) {
                continue;
            }

            $detectedUrl = $this->normalizedComparableUrl($current[$slot] ?? null);

            if ($detectedUrl === $expectedUrl) {
                continue;
            }

            $mismatches[] = [
                'slot' => $slot,
                'expected_url' => $expectedUrl,
                'detected_url' => $detectedUrl,
                'expected_host' => $this->hostForComparableUrl($expectedUrl),
                'detected_host' => $this->hostForComparableUrl($detectedUrl),
            ];
        }

        if ($mismatches === []) {
            return [
                'status' => 'ok',
                'verdict' => 'handoff_ok',
                'summary' => 'Live quote and booking handoff links match the configured targets.',
                'evidence' => [
                    'verdict' => 'handoff_ok',
                    'current' => $current,
                    'expected' => $targets,
                    'mismatches' => [],
                ],
            ];
        }

        $verdict = collect($mismatches)
            ->contains(fn (array $mismatch): bool => $mismatch['detected_url'] === null)
                ? 'missing_handoff_link'
                : 'wrong_handoff_target';

        return [
            'status' => 'fail',
            'verdict' => $verdict,
            'summary' => sprintf(
                '%d live quote or booking handoff link(s) do not match the configured targets.',
                count($mismatches)
            ),
            'evidence' => [
                'verdict' => $verdict,
                'current' => $current,
                'expected' => $targets,
                'mismatches' => $mismatches,
            ],
        ];
    }

    /**
     * @return array{
     *   status: 'ok'|'fail',
     *   verdict: string,
     *   summary: string,
     *   evidence: array<string, mixed>
     * }
     */
    public function auditBrokenLinks(WebProperty $property, DomainHealthCheckRunner $healthCheckRunner): array
    {
        $primaryDomain = $property->primaryDomainModel();

        if ($primaryDomain === null) {
            return [
                'status' => 'fail',
                'verdict' => 'missing_primary_domain',
                'summary' => 'Property does not have a primary domain for broken-link verification.',
                'evidence' => [
                    'verdict' => 'missing_primary_domain',
                    'property_slug' => $property->slug,
                ],
            ];
        }

        $refresh = $healthCheckRunner->run($primaryDomain, 'broken_links');
        $latestCheck = $primaryDomain->checks()
            ->where('check_type', 'broken_links')
            ->latest('finished_at')
            ->latest('created_at')
            ->first();

        if (! $latestCheck instanceof DomainCheck) {
            return [
                'status' => 'fail',
                'verdict' => 'broken_links_refresh_failed',
                'summary' => 'Broken-link crawl did not produce a persisted result.',
                'evidence' => [
                    'verdict' => 'broken_links_refresh_failed',
                    'refresh_status' => $refresh['status'],
                    'refresh_reason' => $refresh['reason'] ?? null,
                ],
            ];
        }

        $payload = is_array($latestCheck->payload) ? $latestCheck->payload : [];
        $brokenLinks = is_array($payload['broken_links'] ?? null) ? $payload['broken_links'] : [];
        $brokenLinksCount = is_numeric($payload['broken_links_count'] ?? null)
            ? (int) $payload['broken_links_count']
            : count($brokenLinks);
        $pagesScanned = is_numeric($payload['pages_scanned'] ?? null)
            ? (int) $payload['pages_scanned']
            : 0;

        if ($latestCheck->status === 'unknown' || $pagesScanned === 0) {
            return [
                'status' => 'fail',
                'verdict' => 'crawl_unverified',
                'summary' => 'Broken-link crawl could not verify any live pages.',
                'evidence' => [
                    'verdict' => 'crawl_unverified',
                    'refresh_status' => $refresh['status'],
                    'refresh_reason' => $refresh['reason'] ?? null,
                    'pages_scanned' => $pagesScanned,
                    'error_message' => $latestCheck->error_message,
                ],
            ];
        }

        if ($brokenLinksCount === 0) {
            return [
                'status' => 'ok',
                'verdict' => 'broken_links_clear',
                'summary' => 'Deep audit crawl did not find broken links.',
                'evidence' => [
                    'verdict' => 'broken_links_clear',
                    'pages_scanned' => $pagesScanned,
                    'broken_links_count' => 0,
                    'broken_links' => [],
                ],
            ];
        }

        return [
            'status' => 'fail',
            'verdict' => 'broken_links_detected',
            'summary' => sprintf('Deep audit crawl found %d broken link(s).', $brokenLinksCount),
            'evidence' => [
                'verdict' => 'broken_links_detected',
                'pages_scanned' => $pagesScanned,
                'broken_links_count' => $brokenLinksCount,
                'broken_links' => $brokenLinks,
            ],
        ];
    }

    /**
     * @return array{
     *   status: 'ok'|'fail',
     *   verdict: string,
     *   summary: string,
     *   evidence: array<string, mixed>
     * }
     */
    public function auditExternalLinks(WebProperty $property, DomainHealthCheckRunner $healthCheckRunner): array
    {
        $primaryDomain = $property->primaryDomainModel();

        if ($primaryDomain === null) {
            return [
                'status' => 'fail',
                'verdict' => 'missing_primary_domain',
                'summary' => 'Property does not have a primary domain for external-link inventory.',
                'evidence' => [
                    'verdict' => 'missing_primary_domain',
                    'property_slug' => $property->slug,
                ],
            ];
        }

        $refresh = $healthCheckRunner->run($primaryDomain, 'external_links');
        $latestCheck = $primaryDomain->checks()
            ->where('check_type', 'external_links')
            ->latest('finished_at')
            ->latest('created_at')
            ->first();

        if (! $latestCheck instanceof DomainCheck) {
            return [
                'status' => 'fail',
                'verdict' => 'external_link_inventory_refresh_failed',
                'summary' => 'External-link inventory crawl did not produce a persisted result.',
                'evidence' => [
                    'verdict' => 'external_link_inventory_refresh_failed',
                    'refresh_status' => $refresh['status'],
                    'refresh_reason' => $refresh['reason'] ?? null,
                ],
            ];
        }

        $payload = is_array($latestCheck->payload) ? $latestCheck->payload : [];
        $pagesScanned = is_numeric($payload['pages_scanned'] ?? null)
            ? (int) $payload['pages_scanned']
            : 0;
        $pageFailuresCount = is_numeric($payload['page_failures_count'] ?? null)
            ? (int) $payload['page_failures_count']
            : 0;
        $externalLinks = collect(is_array($payload['external_links'] ?? null) ? $payload['external_links'] : [])
            ->filter(fn (mixed $item): bool => is_array($item))
            ->map(function (array $item): array {
                $foundOnPages = collect(is_array($item['found_on_pages'] ?? null) ? $item['found_on_pages'] : [])
                    ->filter(fn (mixed $page): bool => is_string($page) && $page !== '')
                    ->values()
                    ->all();

                return [
                    'url' => is_string($item['url'] ?? null) ? $item['url'] : null,
                    'host' => is_string($item['host'] ?? null) ? $item['host'] : null,
                    'relationship' => is_string($item['relationship'] ?? null) ? $item['relationship'] : 'external',
                    'found_on' => is_string($item['found_on'] ?? null) ? $item['found_on'] : ($foundOnPages[0] ?? null),
                    'found_on_pages' => $foundOnPages,
                ];
            })
            ->filter(fn (array $item): bool => $item['url'] !== null)
            ->values();
        $reviewableLinks = $externalLinks
            ->filter(fn (array $item): bool => $item['relationship'] === 'external')
            ->reject(fn (array $item): bool => $this->isAllowedExternalReferenceHost($item['host'] ?? null))
            ->values();
        $uniqueHosts = $reviewableLinks
            ->pluck('host')
            ->filter(fn (mixed $host): bool => is_string($host) && $host !== '')
            ->unique()
            ->sort()
            ->values()
            ->all();

        if ($latestCheck->status === 'unknown' || $pagesScanned === 0) {
            return [
                'status' => 'fail',
                'verdict' => 'inventory_unverified',
                'summary' => 'External-link inventory could not verify any live pages.',
                'evidence' => [
                    'verdict' => 'inventory_unverified',
                    'refresh_status' => $refresh['status'],
                    'refresh_reason' => $refresh['reason'] ?? null,
                    'pages_scanned' => $pagesScanned,
                    'page_failures_count' => $pageFailuresCount,
                    'error_message' => $latestCheck->error_message,
                ],
            ];
        }

        if ($reviewableLinks->isEmpty()) {
            return [
                'status' => 'ok',
                'verdict' => 'external_links_clear',
                'summary' => 'Deep audit inventory did not find off-host links that need review.',
                'evidence' => [
                    'verdict' => 'external_links_clear',
                    'pages_scanned' => $pagesScanned,
                    'page_failures_count' => $pageFailuresCount,
                    'reviewable_external_links_count' => 0,
                    'reviewable_unique_hosts_count' => 0,
                    'external_links' => [],
                    'unique_hosts' => [],
                ],
            ];
        }

        return [
            'status' => 'fail',
            'verdict' => 'external_links_detected',
            'summary' => sprintf(
                'Deep audit inventory found %d off-host link(s) across %d unique host(s).',
                $reviewableLinks->count(),
                count($uniqueHosts)
            ),
            'evidence' => [
                'verdict' => 'external_links_detected',
                'pages_scanned' => $pagesScanned,
                'page_failures_count' => $pageFailuresCount,
                'reviewable_external_links_count' => $reviewableLinks->count(),
                'reviewable_unique_hosts_count' => count($uniqueHosts),
                'external_links' => $reviewableLinks->all(),
                'unique_hosts' => $uniqueHosts,
            ],
        ];
    }

    private function isAllowedExternalReferenceHost(mixed $host): bool
    {
        if (! is_string($host) || trim($host) === '') {
            return false;
        }

        $normalizedHost = Str::lower(trim($host));

        return $normalizedHost === 'gov.au'
            || Str::endsWith($normalizedHost, '.gov.au');
    }

    /**
     * @return array{
     *   status: 'ok'|'fail',
     *   verdict: string,
     *   summary: string,
     *   evidence: array<string, mixed>
     * }
     */
    public function auditRedirectPolicy(WebProperty $property, int $timeout = 10): array
    {
        $expectedOrigin = $this->expectedOrigin($property);

        if ($expectedOrigin === null) {
            return [
                'status' => 'fail',
                'verdict' => 'missing_expected_origin',
                'summary' => 'Property does not have a resolvable preferred origin for redirect checks.',
                'evidence' => [
                    'verdict' => 'missing_expected_origin',
                    'property_slug' => $property->slug,
                ],
            ];
        }

        $expectedParts = parse_url($expectedOrigin);
        $expectedHost = is_array($expectedParts) ? (string) ($expectedParts['host'] ?? '') : '';
        $httpProbe = $this->probeUrl('http://'.$expectedHost.'/', $timeout);
        $alternateHost = $this->alternateHost($expectedHost);
        $alternateProbe = $alternateHost !== null
            ? $this->probeUrl('https://'.$alternateHost.'/', $timeout)
            : null;
        $problems = [];

        if ($httpProbe === null) {
            $problems[] = 'http_fetch_failed';
        } else {
            if (! $this->finalUrlMatchesExpectedOrigin($httpProbe['final_url'], $expectedOrigin)) {
                $problems[] = 'http_upgrade_failed';
            }

            if (count($httpProbe['redirect_chain']) > 2) {
                $problems[] = 'redirect_chain_too_long';
            }
        }

        if ($alternateHost !== null) {
            if ($alternateProbe === null) {
                $problems[] = 'preferred_host_unverified';
            } elseif (! $this->finalUrlMatchesExpectedOrigin($alternateProbe['final_url'], $expectedOrigin)) {
                $problems[] = 'preferred_host_mismatch';
            }
        }

        if ($problems === []) {
            return [
                'status' => 'ok',
                'verdict' => 'redirect_policy_ok',
                'summary' => 'Root redirects resolve cleanly to the preferred HTTPS host.',
                'evidence' => [
                    'verdict' => 'redirect_policy_ok',
                    'expected_origin' => $expectedOrigin,
                    'http_probe' => $httpProbe,
                    'alternate_probe' => $alternateProbe,
                    'problems' => [],
                ],
            ];
        }

        $verdict = $problems[0];

        return [
            'status' => 'fail',
            'verdict' => $verdict,
            'summary' => $this->summaryForRedirectProblems($problems, $expectedOrigin),
            'evidence' => [
                'verdict' => $verdict,
                'expected_origin' => $expectedOrigin,
                'http_probe' => $httpProbe,
                'alternate_probe' => $alternateProbe,
                'problems' => $problems,
            ],
        ];
    }

    /**
     * @return Collection<int, string>
     */
    private function candidateUrlsForProperty(WebProperty $property): Collection
    {
        $urls = [];

        if (is_string($property->production_url) && trim($property->production_url) !== '') {
            $urls[] = trim($property->production_url);
        }

        $expectedOrigin = $this->expectedOrigin($property);
        if ($expectedOrigin !== null) {
            $urls[] = $expectedOrigin.'/';
        }

        $primaryDomain = $property->primaryDomainName();
        if (is_string($primaryDomain) && trim($primaryDomain) !== '') {
            $urls[] = 'https://'.trim($primaryDomain).'/';
        }

        /** @var Collection<int, string> $normalizedUrls */
        $normalizedUrls = collect($urls)
            ->map(fn (string $url): string => $this->normalizedUrl($url))
            ->filter(fn (string $url): bool => $url !== '')
            ->unique()
            ->values();

        return $normalizedUrls;
    }

    private function baseUrlForProperty(WebProperty $property): string
    {
        return $this->expectedOrigin($property)
            ?? rtrim($this->normalizedUrl($property->production_url ?? ''), '/')
            ?: 'https://'.trim((string) $property->primaryDomainName());
    }

    private function expectedOrigin(WebProperty $property): ?string
    {
        $scheme = $property->canonical_origin_scheme;
        $host = $property->canonical_origin_host;

        if (is_string($scheme) && trim($scheme) !== '' && is_string($host) && trim($host) !== '') {
            return strtolower(trim($scheme)).'://'.Str::lower(trim($host));
        }

        $primaryDomain = $property->primaryDomainName();

        if (! is_string($primaryDomain) || trim($primaryDomain) === '') {
            return null;
        }

        return 'https://'.Str::lower(trim($primaryDomain));
    }

    /**
     * @param  Collection<int, string>  $urls
     * @return array<string, mixed>|null
     */
    private function firstSuccessfulProbe(Collection $urls, int $timeout): ?array
    {
        foreach ($urls as $url) {
            $probe = $this->probeUrl($url, $timeout);

            if ($probe !== null && $probe['successful']) {
                return $probe;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function probeUrl(string $url, int $timeout): ?array
    {
        try {
            $response = Http::timeout($timeout)
                ->withHeaders(['User-Agent' => 'DomainMonitor/1.0'])
                ->withOptions([
                    'allow_redirects' => [
                        'max' => 5,
                        'track_redirects' => true,
                    ],
                ])
                ->get($url);

            $redirectHistory = Arr::wrap($response->header('X-Guzzle-Redirect-History'));
            $statusHistory = Arr::wrap($response->header('X-Guzzle-Redirect-Status-History'));
            $finalUrl = is_string(last($redirectHistory)) && trim((string) last($redirectHistory)) !== ''
                ? (string) last($redirectHistory)
                : $url;

            $normalizedRedirectHistory = collect($redirectHistory)
                ->map(fn (string $item): string => trim($item))
                ->filter(fn (string $item): bool => $item !== '')
                ->values()
                ->all();
            $normalizedStatusHistory = collect($statusHistory)
                ->map(fn (string $item): int => (int) $item)
                ->values()
                ->all();

            return [
                'url' => $url,
                'final_url' => $finalUrl,
                'status' => $response->status(),
                'successful' => $response->successful(),
                'headers' => $response->headers(),
                'html' => $response->body(),
                'redirect_chain' => $normalizedRedirectHistory,
                'redirect_statuses' => $normalizedStatusHistory,
            ];
        } catch (ConnectionException|RequestException) {
            return null;
        }
    }

    /**
     * @return array{ok: bool, url: string, status: int, body: string|null, error: string|null, sitemap_url?: string|null}
     */
    private function fetchRobotsFile(string $pageUrl, int $timeout): array
    {
        $parts = parse_url($pageUrl);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return [
                'ok' => false,
                'url' => '',
                'status' => 0,
                'body' => null,
                'error' => 'invalid_page_url',
                'sitemap_url' => null,
            ];
        }

        $baseUrl = strtolower((string) $parts['scheme']).'://'.Str::lower((string) $parts['host']);
        if (isset($parts['port'])) {
            $baseUrl .= ':'.$parts['port'];
        }

        $robots = $this->fetchTextFile($baseUrl.'/robots.txt', $timeout);
        $body = $robots['body'] ?? null;

        if (! is_string($body) || $body === '') {
            $robots['sitemap_url'] = null;

            return $robots;
        }

        preg_match('/^\s*Sitemap:\s*(\S+)\s*$/im', $body, $matches);
        $robots['sitemap_url'] = is_string($matches[1] ?? null) ? trim($matches[1]) : null;

        return $robots;
    }

    /**
     * @return array{ok: bool, url: string, status: int, body: string|null, error: string|null}
     */
    private function fetchTextFile(string $url, int $timeout): array
    {
        try {
            $response = Http::timeout($timeout)
                ->withHeaders(['User-Agent' => 'DomainMonitor/1.0'])
                ->get($url);

            return [
                'ok' => $response->successful(),
                'url' => $url,
                'status' => $response->status(),
                'body' => $response->successful() ? $response->body() : null,
                'error' => $response->successful() ? null : 'status_'.$response->status(),
            ];
        } catch (ConnectionException|RequestException $exception) {
            return [
                'ok' => false,
                'url' => $url,
                'status' => 0,
                'body' => null,
                'error' => $exception->getMessage(),
            ];
        }
    }

    /**
     * @return array{ok: bool, url: string, status: int, body: string|null, error: string|null}
     */
    private function fetchSitemapFile(string $baseUrl, int $timeout): array
    {
        $base = rtrim($baseUrl, '/');
        $sitemap = $this->fetchTextFile($base.'/sitemap.xml', $timeout);

        if ($sitemap['ok']) {
            return $sitemap;
        }

        return $this->fetchTextFile($base.'/sitemap_index.xml', $timeout);
    }

    private function extractCanonicalUrl(string $html): ?string
    {
        if (preg_match('/<link[^>]+rel=["\'][^"\']*canonical[^"\']*["\'][^>]+href=["\']([^"\']+)["\']/i', $html, $matches) === 1) {
            return trim((string) $matches[1]);
        }

        if (preg_match('/<link[^>]+href=["\']([^"\']+)["\'][^>]+rel=["\'][^"\']*canonical[^"\']*["\']/i', $html, $matches) === 1) {
            return trim((string) $matches[1]);
        }

        return null;
    }

    /**
     * @param  array<string, array<int, string>>  $headers
     */
    private function hasNoindexDirective(string $html, array $headers): bool
    {
        if (preg_match('/<meta[^>]+name=["\']robots["\'][^>]+content=["\']([^"\']*)["\']/i', $html, $matches) === 1) {
            return Str::contains(Str::lower((string) $matches[1]), 'noindex');
        }

        if (preg_match('/<meta[^>]+content=["\']([^"\']*)["\'][^>]+name=["\']robots["\']/i', $html, $matches) === 1) {
            return Str::contains(Str::lower((string) $matches[1]), 'noindex');
        }

        $robotsHeaders = $headers['X-Robots-Tag'] ?? $headers['x-robots-tag'] ?? [];

        return collect(Arr::wrap($robotsHeaders))
            ->contains(fn (string $header): bool => Str::contains(Str::lower($header), 'noindex'));
    }

    /**
     * @return array<int, array{valid: bool, type: string|null}>
     */
    private function structuredDataScripts(string $html): array
    {
        preg_match_all('/<script[^>]*type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', $html, $matches);
        $contents = $matches[1];

        return collect($contents)
            ->map(function (string $content): array {
                $decoded = json_decode(trim($content), true);

                if (! is_array($decoded)) {
                    return ['valid' => false, 'type' => null];
                }

                $type = data_get($decoded, '@type');

                if (! is_string($type) && is_array(data_get($decoded, '@graph'))) {
                    $type = collect(data_get($decoded, '@graph'))
                        ->first(fn (mixed $node): bool => is_array($node) && is_string($node['@type'] ?? null))['@type'] ?? null;
                }

                return [
                    'valid' => true,
                    'type' => is_string($type) ? $type : null,
                ];
            })
            ->all();
    }

    private function canonicalMatchesExpectedOrigin(string $canonicalUrl, string $expectedOrigin, string $pageUrl): bool
    {
        if (str_starts_with($canonicalUrl, '/')) {
            $parts = parse_url($pageUrl);

            if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
                return false;
            }

            $canonicalUrl = strtolower((string) $parts['scheme']).'://'.Str::lower((string) $parts['host']).$canonicalUrl;
        }

        return $this->finalUrlMatchesExpectedOrigin($canonicalUrl, $expectedOrigin);
    }

    private function finalUrlMatchesExpectedOrigin(string $url, string $expectedOrigin): bool
    {
        $final = parse_url($url);
        $expected = parse_url($expectedOrigin);

        if (! is_array($final) || ! is_array($expected)) {
            return false;
        }

        return Str::lower((string) ($final['scheme'] ?? '')) === Str::lower((string) ($expected['scheme'] ?? ''))
            && Str::lower((string) ($final['host'] ?? '')) === Str::lower((string) ($expected['host'] ?? ''));
    }

    private function alternateHost(string $expectedHost): ?string
    {
        $normalized = Str::lower(trim($expectedHost));

        if ($normalized === '' || Str::startsWith($normalized, 'www.') && Str::substrCount($normalized, '.') < 2) {
            return null;
        }

        return Str::startsWith($normalized, 'www.')
            ? Str::after($normalized, 'www.')
            : 'www.'.$normalized;
    }

    /**
     * @param  array<int, string>  $problems
     */
    private function summaryForIndexabilityProblems(array $problems, ?string $expectedOrigin): string
    {
        $labels = collect($problems)
            ->map(fn (string $problem): string => match ($problem) {
                'missing_canonical' => 'canonical tag is missing',
                'wrong_canonical' => 'canonical does not match the preferred origin',
                'homepage_noindex' => 'homepage is marked noindex',
                'preferred_host_mismatch' => 'homepage does not resolve to the preferred host',
                'sitemap_not_referenced' => 'robots.txt does not reference a sitemap',
                default => $problem,
            })
            ->all();

        $suffix = $expectedOrigin !== null ? sprintf(' Expected origin: %s.', $expectedOrigin) : '';

        return sprintf(
            'Homepage indexability signals are off: %s.%s',
            implode('; ', $labels),
            $suffix
        );
    }

    /**
     * @param  array<int, string>  $problems
     */
    private function summaryForRedirectProblems(array $problems, string $expectedOrigin): string
    {
        $labels = collect($problems)
            ->map(fn (string $problem): string => match ($problem) {
                'http_fetch_failed' => 'HTTP root could not be fetched',
                'http_upgrade_failed' => 'HTTP root did not resolve to the preferred HTTPS origin',
                'redirect_chain_too_long' => 'redirect chain is longer than expected',
                'preferred_host_unverified' => 'non-preferred host redirect could not be verified',
                'preferred_host_mismatch' => 'non-preferred host did not redirect to the preferred host',
                default => $problem,
            })
            ->all();

        return sprintf(
            'Root redirect policy is not clean: %s. Expected origin: %s.',
            implode('; ', $labels),
            $expectedOrigin
        );
    }

    private function normalizedUrl(?string $url): string
    {
        if (! is_string($url) || trim($url) === '') {
            return '';
        }

        $trimmed = trim($url);

        if (! str_starts_with($trimmed, 'http://') && ! str_starts_with($trimmed, 'https://')) {
            $trimmed = 'https://'.$trimmed;
        }

        return $trimmed;
    }

    private function normalizedComparableUrl(mixed $url): ?string
    {
        if (! is_string($url) || trim($url) === '') {
            return null;
        }

        $parts = parse_url(trim($url));

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        $normalized = strtolower((string) $parts['scheme']).'://'.Str::lower((string) $parts['host']);

        if (isset($parts['port'])) {
            $normalized .= ':'.$parts['port'];
        }

        $path = isset($parts['path']) ? (string) $parts['path'] : '';
        $normalized .= $path !== '' ? rtrim($path, '/') : '';

        return $normalized;
    }

    private function hostForComparableUrl(?string $url): ?string
    {
        if (! is_string($url) || $url === '') {
            return null;
        }

        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? Str::lower($host) : null;
    }
}
