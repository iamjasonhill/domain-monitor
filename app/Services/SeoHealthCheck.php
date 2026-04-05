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
     *     verified: bool,
     *     results: array{
     *         robots: array{
     *             exists: bool,
     *             verified: bool,
     *             status: int,
     *             url: string,
     *             error: string|null,
     *             has_standard_wordpress_admin_rule: bool,
     *             allow_admin_ajax: bool
     *         },
     *         sitemap: array{exists: bool, verified: bool, status: int, url: string, error: string|null}
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
            $robotsResult = $this->checkRobotsFile($baseUrl);

            $sitemapResult = $this->checkFile($baseUrl, '/sitemap.xml');

            if (! $sitemapResult['exists']) {
                $altSitemapResult = $this->checkFile($baseUrl, '/sitemap_index.xml');
                if ($altSitemapResult['exists']) {
                    $sitemapResult = $altSitemapResult;
                }
            }

            $isValid = $robotsResult['exists'];

            $duration = (int) ((microtime(true) - $startTime) * 1000);

            return [
                'is_valid' => $isValid,
                'verified' => $robotsResult['verified'] || $sitemapResult['verified'],
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
                'verified' => false,
                'results' => [
                    'robots' => [
                        'exists' => false,
                        'verified' => false,
                        'status' => 0,
                        'url' => '',
                        'error' => 'Check failed',
                        'has_standard_wordpress_admin_rule' => false,
                        'allow_admin_ajax' => false,
                    ],
                    'sitemap' => ['exists' => false, 'verified' => false, 'status' => 0, 'url' => '', 'error' => 'Check failed'],
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
     * @return array{
     *     exists: bool,
     *     verified: bool,
     *     status: int,
     *     url: string,
     *     error: string|null,
     *     has_standard_wordpress_admin_rule: bool,
     *     allow_admin_ajax: bool
     * }
     */
    private function checkRobotsFile(string $baseUrl): array
    {
        $url = $baseUrl.'/robots.txt';

        try {
            $response = Http::timeout(10)
                ->withHeaders(['User-Agent' => 'DomainMonitor/1.0'])
                ->get($url);

            /** @var \Illuminate\Http\Client\Response $response */
            $status = $response->status();
            $exists = $response->successful();
            $body = $exists ? $response->body() : '';

            return [
                'exists' => $exists,
                'verified' => true,
                'status' => $status,
                'url' => $url,
                'error' => $exists ? null : "Returned status {$status}",
                'has_standard_wordpress_admin_rule' => $exists
                    && preg_match('/^\s*disallow:\s*\/wp-admin\/\s*$/im', $body) === 1,
                'allow_admin_ajax' => $exists
                    && preg_match('/^\s*allow:\s*\/wp-admin\/admin-ajax\.php\s*$/im', $body) === 1,
            ];
        } catch (Exception $e) {
            return [
                'exists' => false,
                'verified' => false,
                'status' => 0,
                'url' => $url,
                'error' => $e->getMessage(),
                'has_standard_wordpress_admin_rule' => false,
                'allow_admin_ajax' => false,
            ];
        }
    }

    /**
     * @return array{exists: bool, verified: bool, status: int, url: string, error: string|null}
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
                'verified' => true,
                'status' => $status,
                'url' => $url,
                'error' => $exists ? null : "Returned status {$status}",
            ];
        } catch (Exception $e) {
            return [
                'exists' => false,
                'verified' => false,
                'status' => 0,
                'url' => $url,
                'error' => $e->getMessage(),
            ];
        }
    }
}
