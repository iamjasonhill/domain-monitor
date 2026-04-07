<?php

namespace App\Services;

use App\Models\WebProperty;
use Illuminate\Support\Str;

class SearchConsoleIssueActionabilityService
{
    public function __construct(
        private readonly IntentionalSearchConsoleExclusionService $intentionalSearchConsoleExclusionService,
    ) {}

    /**
     * @param  array<string, mixed>  $issueEvidence
     * @return array<string, mixed>
     */
    public function normalize(WebProperty $property, string $issueClass, array $issueEvidence): array
    {
        $normalizedEvidence = match ($issueClass) {
            'not_found_404' => $this->normalizeNotFound404Issue($property, $issueEvidence),
            'page_with_redirect_in_sitemap' => $this->normalizePageWithRedirectIssue($issueEvidence),
            default => $issueEvidence,
        };

        $expectedExclusion = $this->intentionalSearchConsoleExclusionService->classify(
            $property,
            $issueClass,
            $normalizedEvidence
        );

        if (is_array($expectedExclusion)) {
            $normalizedEvidence['expected_exclusion'] = $expectedExclusion;
        }

        return $normalizedEvidence;
    }

    /**
     * @param  array<string, mixed>  $issueEvidence
     */
    public function isActionable(string $issueClass, array $issueEvidence): bool
    {
        if (! is_array(config('domain_monitor.search_console_issue_catalog.'.$issueClass))) {
            return true;
        }

        if (is_array($issueEvidence['expected_exclusion'] ?? null)) {
            return false;
        }

        $activeAffectedUrlCount = is_numeric($issueEvidence['active_affected_url_count'] ?? null)
            ? (int) $issueEvidence['active_affected_url_count']
            : null;

        if ($activeAffectedUrlCount !== null) {
            return $activeAffectedUrlCount > 0
                || ((int) ($issueEvidence['affected_url_count'] ?? 0) > 0);
        }

        if (is_numeric($issueEvidence['affected_url_count'] ?? null)) {
            return (int) $issueEvidence['affected_url_count'] > 0;
        }

        foreach (['affected_urls', 'examples', 'url_inspection', 'sitemaps', 'referring_urls', 'canonical_state', 'search_analytics'] as $key) {
            if (! empty($issueEvidence[$key])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $issueEvidence
     * @return array<string, mixed>
     */
    private function normalizeNotFound404Issue(WebProperty $property, array $issueEvidence): array
    {
        $candidateUrls = $this->candidateUrls($issueEvidence);

        if ($candidateUrls === []) {
            return $issueEvidence;
        }

        $activeUrls = [];
        $retiredUrls = [];
        $allSystemNoise = true;
        $allAuthorArchives = true;
        $allLegacyEndpoints = true;

        foreach ($candidateUrls as $url) {
            $isSystemNoise = $this->isExpectedWordPressSystem404($url);
            $isAuthorArchive = $this->isWordPressAuthorArchiveUrl($url);
            $isLegacyEndpoint = $this->isLegacyEndpointUrl($property, $url);

            if (! $isSystemNoise) {
                $allSystemNoise = false;
            }

            if (! $isAuthorArchive) {
                $allAuthorArchives = false;
            }

            if (! $isLegacyEndpoint) {
                $allLegacyEndpoints = false;
            }

            if ($isSystemNoise) {
                $retiredUrls[$url] = 'expected_wordpress_system_404';

                continue;
            }

            if ($this->isResolvedLegacyEndpoint($property, $url)) {
                $retiredUrls[$url] = 'resolved_legacy_endpoint';

                continue;
            }

            $liveCheck = $this->liveUrlCheckForUrl($issueEvidence, $url);

            if ($liveCheck !== null && $this->isResolvedLiveUrlCheck($liveCheck)) {
                $retiredUrls[$url] = $this->isRetiredAuthorArchive($property, $url, $issueEvidence, $liveCheck)
                    ? 'retired_wordpress_author_archive'
                    : 'resolved_live_url';

                continue;
            }

            $activeUrls[] = $url;
        }

        if ($activeUrls === $candidateUrls) {
            return $issueEvidence;
        }

        if (! $this->issueEvidenceRepresentsCompleteSet($issueEvidence, $candidateUrls)) {
            return $this->filterEvidenceToUrls($issueEvidence, $activeUrls, true);
        }

        $normalizedEvidence = $this->filterEvidenceToUrls($issueEvidence, $activeUrls);

        if ($activeUrls !== []) {
            return $normalizedEvidence;
        }

        $matchedUrls = array_keys($retiredUrls);
        $state = 'resolved_or_retired_404';
        $reason = 'all_exact_examples_are_non_actionable_or_already_resolved';
        $code = 'resolved_or_retired_404';

        if ($matchedUrls !== [] && $allSystemNoise) {
            $state = 'expected_wordpress_system_404';
            $reason = 'wordpress_system_paths_are_expected_404_noise';
            $code = 'expected_wordpress_system_404';
        } elseif ($matchedUrls !== [] && $allLegacyEndpoints) {
            $state = 'resolved_legacy_endpoint_404';
            $reason = 'legacy_subdomain_endpoints_now_resolve_successfully';
            $code = 'resolved_legacy_endpoint_404';
        } elseif ($matchedUrls !== [] && $allAuthorArchives) {
            $state = 'retired_wordpress_author_archive';
            $reason = 'retired_wordpress_author_archives_now_redirect_safely';
            $code = 'retired_wordpress_author_archive';
        }

        $normalizedEvidence['expected_exclusion'] = [
            'state' => $state,
            'code' => $code,
            'reason' => $reason,
            'matched_urls' => array_slice($matchedUrls, 0, 10),
            'matched_url_count' => count($matchedUrls),
        ];

        return $normalizedEvidence;
    }

    /**
     * @param  array<string, mixed>  $issueEvidence
     * @return array<string, mixed>
     */
    private function normalizePageWithRedirectIssue(array $issueEvidence): array
    {
        $candidateUrls = $this->candidateUrls($issueEvidence);

        if ($candidateUrls === []) {
            return $issueEvidence;
        }

        $activeUrls = [];
        $retiredUrls = [];

        foreach ($candidateUrls as $url) {
            $sitemapCheck = $this->liveSitemapCheckForUrl($issueEvidence, $url);

            if ($sitemapCheck !== null && ($sitemapCheck['present_in_current_sitemap'] ?? null) === false) {
                $retiredUrls[$url] = 'removed_from_current_sitemap';

                continue;
            }

            $activeUrls[] = $url;
        }

        if ($activeUrls === $candidateUrls) {
            return $issueEvidence;
        }

        if (! $this->issueEvidenceRepresentsCompleteSet($issueEvidence, $candidateUrls)) {
            return $this->filterEvidenceToUrls($issueEvidence, $activeUrls, true);
        }

        $normalizedEvidence = $this->filterEvidenceToUrls($issueEvidence, $activeUrls);

        if ($activeUrls !== []) {
            return $normalizedEvidence;
        }

        $matchedUrls = array_keys($retiredUrls);
        $normalizedEvidence['expected_exclusion'] = [
            'state' => 'resolved_or_retired_redirect_in_sitemap',
            'code' => 'removed_from_current_sitemap',
            'reason' => 'current_sitemap_no_longer_contains_historical_redirect_urls',
            'matched_urls' => array_slice($matchedUrls, 0, 10),
            'matched_url_count' => count($matchedUrls),
        ];

        return $normalizedEvidence;
    }

    /**
     * @param  array<string, mixed>  $issueEvidence
     * @return array<int, string>
     */
    private function candidateUrls(array $issueEvidence): array
    {
        $urls = [];

        foreach ((array) ($issueEvidence['affected_urls'] ?? []) as $url) {
            if (is_string($url) && $url !== '') {
                $urls[] = $url;
            }
        }

        foreach ((array) ($issueEvidence['sample_urls'] ?? []) as $url) {
            if (is_string($url) && $url !== '') {
                $urls[] = $url;
            }
        }

        foreach ((array) ($issueEvidence['examples'] ?? []) as $example) {
            if (is_array($example) && is_string($example['url'] ?? null) && $example['url'] !== '') {
                $urls[] = $example['url'];
            }
        }

        return array_values(array_unique($urls));
    }

    /**
     * @param  array<string, mixed>  $issueEvidence
     * @param  array<int, string>  $candidateUrls
     */
    private function issueEvidenceRepresentsCompleteSet(array $issueEvidence, array $candidateUrls): bool
    {
        if (($issueEvidence['is_example_set_truncated'] ?? false) === true) {
            return false;
        }

        $affectedUrlCount = is_numeric($issueEvidence['affected_url_count'] ?? null)
            ? (int) $issueEvidence['affected_url_count']
            : null;
        $exactExampleCount = is_numeric($issueEvidence['exact_example_count'] ?? null)
            ? (int) $issueEvidence['exact_example_count']
            : null;
        $candidateUrlCount = count($candidateUrls);

        if ($exactExampleCount !== null && $affectedUrlCount !== null) {
            return $candidateUrlCount >= $exactExampleCount
                && $candidateUrlCount >= $affectedUrlCount;
        }

        if ($exactExampleCount !== null) {
            return $candidateUrlCount >= $exactExampleCount;
        }

        if ($affectedUrlCount !== null) {
            return $candidateUrlCount >= $affectedUrlCount;
        }

        return false;
    }

    private function isExpectedWordPressSystem404(string $url): bool
    {
        $path = Str::lower((string) parse_url($url, PHP_URL_PATH));

        if ($path === '' || $path === '/*') {
            return true;
        }

        if (Str::startsWith($path, ['/wp-content/', '/wp-content/plugins/', '/wp-content/themes/', '/wp-content/uploads/'])) {
            return true;
        }

        return (bool) preg_match('#^/wp-[^/]+\.php$#', $path);
    }

    private function isResolvedLegacyEndpoint(WebProperty $property, string $url): bool
    {
        $scan = is_array($property->legacy_moveroo_endpoint_scan) ? $property->legacy_moveroo_endpoint_scan : [];

        foreach ([
            'legacy_booking_endpoint' => $property->target_legacy_bookings_replacement_url,
            'legacy_payment_endpoint' => $property->target_legacy_payments_replacement_url,
        ] as $key => $preferredReplacement) {
            $entry = $scan[$key] ?? null;

            if (! is_array($entry) || ! is_string($entry['url'] ?? null) || $entry['url'] !== $url) {
                continue;
            }

            $status = is_numeric($entry['resolved_status'] ?? null) ? (int) $entry['resolved_status'] : null;
            $resolvedUrl = $this->normalizeComparableUrl($entry['resolved_url'] ?? null);
            $preferredTarget = $this->normalizeComparableUrl($preferredReplacement);

            return $status !== null
                && $status >= 200
                && $status < 300
                && $preferredTarget !== null
                && $resolvedUrl === $preferredTarget;
        }

        return false;
    }

    private function isLegacyEndpointUrl(WebProperty $property, string $url): bool
    {
        $scan = is_array($property->legacy_moveroo_endpoint_scan) ? $property->legacy_moveroo_endpoint_scan : [];

        foreach (['legacy_booking_endpoint', 'legacy_payment_endpoint'] as $key) {
            if (is_string($scan[$key]['url'] ?? null) && $scan[$key]['url'] === $url) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $issueEvidence
     * @return array<string, mixed>|null
     */
    private function liveUrlCheckForUrl(array $issueEvidence, string $url): ?array
    {
        foreach ((array) ($issueEvidence['live_url_checks'] ?? []) as $check) {
            if (is_array($check) && is_string($check['url'] ?? null) && $check['url'] === $url) {
                return $check;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $issueEvidence
     * @return array<string, mixed>|null
     */
    private function liveSitemapCheckForUrl(array $issueEvidence, string $url): ?array
    {
        foreach ((array) ($issueEvidence['live_sitemap_checks'] ?? []) as $check) {
            if (is_array($check) && is_string($check['url'] ?? null) && $check['url'] === $url) {
                return $check;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $liveCheck
     */
    private function isResolvedLiveUrlCheck(array $liveCheck): bool
    {
        if (($liveCheck['resolved_ok'] ?? false) === true) {
            return true;
        }

        $finalStatus = is_numeric($liveCheck['final_status'] ?? null)
            ? (int) $liveCheck['final_status']
            : null;

        return $finalStatus !== null && $finalStatus >= 200 && $finalStatus < 300;
    }

    /**
     * @param  array<string, mixed>  $issueEvidence
     * @param  array<string, mixed>  $liveCheck
     */
    private function isRetiredAuthorArchive(WebProperty $property, string $url, array $issueEvidence, array $liveCheck): bool
    {
        if (! $this->isWordPressAuthorArchiveUrl($url)) {
            return false;
        }

        $finalUrl = is_string($liveCheck['final_url'] ?? null) ? $liveCheck['final_url'] : null;

        if (! $this->isSafeResolvedPropertyUrl($property, $finalUrl) || $this->inspectionSuggestsCurrentSources($issueEvidence, $url)) {
            return false;
        }

        return true;
    }

    private function isWordPressAuthorArchiveUrl(string $url): bool
    {
        $path = Str::lower((string) parse_url($url, PHP_URL_PATH));

        return (bool) preg_match('#^/author/[^/]+/?$#', $path);
    }

    /**
     * @param  array<string, mixed>  $issueEvidence
     */
    private function inspectionSuggestsCurrentSources(array $issueEvidence, string $url): bool
    {
        foreach ((array) data_get($issueEvidence, 'url_inspection.inspected_urls', []) as $inspection) {
            if (! is_array($inspection) || ! is_string($inspection['url'] ?? null) || $inspection['url'] !== $url) {
                continue;
            }

            if (! empty($inspection['referring_urls']) || ! empty($inspection['sitemaps'])) {
                return true;
            }
        }

        return false;
    }

    private function isSafeResolvedPropertyUrl(WebProperty $property, ?string $url): bool
    {
        if (! is_string($url) || $url === '') {
            return false;
        }

        $host = Str::lower((string) parse_url($url, PHP_URL_HOST));
        $path = (string) parse_url($url, PHP_URL_PATH);

        if ($host === '' || ! in_array($host, $this->knownPropertyHosts($property), true)) {
            return false;
        }

        return ! Str::startsWith(Str::lower($path), '/author/');
    }

    private function normalizeComparableUrl(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        $parts = parse_url($value);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        $normalizedUrl = Str::lower((string) $parts['scheme']).'://'.Str::lower((string) $parts['host']);

        if (isset($parts['port'])) {
            $normalizedUrl .= ':'.$parts['port'];
        }

        $path = (string) ($parts['path'] ?? '/');

        return $normalizedUrl.($path !== '' ? $path : '/');
    }

    /**
     * @param  array<string, mixed>  $issueEvidence
     * @param  array<int, string>  $activeUrls
     * @return array<string, mixed>
     */
    private function filterEvidenceToUrls(array $issueEvidence, array $activeUrls, bool $preserveCounts = false): array
    {
        $activeUrlMap = array_fill_keys($activeUrls, true);

        $issueEvidence['affected_urls'] = $activeUrls;
        $issueEvidence['sample_urls'] = array_slice($activeUrls, 0, 10);
        $issueEvidence['examples'] = array_values(array_filter(
            (array) ($issueEvidence['examples'] ?? []),
            static fn (mixed $example): bool => is_array($example)
                && is_string($example['url'] ?? null)
                && isset($activeUrlMap[$example['url']])
        ));
        $issueEvidence['live_url_checks'] = array_values(array_filter(
            (array) ($issueEvidence['live_url_checks'] ?? []),
            static fn (mixed $check): bool => is_array($check)
                && is_string($check['url'] ?? null)
                && isset($activeUrlMap[$check['url']])
        ));
        $issueEvidence['live_sitemap_checks'] = array_values(array_filter(
            (array) ($issueEvidence['live_sitemap_checks'] ?? []),
            static fn (mixed $check): bool => is_array($check)
                && is_string($check['url'] ?? null)
                && isset($activeUrlMap[$check['url']])
        ));

        if (! $preserveCounts) {
            $issueEvidence['affected_url_count'] = count($activeUrls);
            $issueEvidence['exact_example_count'] = count($activeUrls);
            $issueEvidence['is_example_set_truncated'] = false;
            unset($issueEvidence['active_affected_url_count'], $issueEvidence['raw_affected_url_count']);
        } else {
            $issueEvidence['active_affected_url_count'] = count($activeUrls);
            $issueEvidence['raw_affected_url_count'] = is_numeric($issueEvidence['affected_url_count'] ?? null)
                ? (int) $issueEvidence['affected_url_count']
                : null;
        }

        return $issueEvidence;
    }

    /**
     * @return array<int, string>
     */
    private function knownPropertyHosts(WebProperty $property): array
    {
        $hosts = [];

        $property->loadMissing(['primaryDomain', 'propertyDomains.domain']);

        if (is_string($property->primaryDomain?->domain) && $property->primaryDomain->domain !== '') {
            $hosts[] = Str::lower($property->primaryDomain->domain);
        }

        foreach ($property->propertyDomains as $link) {
            if (is_string($link->domain?->domain) && $link->domain->domain !== '') {
                $hosts[] = Str::lower($link->domain->domain);
            }
        }

        if (is_string($property->target_moveroo_subdomain_url) && $property->target_moveroo_subdomain_url !== '') {
            $targetHost = parse_url($property->target_moveroo_subdomain_url, PHP_URL_HOST);

            if (is_string($targetHost) && $targetHost !== '') {
                $hosts[] = Str::lower($targetHost);
            }
        }

        return array_values(array_unique($hosts));
    }
}
