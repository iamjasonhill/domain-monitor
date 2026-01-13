<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;

class SeoHealthCheck
{
    /**
     * Perform SEO Fundamentals health check
     *
     * @return array{
     *     is_valid: bool,
     *     results: array{
     *         robots: array{exists: bool, status: int, url: string, error: string|null},
     *         sitemap: array{exists: bool, status: int, url: string, error: string|null}
     *     },
     *     error_message: string|null,
     *     payload: array<string, mixed>
     * }
     */
    public function check(string $domain): array
    {
        $startTime = microtime(true);
        $baseUrl = $this->normalizeUrl($domain);

        try {
            // Check robots.txt
            $robotsResult = $this->checkFile($baseUrl, '/robots.txt');

            // Check sitemap.xml (try common locations if standard one fails, or just standard for now)
            // For MVP, we just check /sitemap.xml. A more advanced version could parse robots.txt for the sitemap URL.
            $sitemapResult = $this->checkFile($baseUrl, '/sitemap.xml');

            // If sitemap not found at root, try sitemap_index.xml (common in WordPress/Yoast)
            if (! $sitemapResult['exists']) {
                $altSitemapResult = $this->checkFile($baseUrl, '/sitemap_index.xml');
                if ($altSitemapResult['exists']) {
                    $sitemapResult = $altSitemapResult;
                }
            }

            // Consider valid if robots.txt exists (crucial). Sitemap is important but sometimes named differently.
            // Let's set is_valid to true if robots.txt exists.
            $isValid = $robotsResult['exists'];

            $duration = (int) ((microtime(true) - $startTime) * 1000);

            return [
                'is_valid' => $isValid,
                'results' => [
                    'robots' => $robotsResult,
                    'sitemap' => $sitemapResult,
                ],
                'error_message' => null,
                'payload' => [
                    'domain' => $domain,
                    'results' => [
                        'robots' => $robotsResult,
                        'sitemap' => $sitemapResult,
                    ],
                    'duration_ms' => $duration,
                ],
            ];
        } catch (Exception $e) {
            return [
                'is_valid' => false,
                'results' => [
                    'robots' => ['exists' => false, 'status' => 0, 'url' => '', 'error' => 'Check failed'],
                    'sitemap' => ['exists' => false, 'status' => 0, 'url' => '', 'error' => 'Check failed'],
                ],
                'error_message' => 'Exception: '.$e->getMessage(),
                'payload' => [
                    'domain' => $domain,
                    'error' => $e->getMessage(),
                    'duration_ms' => (int) ((microtime(true) - $startTime) * 1000),
                ],
            ];
        }
    }

    private function normalizeUrl(string $domain): string
    {
        if (str_starts_with($domain, 'http://') || str_starts_with($domain, 'https://')) {
            return rtrim($domain, '/');
        }

        return 'https://'.rtrim($domain, '/');
    }

    /**
     * @return array{exists: bool, status: int, url: string, error: string|null}
     */
    private function checkFile(string $baseUrl, string $path): array
    {
        $url = $baseUrl.$path;

        try {
            $response = Http::timeout(10)
                ->withHeaders(['User-Agent' => 'DomainMonitor/1.0'])
                ->get($url);

            /** @var \Illuminate\Http\Client\Response $response */
            $status = $response->status();
            $exists = $response->successful(); // 2xx status

            return [
                'exists' => $exists,
                'status' => $status,
                'url' => $url,
                'error' => $exists ? null : "Returned status {$status}",
            ];
        } catch (Exception $e) {
            return [
                'exists' => false,
                'status' => 0,
                'url' => $url,
                'error' => $e->getMessage(),
            ];
        }
    }
}
