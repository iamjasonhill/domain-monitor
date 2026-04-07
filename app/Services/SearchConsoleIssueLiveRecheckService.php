<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\SearchConsoleIssueSnapshot;
use App\Models\Subdomain;
use App\Models\WebProperty;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SearchConsoleIssueLiveRecheckService
{
    /**
     * @var array<int, string>
     */
    private const ISSUE_CLASSES = [
        'not_found_404',
        'page_with_redirect_in_sitemap',
    ];

    private const REDIRECT_LIMIT = 5;

    private const TIMEOUT_SECONDS = 5;

    private const URL_LIMIT = 10;

    private const TOTAL_BUDGET_SECONDS = 20;

    private const SITEMAP_FILE_LIMIT = 20;

    /**
     * @var array<string, bool>
     */
    private array $managedHostCache = [];

    /**
     * @return array{status:'refreshed'|'skipped'|'failed',captured_at:string|null,checked_url_count:int,reason:string|null}
     */
    public function refreshProperty(WebProperty $property, ?string $capturedBy = null): array
    {
        $property->loadMissing(['primaryDomain', 'propertyDomains.domain']);

        $primaryDomain = $property->primaryDomainModel();

        if (! $primaryDomain instanceof Domain) {
            return [
                'status' => 'skipped',
                'captured_at' => null,
                'checked_url_count' => 0,
                'reason' => 'missing',
            ];
        }

        try {
            $results = collect(self::ISSUE_CLASSES)
                ->map(fn (string $issueClass): array => $this->refreshIssueClass($property, $primaryDomain, $issueClass, $capturedBy))
                ->values();
        } catch (\Throwable $exception) {
            Log::warning('Search Console live URL recheck failed', [
                'web_property_id' => $property->id,
                'property_slug' => $property->slug,
                'issue_classes' => self::ISSUE_CLASSES,
                'exception' => $exception->getMessage(),
            ]);

            return [
                'status' => 'failed',
                'captured_at' => null,
                'checked_url_count' => 0,
                'reason' => 'live_recheck_failed',
            ];
        }

        $refreshedResults = $results->where('status', 'refreshed')->values();

        if ($refreshedResults->isNotEmpty()) {
            return [
                'status' => 'refreshed',
                'captured_at' => $refreshedResults
                    ->pluck('captured_at')
                    ->filter(fn (mixed $capturedAt): bool => is_string($capturedAt) && $capturedAt !== '')
                    ->sort()
                    ->last(),
                'checked_url_count' => (int) $refreshedResults->sum('checked_url_count'),
                'reason' => null,
            ];
        }

        if ($results->contains(fn (array $result): bool => $result['status'] === 'failed')) {
            return [
                'status' => 'failed',
                'captured_at' => null,
                'checked_url_count' => 0,
                'reason' => 'live_recheck_failed',
            ];
        }

        return [
            'status' => 'skipped',
            'captured_at' => null,
            'checked_url_count' => 0,
            'reason' => 'missing',
        ];
    }

    /**
     * @return array{status:'refreshed'|'skipped'|'failed',captured_at:string|null,checked_url_count:int,reason:string|null}
     */
    private function refreshIssueClass(WebProperty $property, Domain $primaryDomain, string $issueClass, ?string $capturedBy): array
    {
        $sourceSnapshot = $this->latestSourceSnapshot($property, $issueClass);

        if (! $sourceSnapshot instanceof SearchConsoleIssueSnapshot) {
            return [
                'status' => 'skipped',
                'captured_at' => null,
                'checked_url_count' => 0,
                'reason' => 'missing',
            ];
        }

        $candidateUrls = $this->candidateUrls($sourceSnapshot->issueEvidence());

        if ($candidateUrls === []) {
            return [
                'status' => 'skipped',
                'captured_at' => null,
                'checked_url_count' => 0,
                'reason' => 'missing',
            ];
        }

        $checks = match ($issueClass) {
            'not_found_404' => $this->probeLiveUrls($property, $candidateUrls),
            'page_with_redirect_in_sitemap' => $this->probeCurrentSitemapUrls($property, $sourceSnapshot, $candidateUrls),
            default => null,
        };

        if (! is_array($checks) || $checks === []) {
            return [
                'status' => 'skipped',
                'captured_at' => null,
                'checked_url_count' => 0,
                'reason' => 'missing',
            ];
        }

        $capturedAt = now();

        DB::transaction(function () use ($property, $primaryDomain, $sourceSnapshot, $issueClass, $capturedAt, $capturedBy, $checks): void {
            SearchConsoleIssueSnapshot::query()
                ->where('web_property_id', $property->id)
                ->where('issue_class', $issueClass)
                ->where('capture_method', 'gsc_live_recheck')
                ->delete();

            $normalizedPayload = [
                'affected_urls' => array_map(
                    static fn (array $check): string => (string) $check['url'],
                    $checks
                ),
            ];

            $rawPayload = [
                'source_snapshot_id' => $sourceSnapshot->id,
            ];

            if ($issueClass === 'not_found_404') {
                $normalizedPayload['live_url_checks'] = $checks;
                $rawPayload['live_url_checks'] = $checks;
            }

            if ($issueClass === 'page_with_redirect_in_sitemap') {
                $normalizedPayload['live_sitemap_checks'] = $checks;
                $rawPayload['live_sitemap_checks'] = $checks;
            }

            SearchConsoleIssueSnapshot::query()->create([
                'domain_id' => $primaryDomain->id,
                'web_property_id' => $property->id,
                'property_analytics_source_id' => null,
                'issue_class' => $issueClass,
                'source_issue_label' => data_get(config('domain_monitor.search_console_issue_catalog.'.$issueClass), 'label'),
                'capture_method' => 'gsc_live_recheck',
                'source_report' => $issueClass === 'page_with_redirect_in_sitemap'
                    ? 'search_console_live_sitemap_recheck'
                    : 'search_console_live_http_recheck',
                'source_property' => $property->searchConsolePropertyUri(),
                'artifact_path' => null,
                'captured_at' => $capturedAt,
                'captured_by' => $capturedBy ?: 'fleet_context_refresh',
                'first_detected_at' => $sourceSnapshot->first_detected_at,
                'last_updated_at' => $sourceSnapshot->last_updated_at,
                'property_scope' => $sourceSnapshot->property_scope,
                'affected_url_count' => count($checks),
                'sample_urls' => array_slice(array_map(
                    static fn (array $check): string => (string) $check['url'],
                    $checks
                ), 0, 10),
                'examples' => $sourceSnapshot->examples,
                'chart_points' => null,
                'normalized_payload' => $normalizedPayload,
                'raw_payload' => $rawPayload,
            ]);
        });

        return [
            'status' => 'refreshed',
            'captured_at' => $capturedAt->toIso8601String(),
            'checked_url_count' => count($checks),
            'reason' => null,
        ];
    }

    private function latestSourceSnapshot(WebProperty $property, string $issueClass): ?SearchConsoleIssueSnapshot
    {
        return SearchConsoleIssueSnapshot::query()
            ->where('web_property_id', $property->id)
            ->where('issue_class', $issueClass)
            ->whereIn('capture_method', ['gsc_drilldown_zip', 'gsc_api', 'gsc_mcp_api'])
            ->orderByRaw("case when capture_method = 'gsc_drilldown_zip' then 0 else 1 end")
            ->orderByDesc('captured_at')
            ->orderByDesc('created_at')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $issueEvidence
     * @return array<int, string>
     */
    private function candidateUrls(array $issueEvidence): array
    {
        $urls = [];

        foreach (['affected_urls', 'sample_urls'] as $key) {
            foreach ((array) ($issueEvidence[$key] ?? []) as $url) {
                if (is_string($url) && $url !== '') {
                    $urls[] = $url;
                }
            }
        }

        foreach ((array) ($issueEvidence['examples'] ?? []) as $example) {
            if (is_array($example) && is_string($example['url'] ?? null) && $example['url'] !== '') {
                $urls[] = $example['url'];
            }
        }

        return array_slice(array_values(array_unique($urls)), 0, self::URL_LIMIT);
    }

    /**
     * @param  array<int, string>  $candidateUrls
     * @return array<int, array{url:string,checked_at:string,final_url:string|null,final_status:int|null,resolved_ok:bool,host_changed:bool|null}>
     */
    private function probeLiveUrls(WebProperty $property, array $candidateUrls): array
    {
        $checks = [];
        $deadline = microtime(true) + self::TOTAL_BUDGET_SECONDS;

        foreach ($candidateUrls as $url) {
            if (microtime(true) >= $deadline) {
                break;
            }

            $checks[] = $this->probeUrl($property, $url);
        }

        return $checks;
    }

    /**
     * @param  array<int, string>  $candidateUrls
     * @return array<int, array{url:string,checked_at:string,present_in_current_sitemap:bool,matched_sitemap:string|null}>|null
     */
    private function probeCurrentSitemapUrls(WebProperty $property, SearchConsoleIssueSnapshot $sourceSnapshot, array $candidateUrls): ?array
    {
        $candidateMap = [];

        foreach ($candidateUrls as $url) {
            $normalizedUrl = $this->normalizeComparableUrl($url);

            if ($normalizedUrl !== null) {
                $candidateMap[$normalizedUrl] = $url;
            }
        }

        if ($candidateMap === []) {
            return null;
        }

        $queue = $this->candidateSitemapUrls($property, $sourceSnapshot);

        if ($queue === []) {
            return null;
        }

        $foundUrls = [];
        $visitedUrls = [];
        $fetchedAnySitemap = false;

        while ($queue !== [] && count($visitedUrls) < self::SITEMAP_FILE_LIMIT) {
            $sitemapUrl = array_shift($queue);
            $normalizedSitemapUrl = $this->normalizeComparableUrl($sitemapUrl);

            if ($normalizedSitemapUrl === null || isset($visitedUrls[$normalizedSitemapUrl])) {
                continue;
            }

            $visitedUrls[$normalizedSitemapUrl] = true;

            $body = $this->fetchSitemapBody($property, $sitemapUrl);

            if ($body === null) {
                continue;
            }

            $fetchedAnySitemap = true;
            $document = $this->parseSitemapDocument($body);

            if ($document === null) {
                continue;
            }

            foreach ($document['urls'] as $url) {
                $normalizedUrl = $this->normalizeComparableUrl($url);

                if ($normalizedUrl === null || ! isset($candidateMap[$normalizedUrl])) {
                    continue;
                }

                $foundUrls[$candidateMap[$normalizedUrl]] = $sitemapUrl;
            }

            if (count($foundUrls) === count($candidateMap)) {
                break;
            }

            foreach ($document['sitemaps'] as $childSitemapUrl) {
                if ($this->isSafeSitemapUrl($property, $childSitemapUrl)) {
                    $queue[] = $childSitemapUrl;
                }
            }
        }

        if (! $fetchedAnySitemap) {
            return null;
        }

        $checkedAt = now()->toIso8601String();

        return array_map(
            fn (string $url): array => [
                'url' => $url,
                'checked_at' => $checkedAt,
                'present_in_current_sitemap' => array_key_exists($url, $foundUrls),
                'matched_sitemap' => $foundUrls[$url] ?? null,
            ],
            $candidateUrls
        );
    }

    /**
     * @return array<int, string>
     */
    private function candidateSitemapUrls(WebProperty $property, SearchConsoleIssueSnapshot $sourceSnapshot): array
    {
        $urls = [];
        $sourceEvidence = $sourceSnapshot->issueEvidence();

        foreach ((array) ($sourceEvidence['sitemaps'] ?? []) as $sitemap) {
            $path = is_array($sitemap) ? ($sitemap['path'] ?? null) : null;

            if (is_string($path) && $path !== '' && $this->isSafeSitemapUrl($property, $path)) {
                $urls[] = $path;
            }
        }

        $baseUrl = $this->propertyBaseUrl($property);

        if ($baseUrl !== null) {
            foreach (['/sitemap.xml', '/sitemap_index.xml', '/sitemaps.xml'] as $path) {
                $urls[] = $baseUrl.$path;
            }
        }

        return array_values(array_unique($urls));
    }

    private function propertyBaseUrl(WebProperty $property): ?string
    {
        $scheme = is_string($property->canonical_origin_scheme) && $property->canonical_origin_scheme !== ''
            ? Str::lower($property->canonical_origin_scheme)
            : null;
        $host = is_string($property->canonical_origin_host) && $property->canonical_origin_host !== ''
            ? Str::lower($property->canonical_origin_host)
            : null;

        if ($scheme !== null && $host !== null) {
            return $scheme.'://'.$host;
        }

        $productionUrl = is_string($property->production_url) && $property->production_url !== ''
            ? $property->production_url
            : null;

        if ($productionUrl !== null) {
            $productionParts = parse_url($productionUrl);

            if (is_array($productionParts) && isset($productionParts['scheme'], $productionParts['host'])) {
                return Str::lower((string) $productionParts['scheme']).'://'.Str::lower((string) $productionParts['host']);
            }
        }

        $primaryDomain = $property->primaryDomainModel();

        if ($primaryDomain instanceof Domain && $primaryDomain->domain !== '') {
            return 'https://'.Str::lower($primaryDomain->domain);
        }

        return null;
    }

    private function fetchSitemapBody(WebProperty $property, string $url): ?string
    {
        if (! $this->isSafeSitemapUrl($property, $url)) {
            return null;
        }

        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->withHeaders([
                    'Accept' => 'application/xml,text/xml,text/plain,*/*',
                    'User-Agent' => 'DomainMonitorSearchConsoleRecheck/1.0 (+https://monitor.again.com.au)',
                ])
                ->get($url);
        } catch (\Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $body = $response->body();

        return trim($body) !== '' ? $body : null;
    }

    /**
     * @return array{urls: array<int, string>, sitemaps: array<int, string>}|null
     */
    private function parseSitemapDocument(string $body): ?array
    {
        $previousInternalErrors = libxml_use_internal_errors(true);
        $document = simplexml_load_string($body);
        libxml_clear_errors();
        libxml_use_internal_errors($previousInternalErrors);

        if (! $document instanceof \SimpleXMLElement) {
            return null;
        }

        $urlNodes = $document->xpath('//*[local-name()="url"]/*[local-name()="loc"]');
        $sitemapNodes = $document->xpath('//*[local-name()="sitemap"]/*[local-name()="loc"]');

        return [
            'urls' => collect(is_array($urlNodes) ? $urlNodes : [])
                ->map(fn (mixed $node): string => trim((string) $node))
                ->filter(fn (string $url): bool => $url !== '')
                ->values()
                ->all(),
            'sitemaps' => collect(is_array($sitemapNodes) ? $sitemapNodes : [])
                ->map(fn (mixed $node): string => trim((string) $node))
                ->filter(fn (string $url): bool => $url !== '')
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array{url:string,checked_at:string,final_url:string|null,final_status:int|null,resolved_ok:bool,host_changed:bool|null}
     */
    private function probeUrl(WebProperty $property, string $url): array
    {
        $originalHost = parse_url($url, PHP_URL_HOST);
        $currentUrl = $url;
        $lastStatus = null;
        $resolvedUrl = null;

        for ($attempt = 0; $attempt <= self::REDIRECT_LIMIT; $attempt++) {
            if (! $this->isSafeProbeUrl($property, $currentUrl)) {
                break;
            }

            try {
                $response = Http::timeout(self::TIMEOUT_SECONDS)
                    ->withoutRedirecting()
                    ->withHeaders([
                        'Accept' => 'text/html,application/xhtml+xml',
                        'User-Agent' => 'DomainMonitorSearchConsoleRecheck/1.0 (+https://monitor.again.com.au)',
                    ])
                    ->get($currentUrl);
            } catch (\Throwable) {
                break;
            }

            $lastStatus = $response->status();
            $location = $response->header('Location');

            if ($lastStatus >= 300 && $lastStatus < 400 && $location !== '' && $attempt < self::REDIRECT_LIMIT) {
                $nextUrl = $this->normalizeUrl($location, $currentUrl);

                if ($nextUrl === null) {
                    break;
                }

                $currentUrl = $nextUrl;

                continue;
            }

            $resolvedUrl = $this->sanitizeUrl($currentUrl);

            break;
        }

        $resolvedHost = is_string($resolvedUrl) ? parse_url($resolvedUrl, PHP_URL_HOST) : null;

        return [
            'url' => $url,
            'checked_at' => now()->toIso8601String(),
            'final_url' => $resolvedUrl,
            'final_status' => $lastStatus,
            'resolved_ok' => $lastStatus !== null && $lastStatus >= 200 && $lastStatus < 300,
            'host_changed' => is_string($originalHost) && is_string($resolvedHost)
                ? Str::lower($originalHost) !== Str::lower($resolvedHost)
                : null,
        ];
    }

    private function isSafeProbeUrl(WebProperty $property, string $url): bool
    {
        $parts = parse_url($url);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return false;
        }

        $scheme = Str::lower((string) $parts['scheme']);
        $host = Str::lower((string) $parts['host']);

        if (! in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return filter_var(
                $host,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            ) !== false;
        }

        if (! str_contains($host, '.') || Str::endsWith($host, ['.local', '.internal'])) {
            return false;
        }

        return (in_array($host, $this->knownHosts($property), true) || $this->isManagedHost($host))
            && $this->hostResolvesPublicly($host);
    }

    private function isSafeSitemapUrl(WebProperty $property, string $url): bool
    {
        $parts = parse_url($url);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return false;
        }

        $scheme = Str::lower((string) $parts['scheme']);
        $host = Str::lower((string) $parts['host']);

        if (! in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        return in_array($host, $this->knownHosts($property), true) && $this->hostResolvesPublicly($host);
    }

    private function normalizeUrl(string $url, string $baseUrl): ?string
    {
        if (preg_match('#^https?://#i', $url) === 1) {
            return $url;
        }

        $baseParts = parse_url($baseUrl);

        if (! is_array($baseParts) || ! isset($baseParts['scheme'], $baseParts['host'])) {
            return null;
        }

        $scheme = Str::lower((string) $baseParts['scheme']);
        $host = (string) $baseParts['host'];
        $port = isset($baseParts['port']) ? ':'.$baseParts['port'] : '';

        if (Str::startsWith($url, '//')) {
            return $scheme.':'.$url;
        }

        if (Str::startsWith($url, '/')) {
            return $scheme.'://'.$host.$port.$url;
        }

        $basePath = (string) ($baseParts['path'] ?? '/');
        $baseDirectory = rtrim(str_replace('\\', '/', dirname($basePath)), '/');
        $baseDirectory = $baseDirectory === '' ? '' : $baseDirectory;

        return $scheme.'://'.$host.$port.$baseDirectory.'/'.$url;
    }

    private function sanitizeUrl(string $url): ?string
    {
        $parts = parse_url($url);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        $sanitizedUrl = Str::lower((string) $parts['scheme']).'://'.$parts['host'];

        if (isset($parts['port'])) {
            $sanitizedUrl .= ':'.$parts['port'];
        }

        $path = (string) ($parts['path'] ?? '/');

        return $sanitizedUrl.($path !== '' ? $path : '/');
    }

    private function normalizeComparableUrl(?string $url): ?string
    {
        if (! is_string($url) || $url === '') {
            return null;
        }

        $parts = parse_url($url);

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

    private function hostResolvesPublicly(string $host): bool
    {
        if (app()->runningUnitTests()) {
            return true;
        }

        $records = dns_get_record($host, DNS_A | DNS_AAAA);

        if (! is_array($records) || $records === []) {
            return false;
        }

        foreach ($records as $record) {
            $ipAddress = $record['ip'] ?? $record['ipv6'] ?? null;

            if (! is_string($ipAddress)) {
                continue;
            }

            if (filter_var(
                $ipAddress,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            ) === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<int, string>
     */
    private function knownHosts(WebProperty $property): array
    {
        $hosts = [];

        if ($property->primaryDomain instanceof Domain && $property->primaryDomain->domain !== '') {
            $hosts[] = Str::lower($property->primaryDomain->domain);
        }

        foreach ($property->propertyDomains as $link) {
            if ($link->domain instanceof Domain && $link->domain->domain !== '') {
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

    private function isManagedHost(string $host): bool
    {
        if (array_key_exists($host, $this->managedHostCache)) {
            return $this->managedHostCache[$host];
        }

        $domainExists = Domain::query()
            ->whereRaw('LOWER(domain) = ?', [$host])
            ->where('is_active', true)
            ->exists();

        if ($domainExists) {
            return $this->managedHostCache[$host] = true;
        }

        $subdomain = Subdomain::query()
            ->whereRaw('LOWER(full_domain) = ?', [$host])
            ->where('is_active', true)
            ->first();

        return $this->managedHostCache[$host] = $subdomain instanceof Subdomain
            && $subdomain->expectsIpResolution();
    }
}
