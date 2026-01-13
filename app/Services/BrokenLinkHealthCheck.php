<?php

namespace App\Services;

use DOMDocument;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class BrokenLinkHealthCheck
{
    /** @var array<string, bool> */
    private array $visited = [];

    /** @var array<int, array{url: string, status: int, found_on: string, error?: string}> */
    private array $brokenLinks = [];

    private int $pagesScanned = 0;

    private int $maxPages = 50;

    private string $baseUrl;

    private string $host;

    /**
     * Perform Broken Link check for a domain
     *
     * @param  string  $domain  Domain name (with or without protocol)
     * @param  int  $timeout  Timeout in seconds (default 10 per request)
     * @return array{is_valid: bool, broken_links_count: int, pages_scanned: int, broken_links: array<int, array{url: string, status: int, found_on: string}>, error_message: string|null, payload: array<string, mixed>}
     */
    public function check(string $domain, int $timeout = 10): array
    {
        $startTime = microtime(true);
        $this->visited = [];
        $this->brokenLinks = [];
        $this->pagesScanned = 0;

        // Ensure protocol
        if (! Str::startsWith($domain, ['http://', 'https://'])) {
            $domain = 'https://'.$domain;
        }

        $this->baseUrl = rtrim($domain, '/');
        $parsedUrl = parse_url($this->baseUrl);
        $this->host = $parsedUrl['host'] ?? $domain;

        try {
            $this->crawl($this->baseUrl, $timeout);

            $duration = (int) ((microtime(true) - $startTime) * 1000);
            $brokenCount = count($this->brokenLinks);

            return [
                'is_valid' => $brokenCount === 0,
                'broken_links_count' => $brokenCount,
                'pages_scanned' => $this->pagesScanned,
                'broken_links' => $this->brokenLinks,
                'error_message' => null,
                'payload' => [
                    'domain' => $this->host,
                    'broken_links_count' => $brokenCount,
                    'pages_scanned' => $this->pagesScanned,
                    'broken_links' => $this->brokenLinks,
                    'duration_ms' => $duration,
                ],
            ];
        } catch (\Exception $e) {
            $duration = (int) ((microtime(true) - $startTime) * 1000);
            Log::error('Broken Link Check failed', ['domain' => $domain, 'error' => $e->getMessage()]);

            return [
                'is_valid' => false,
                'broken_links_count' => 0,
                'pages_scanned' => $this->pagesScanned,
                'broken_links' => [],
                'error_message' => 'Check failed: '.$e->getMessage(),
                'payload' => [
                    'domain' => $this->host,
                    'error_type' => 'exception',
                    'duration_ms' => $duration,
                ],
            ];
        }
    }

    private function crawl(string $url, int $timeout, string $foundOn = 'Homepage'): void
    {
        if ($this->pagesScanned >= $this->maxPages) {
            return;
        }

        if (isset($this->visited[$url])) {
            return;
        }

        $this->visited[$url] = true;

        // Verify it's an internal link before crawling content
        if (! $this->isInternal($url)) {
            // Just check status for external links (HEAD request)
            $this->checkLinkStatus($url, $timeout, $foundOn);

            return;
        }

        // It is internal, so we fetch and parse
        try {
            /** @var \Illuminate\Http\Client\Response $response */
            $response = Http::timeout($timeout)->get($url);
            $status = $response->status();
            $contentType = $response->header('Content-Type');

            if ($status >= 400) {
                $this->brokenLinks[] = [
                    'url' => $url,
                    'status' => $status,
                    'found_on' => $foundOn,
                ];

                return;
            }

            // Only parse HTML
            if ($status === 200 && Str::contains($contentType, 'text/html')) {
                $this->pagesScanned++;
                $this->extractAndCrawlLinks($response->body(), $url, $timeout);
            }
        } catch (\Exception $e) {
            $this->brokenLinks[] = [
                'url' => $url,
                'status' => 0, // 0 usually indicates connection failure
                'found_on' => $foundOn,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function checkLinkStatus(string $url, int $timeout, string $foundOn): void
    {
        try {
            /** @var \Illuminate\Http\Client\Response $response */
            $response = Http::timeout($timeout)->head($url);
            $status = $response->status();

            if ($status >= 400) {
                // Determine if 405 Method Not Allowed, try GET if so (some servers block HEAD)
                if ($status === 405) {
                    /** @var \Illuminate\Http\Client\Response $response */
                    $response = Http::timeout($timeout)->get($url);
                    $status = $response->status();
                }

                if ($status >= 400) {
                    $this->brokenLinks[] = [
                        'url' => $url,
                        'status' => $status,
                        'found_on' => $foundOn,
                    ];
                }
            }
        } catch (\Exception $e) {
            $this->brokenLinks[] = [
                'url' => $url,
                'status' => 0,
                'found_on' => $foundOn,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function extractAndCrawlLinks(string $html, string $currentUrl, int $timeout): void
    {
        $dom = new DOMDocument;
        // Suppress warnings for malformed HTML
        @$dom->loadHTML($html);

        $links = $dom->getElementsByTagName('a');

        foreach ($links as $link) {
            /** @var \DOMElement $link */
            $href = $link->getAttribute('href');

            // Normalize URL
            $normalizedUrl = $this->normalizeUrl($href, $currentUrl);

            if ($normalizedUrl && ! isset($this->visited[$normalizedUrl])) {
                // If internal, add to crawl queue (recursion)
                // We only recurse on internal links
                if ($this->isInternal($normalizedUrl)) {
                    $this->crawl($normalizedUrl, $timeout, $currentUrl);
                } else {
                    // Check external link immediately (no recursion)
                    $this->checkLinkStatus($normalizedUrl, $timeout, $currentUrl);
                    $this->visited[$normalizedUrl] = true; // Mark external as visited so we don't check again
                }
            }
        }
    }

    private function normalizeUrl(string $href, string $currentUrl): ?string
    {
        if (empty($href) || Str::startsWith($href, ['#', 'javascript:', 'mailto:', 'tel:'])) {
            return null;
        }

        // Handle absolute URLs
        if (Str::startsWith($href, ['http://', 'https://'])) {
            return $href;
        }

        // Handle root-relative URLs
        if (Str::startsWith($href, '/')) {
            return $this->baseUrl.$href;
        }

        // Handle relative URLs (naive implementation, assumes $currentUrl is base)
        // Ideally should handle ../ logic but for basic crawl this might suffice
        return rtrim($this->baseUrl, '/').'/'.ltrim($href, '/');
    }

    private function isInternal(string $url): bool
    {
        $parsed = parse_url($url);
        $host = $parsed['host'] ?? '';

        return $host === $this->host || empty($host); // Empty host implies relative path which is internal
    }
}
