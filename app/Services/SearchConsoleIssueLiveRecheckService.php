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
    private const ISSUE_CLASS = 'not_found_404';

    private const REDIRECT_LIMIT = 5;

    private const TIMEOUT_SECONDS = 5;

    private const URL_LIMIT = 10;

    private const TOTAL_BUDGET_SECONDS = 20;

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

        try {
            $sourceSnapshot = $this->latestSourceSnapshot($property);

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

            $primaryDomain = $property->primaryDomainModel();

            if (! $primaryDomain instanceof \App\Models\Domain) {
                return [
                    'status' => 'skipped',
                    'captured_at' => null,
                    'checked_url_count' => 0,
                    'reason' => 'missing',
                ];
            }

            $checks = [];
            $deadline = microtime(true) + self::TOTAL_BUDGET_SECONDS;

            foreach ($candidateUrls as $url) {
                if (microtime(true) >= $deadline) {
                    break;
                }

                $checks[] = $this->probeUrl($property, $url);
            }

            $capturedAt = now();

            DB::transaction(function () use ($property, $primaryDomain, $sourceSnapshot, $capturedAt, $capturedBy, $checks): void {
                SearchConsoleIssueSnapshot::query()
                    ->where('web_property_id', $property->id)
                    ->where('issue_class', self::ISSUE_CLASS)
                    ->where('capture_method', 'gsc_live_recheck')
                    ->delete();

                SearchConsoleIssueSnapshot::query()->create([
                    'domain_id' => $primaryDomain->id,
                    'web_property_id' => $property->id,
                    'property_analytics_source_id' => null,
                    'issue_class' => self::ISSUE_CLASS,
                    'source_issue_label' => data_get(config('domain_monitor.search_console_issue_catalog.'.self::ISSUE_CLASS), 'label'),
                    'capture_method' => 'gsc_live_recheck',
                    'source_report' => 'search_console_live_http_recheck',
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
                    'normalized_payload' => [
                        'affected_urls' => array_map(
                            static fn (array $check): string => (string) $check['url'],
                            $checks
                        ),
                        'live_url_checks' => $checks,
                    ],
                    'raw_payload' => [
                        'source_snapshot_id' => $sourceSnapshot->id,
                        'live_url_checks' => $checks,
                    ],
                ]);
            });

            return [
                'status' => 'refreshed',
                'captured_at' => $capturedAt->toIso8601String(),
                'checked_url_count' => count($checks),
                'reason' => null,
            ];
        } catch (\Throwable $exception) {
            Log::warning('Search Console live URL recheck failed', [
                'web_property_id' => $property->id,
                'property_slug' => $property->slug,
                'issue_class' => self::ISSUE_CLASS,
                'exception' => $exception->getMessage(),
            ]);

            return [
                'status' => 'failed',
                'captured_at' => null,
                'checked_url_count' => 0,
                'reason' => 'live_recheck_failed',
            ];
        }
    }

    private function latestSourceSnapshot(WebProperty $property): ?SearchConsoleIssueSnapshot
    {
        return SearchConsoleIssueSnapshot::query()
            ->where('web_property_id', $property->id)
            ->where('issue_class', self::ISSUE_CLASS)
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

        if ($property->primaryDomain instanceof \App\Models\Domain && $property->primaryDomain->domain !== '') {
            $hosts[] = Str::lower($property->primaryDomain->domain);
        }

        foreach ($property->propertyDomains as $link) {
            if ($link->domain instanceof \App\Models\Domain && $link->domain->domain !== '') {
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
            ->where('domain', $host)
            ->where('is_active', true)
            ->exists();

        if ($domainExists) {
            return $this->managedHostCache[$host] = true;
        }

        $subdomain = Subdomain::query()
            ->where('full_domain', $host)
            ->where('is_active', true)
            ->first();

        return $this->managedHostCache[$host] = $subdomain instanceof Subdomain
            && $subdomain->expectsIpResolution();
    }
}
