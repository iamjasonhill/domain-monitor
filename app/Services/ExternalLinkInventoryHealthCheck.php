<?php

namespace App\Services;

use DOMDocument;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ExternalLinkInventoryHealthCheck
{
    /** @var array<string, bool> */
    private array $visitedPages = [];

    /**
     * @var array<string, array{
     *   url: string,
     *   host: string|null,
     *   relationship: string,
     *   found_on: string|null,
     *   found_on_pages: array<int, string>
     * }>
     */
    private array $externalLinks = [];

    private int $pagesScanned = 0;

    private int $pageFailures = 0;

    private ?string $lastPageFailureMessage = null;

    private int $maxPages = 50;

    private string $baseUrl;

    private string $scheme;

    private string $host;

    /**
     * @return array{
     *   is_valid: bool,
     *   verified: bool,
     *   external_links_count: int,
     *   pages_scanned: int,
     *   external_links: array<int, array{
     *     url: string,
     *     host: string|null,
     *     relationship: string,
     *     found_on: string|null,
     *     found_on_pages: array<int, string>
     *   }>,
     *   error_message: string|null,
     *   payload: array<string, mixed>
     * }
     */
    public function check(string $domain, int $timeout = 10): array
    {
        $startTime = microtime(true);
        $this->visitedPages = [];
        $this->externalLinks = [];
        $this->pagesScanned = 0;
        $this->pageFailures = 0;
        $this->lastPageFailureMessage = null;

        if (! Str::startsWith($domain, ['http://', 'https://'])) {
            $domain = 'https://'.$domain;
        }

        $this->baseUrl = rtrim($domain, '/');
        $parsedUrl = parse_url($this->baseUrl);
        $this->scheme = strtolower((string) ($parsedUrl['scheme'] ?? 'https'));
        $this->host = strtolower((string) ($parsedUrl['host'] ?? ''));

        try {
            $this->crawl($this->baseUrl, $timeout);

            $duration = (int) ((microtime(true) - $startTime) * 1000);
            $externalLinks = $this->externalLinks();
            $verified = $this->pagesScanned > 0;
            $errorMessage = $verified
                ? null
                : ($this->lastPageFailureMessage ?? 'Unable to crawl any pages for verification');

            return [
                'is_valid' => $verified && $this->pageFailures === 0,
                'verified' => $verified,
                'external_links_count' => count($externalLinks),
                'pages_scanned' => $this->pagesScanned,
                'external_links' => $externalLinks,
                'error_message' => $errorMessage,
                'payload' => [
                    'domain' => $this->host,
                    'pages_scanned' => $this->pagesScanned,
                    'external_links_count' => count($externalLinks),
                    'unique_hosts_count' => count(array_unique(array_values(array_filter(
                        array_map(
                            static fn (array $item): ?string => is_string($item['host'] ?? null) ? $item['host'] : null,
                            $externalLinks
                        ),
                        static fn (?string $host): bool => is_string($host) && $host !== ''
                    )))),
                    'page_failures_count' => $this->pageFailures,
                    'external_links' => $externalLinks,
                    'duration_ms' => $duration,
                ],
            ];
        } catch (\Throwable $exception) {
            $duration = (int) ((microtime(true) - $startTime) * 1000);

            Log::error('External link inventory check failed', [
                'domain' => $domain,
                'error' => $exception->getMessage(),
            ]);

            return [
                'is_valid' => false,
                'verified' => false,
                'external_links_count' => 0,
                'pages_scanned' => $this->pagesScanned,
                'external_links' => [],
                'error_message' => 'Check failed: '.$exception->getMessage(),
                'payload' => [
                    'domain' => $this->host,
                    'pages_scanned' => $this->pagesScanned,
                    'external_links_count' => 0,
                    'unique_hosts_count' => 0,
                    'page_failures_count' => $this->pageFailures,
                    'external_links' => [],
                    'error_type' => 'exception',
                    'duration_ms' => $duration,
                ],
            ];
        }
    }

    private function crawl(string $url, int $timeout): void
    {
        if ($this->pagesScanned >= $this->maxPages) {
            return;
        }

        $normalizedPageUrl = $this->normalizeDocumentUrl($url);

        if ($normalizedPageUrl === null || isset($this->visitedPages[$normalizedPageUrl]) || ! $this->isInternal($normalizedPageUrl)) {
            return;
        }

        $this->visitedPages[$normalizedPageUrl] = true;

        try {
            /** @var \Illuminate\Http\Client\Response $response */
            $response = Http::timeout($timeout)->get($normalizedPageUrl);
            if ($response->status() >= 400) {
                $this->pageFailures++;
                $this->lastPageFailureMessage = 'Unable to crawl '.$normalizedPageUrl.' (HTTP '.$response->status().')';

                return;
            }

            $contentType = strtolower((string) $response->header('Content-Type'));
            if (! Str::contains($contentType, 'text/html')) {
                return;
            }

            $this->pagesScanned++;
            $this->extractLinks($response->body(), $normalizedPageUrl, $timeout);
        } catch (\Throwable $exception) {
            $this->pageFailures++;
            $this->lastPageFailureMessage = $exception->getMessage();

            Log::warning('External link inventory page crawl failed', [
                'domain' => $this->host,
                'page_url' => $normalizedPageUrl,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function extractLinks(string $html, string $currentUrl, int $timeout): void
    {
        $dom = new DOMDocument;
        @$dom->loadHTML($html);

        foreach ($dom->getElementsByTagName('a') as $link) {
            /** @var \DOMElement $link */
            $href = trim($link->getAttribute('href'));
            $normalizedUrl = $this->normalizeUrl($href, $currentUrl);

            if ($normalizedUrl === null) {
                continue;
            }

            if ($this->isInternal($normalizedUrl)) {
                $this->crawl($normalizedUrl, $timeout);

                continue;
            }

            $this->recordExternalLink($normalizedUrl, $currentUrl);
        }
    }

    private function recordExternalLink(string $url, string $foundOn): void
    {
        $key = $url;
        $host = parse_url($url, PHP_URL_HOST);
        $normalizedHost = is_string($host) && $host !== '' ? strtolower($host) : null;

        if (! isset($this->externalLinks[$key])) {
            $this->externalLinks[$key] = [
                'url' => $url,
                'host' => $normalizedHost,
                'relationship' => $this->relationshipForHost($normalizedHost),
                'found_on' => $foundOn,
                'found_on_pages' => [$foundOn],
            ];

            return;
        }

        if (! in_array($foundOn, $this->externalLinks[$key]['found_on_pages'], true)) {
            $this->externalLinks[$key]['found_on_pages'][] = $foundOn;
        }
    }

    private function relationshipForHost(?string $host): string
    {
        if ($host === null || $host === '') {
            return 'external';
        }

        if (str_ends_with($host, '.'.$this->host)) {
            return 'subdomain';
        }

        if (str_ends_with($this->host, '.'.$host)) {
            return 'parent_domain';
        }

        return 'external';
    }

    private function normalizeUrl(string $href, string $currentUrl): ?string
    {
        if ($href === '' || Str::startsWith($href, ['#', 'javascript:', 'mailto:', 'tel:'])) {
            return null;
        }

        if (Str::startsWith($href, '//')) {
            return $this->sanitizeAbsoluteUrl($this->scheme.':'.$href);
        }

        if (Str::startsWith($href, ['http://', 'https://'])) {
            return $this->sanitizeAbsoluteUrl($href);
        }

        $currentParts = parse_url($currentUrl);
        if (! is_array($currentParts) || ! isset($currentParts['scheme'], $currentParts['host'])) {
            return null;
        }

        $origin = strtolower((string) $currentParts['scheme']).'://'.$currentParts['host'];
        if (isset($currentParts['port'])) {
            $origin .= ':'.$currentParts['port'];
        }

        if (Str::startsWith($href, '/')) {
            return $this->sanitizeAbsoluteUrl($origin.$href);
        }

        $path = (string) ($currentParts['path'] ?? '/');
        $directory = str_replace('\\', '/', dirname($path));
        $directory = $directory === '.' ? '/' : $directory;

        return $this->sanitizeAbsoluteUrl($origin.$this->normalizePath(
            rtrim($directory, '/').'/'.$href
        ));
    }

    private function normalizeDocumentUrl(string $url): ?string
    {
        return $this->sanitizeAbsoluteUrl($url);
    }

    private function sanitizeAbsoluteUrl(string $url): ?string
    {
        $parts = parse_url($url);
        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        $scheme = strtolower((string) $parts['scheme']);
        if (! in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        $sanitized = $scheme.'://'.strtolower((string) $parts['host']);

        if (isset($parts['port'])) {
            $sanitized .= ':'.$parts['port'];
        }

        $sanitized .= $this->normalizePath((string) ($parts['path'] ?? '/'));

        return $sanitized;
    }

    private function normalizePath(string $path): string
    {
        $segments = [];
        $isAbsolute = Str::startsWith($path, '/');

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                array_pop($segments);

                continue;
            }

            $segments[] = $segment;
        }

        $normalizedPath = ($isAbsolute ? '/' : '').implode('/', $segments);

        return $normalizedPath === '' ? '/' : $normalizedPath;
    }

    private function isInternal(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) && strtolower($host) === $this->host;
    }

    /**
     * @return array<int, array{
     *   url: string,
     *   host: string|null,
     *   relationship: string,
     *   found_on: string|null,
     *   found_on_pages: array<int, string>
     * }>
     */
    private function externalLinks(): array
    {
        $externalLinks = array_values($this->externalLinks);

        usort($externalLinks, static fn (array $left, array $right): int => strcmp($left['url'], $right['url']));

        return $externalLinks;
    }
}
