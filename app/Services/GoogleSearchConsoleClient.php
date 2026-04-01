<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GoogleSearchConsoleClient
{
    public function __construct(
        private readonly GoogleSearchConsoleTokenProvider $tokenProvider,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listSites(): array
    {
        $response = $this->request()
            ->get($this->apiBaseUrl().'/webmasters/v3/sites');

        $this->throwIfFailed($response->status(), $response->json(), 'list Search Console sites');

        $sites = $response->json('siteEntry');

        return is_array($sites) ? array_values(array_filter($sites, 'is_array')) : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listSitemaps(string $siteUrl): array
    {
        $response = $this->request()
            ->get($this->apiBaseUrl().'/webmasters/v3/sites/'.rawurlencode($siteUrl).'/sitemaps');

        $this->throwIfFailed($response->status(), $response->json(), 'list Search Console sitemaps');

        $sitemaps = $response->json('sitemap');

        return is_array($sitemaps) ? array_values(array_filter($sitemaps, 'is_array')) : [];
    }

    /**
     * @param  array<int, string>  $dimensions
     * @return array<string, mixed>
     */
    public function querySearchAnalytics(
        string $siteUrl,
        string $startDate,
        string $endDate,
        int $rowLimit = 250,
        array $dimensions = ['page'],
        string $type = 'web'
    ): array {
        $response = $this->request()
            ->post($this->apiBaseUrl().'/webmasters/v3/sites/'.rawurlencode($siteUrl).'/searchAnalytics/query', [
                'startDate' => $startDate,
                'endDate' => $endDate,
                'dimensions' => $dimensions,
                'type' => $type,
                'rowLimit' => $rowLimit,
            ]);

        $this->throwIfFailed($response->status(), $response->json(), 'query Search Console search analytics');

        $payload = $response->json();

        return is_array($payload) ? $payload : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function inspectUrl(string $siteUrl, string $inspectionUrl): array
    {
        $response = $this->request()
            ->post($this->inspectionBaseUrl().'/v1/urlInspection/index:inspect', [
                'inspectionUrl' => $inspectionUrl,
                'siteUrl' => $siteUrl,
                'languageCode' => $this->inspectionLanguageCode(),
            ]);

        $this->throwIfFailed($response->status(), $response->json(), 'inspect Search Console URL');

        $payload = $response->json();

        return is_array($payload) ? $payload : [];
    }

    private function request(): PendingRequest
    {
        return Http::acceptJson()
            ->contentType('application/json')
            ->timeout(30)
            ->withToken($this->tokenProvider->accessToken());
    }

    private function apiBaseUrl(): string
    {
        return rtrim((string) config('services.google.search_console.api_base_url', 'https://www.googleapis.com'), '/');
    }

    private function inspectionBaseUrl(): string
    {
        return rtrim((string) config('services.google.search_console.inspection_base_url', 'https://searchconsole.googleapis.com'), '/');
    }

    private function inspectionLanguageCode(): string
    {
        return trim((string) config('services.google.search_console.language_code', 'en-AU')) ?: 'en-AU';
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function throwIfFailed(int $status, ?array $payload, string $action): void
    {
        if ($status >= 200 && $status < 300) {
            return;
        }

        $message = data_get($payload, 'error.message')
            ?: data_get($payload, 'error.status')
            ?: 'Unknown Google API error';

        throw new RuntimeException(sprintf('Unable to %s: %s', $action, $message));
    }
}
