<?php

namespace App\Services;

use App\Models\WebProperty;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Str;

class PropertyConversionLinkScanner
{
    private const LEGACY_ENDPOINT_PROBE_REDIRECT_LIMIT = 3;

    private const LEGACY_ENDPOINT_PROBE_TIMEOUT_SECONDS = 5;

    public function __construct(
        private readonly HttpFactory $http,
    ) {}

    /**
     * @return array{
     *   current_household_quote_url: string|null,
     *   current_household_booking_url: string|null,
     *   current_vehicle_quote_url: string|null,
     *   current_vehicle_booking_url: string|null,
     *   conversion_links_scanned_at: \Illuminate\Support\Carbon,
     *   legacy_moveroo_endpoint_scan: array{
     *     legacy_booking_endpoint: array<string, mixed>|null,
     *     legacy_payment_endpoint: array<string, mixed>|null
     *   }
     * }
     */
    public function scanForProperty(WebProperty $property): array
    {
        $homepageUrl = $this->homepageUrlForProperty($property);

        if ($homepageUrl === null) {
            throw new \RuntimeException('Property does not have a scannable production URL or primary domain.');
        }

        if (! $this->isAllowedScanUrl($homepageUrl, $property)) {
            throw new \RuntimeException('Property homepage URL is not allowed for conversion-link scanning.');
        }

        $response = $this->http
            ->timeout(15)
            ->withoutRedirecting()
            ->withHeaders([
                'Accept' => 'text/html,application/xhtml+xml',
                'User-Agent' => 'DomainMonitorConversionLinkScanner/1.0 (+https://monitor.again.com.au)',
            ])
            ->get($homepageUrl);

        if (! $response->successful()) {
            throw new \RuntimeException(sprintf(
                'Homepage URL returned non-success HTTP status [%d].',
                $response->status()
            ));
        }

        $anchors = $this->extractAnchors($response->body(), $homepageUrl);

        return [
            'current_household_quote_url' => $this->pickLink($anchors, 'household_quote'),
            'current_household_booking_url' => $this->pickLink($anchors, 'household_booking'),
            'current_vehicle_quote_url' => $this->pickLink($anchors, 'vehicle_quote'),
            'current_vehicle_booking_url' => $this->pickLink($anchors, 'vehicle_booking'),
            'conversion_links_scanned_at' => now(),
            'legacy_moveroo_endpoint_scan' => $this->scanLegacyMoverooEndpoints($anchors, $homepageUrl, $property),
        ];
    }

    /**
     * @return array<int, array{href: string, text: string, bucket: string|null, score: int}>
     */
    public function extractAnchors(string $html, string $baseUrl): array
    {
        $dom = new DOMDocument;

        $previousInternalErrors = libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($previousInternalErrors);

        $xpath = new DOMXPath($dom);
        $nodes = $xpath->query('//nav//a[@href] | //header//a[@href]');

        $anchors = [];

        if ($nodes !== false) {
            /** @var DOMElement $node */
            foreach ($nodes as $node) {
                $anchors[] = $node;
            }
        }

        return collect($anchors)
            ->map(function (DOMElement $anchor) use ($baseUrl): ?array {
                $href = trim((string) $anchor->getAttribute('href'));

                if ($href === '' || Str::startsWith($href, ['#', 'mailto:', 'tel:', 'javascript:'])) {
                    return null;
                }

                $absoluteUrl = $this->normalizeUrl($href, $baseUrl);

                if ($absoluteUrl === null) {
                    return null;
                }

                $text = trim(preg_replace('/\s+/', ' ', $anchor->textContent ?? '') ?? '');
                $classification = $this->classifyAnchor($text, $absoluteUrl);

                return [
                    'href' => $absoluteUrl,
                    'text' => $text,
                    'bucket' => $classification['bucket'],
                    'score' => $classification['score'],
                ];
            })
            ->filter(fn (?array $anchor): bool => is_array($anchor))
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array{href: string, text: string, bucket: string|null, score: int}>  $anchors
     */
    public function pickLink(array $anchors, string $bucket): ?string
    {
        return collect($anchors)
            ->filter(fn (array $anchor): bool => $anchor['bucket'] === $bucket)
            ->sortByDesc(fn (array $anchor): int => $anchor['score'])
            ->pluck('href')
            ->first();
    }

    /**
     * @return array{
     *   current_household_quote_url: string|null,
     *   current_household_booking_url: string|null,
     *   current_vehicle_quote_url: string|null,
     *   current_vehicle_booking_url: string|null,
     *   conversion_links_scanned_at: \Illuminate\Support\Carbon,
     *   legacy_moveroo_endpoint_scan: array{
     *     legacy_booking_endpoint: array<string, mixed>|null,
     *     legacy_payment_endpoint: array<string, mixed>|null
     *   }
     * }
     */
    public function persistForProperty(WebProperty $property): array
    {
        $scan = $this->scanForProperty($property);
        $property->fill($scan)->save();

        return $scan;
    }

    private function homepageUrlForProperty(WebProperty $property): ?string
    {
        $domain = $property->primaryDomainName();

        if (is_string($domain) && $domain !== '') {
            return 'https://'.$domain;
        }

        $url = $property->production_url;

        if (is_string($url) && $url !== '') {
            $parts = parse_url($url);

            if (is_array($parts) && isset($parts['scheme'], $parts['host'])) {
                $homepageUrl = $parts['scheme'].'://'.$parts['host'];

                if (isset($parts['port'])) {
                    $homepageUrl .= ':'.$parts['port'];
                }

                return $homepageUrl;
            }
        }

        return null;
    }

    private function isAllowedScanUrl(string $url, WebProperty $property): bool
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

        return in_array($host, $this->allowedHostsForProperty($property), true);
    }

    /**
     * @return array<int, string>
     */
    private function allowedHostsForProperty(WebProperty $property): array
    {
        $hosts = [];

        $primaryDomain = $property->primaryDomainName();
        if (is_string($primaryDomain) && $primaryDomain !== '') {
            $hosts[] = Str::lower($primaryDomain);
        }

        foreach ($property->orderedDomainLinks() as $link) {
            if (is_string($link->domain?->domain) && $link->domain->domain !== '') {
                $hosts[] = Str::lower($link->domain->domain);
            }
        }

        if (is_string($property->production_url) && $property->production_url !== '') {
            $productionHost = parse_url($property->production_url, PHP_URL_HOST);

            if (is_string($productionHost) && $productionHost !== '') {
                $hosts[] = Str::lower($productionHost);
            }
        }

        return array_values(array_unique($hosts));
    }

    private function normalizeUrl(string $href, string $baseUrl): ?string
    {
        if (Str::startsWith($href, ['http://', 'https://'])) {
            return $href;
        }

        $base = parse_url($baseUrl);

        if (! is_array($base) || ! isset($base['scheme'], $base['host'])) {
            return null;
        }

        $prefix = $base['scheme'].'://'.$base['host'];

        if (isset($base['port'])) {
            $prefix .= ':'.$base['port'];
        }

        if (Str::startsWith($href, '//')) {
            return $base['scheme'].':'.$href;
        }

        if (Str::startsWith($href, '/')) {
            return $prefix.$href;
        }

        return $prefix.'/'.ltrim($href, '/');
    }

    /**
     * @param  array<int, array{href: string, text: string, bucket: string|null, score: int}>  $anchors
     * @return array{
     *   legacy_booking_endpoint: array<string, mixed>|null,
     *   legacy_payment_endpoint: array<string, mixed>|null
     * }
     */
    private function scanLegacyMoverooEndpoints(array $anchors, string $homepageUrl, WebProperty $property): array
    {
        $previousMatches = is_array($property->legacy_moveroo_endpoint_scan)
            ? $property->legacy_moveroo_endpoint_scan
            : [];

        /** @var array{legacy_booking_endpoint: array<string, mixed>|null, legacy_payment_endpoint: array<string, mixed>|null} $matches */
        $matches = [
            'legacy_booking_endpoint' => null,
            'legacy_payment_endpoint' => null,
        ];

        foreach ($anchors as $anchor) {
            $classification = $this->legacyMoverooEndpointClassification($anchor['href'], $property);

            if ($classification === null || $matches[$classification] !== null) {
                continue;
            }

            $resolution = $this->resolveLegacyEndpoint($anchor['href']);
            $resolution = $this->mergePreviousLegacyResolution(
                $resolution,
                $previousMatches[$classification] ?? null,
                $anchor['href'],
            );

            $matches[$classification] = [
                'classification' => $classification,
                'found_on' => $homepageUrl,
                'url' => $anchor['href'],
                'resolved_url' => $resolution['resolved_url'],
                'resolved_status' => $resolution['resolved_status'],
                'resolved_host_changed' => $resolution['resolved_host_changed'],
            ];
        }

        return [
            'legacy_booking_endpoint' => $matches['legacy_booking_endpoint'],
            'legacy_payment_endpoint' => $matches['legacy_payment_endpoint'],
        ];
    }

    private function legacyMoverooEndpointClassification(string $url, WebProperty $property): ?string
    {
        $parts = parse_url($url);

        if (! is_array($parts) || ! isset($parts['host'])) {
            return null;
        }

        $path = '/'.trim((string) ($parts['path'] ?? '/'), '/');
        $normalizedPath = Str::lower(rtrim($path, '/'));
        $host = Str::lower((string) $parts['host']);

        $classification = match ($normalizedPath) {
            '/bookings' => 'legacy_booking_endpoint',
            '/payments' => 'legacy_payment_endpoint',
            default => null,
        };

        if ($classification === null) {
            return null;
        }

        if ($host === $this->targetMoverooHost($property)) {
            return $classification;
        }

        return null;
    }

    private function targetMoverooHost(WebProperty $property): ?string
    {
        if (! is_string($property->target_moveroo_subdomain_url) || $property->target_moveroo_subdomain_url === '') {
            return null;
        }

        $targetMoverooHost = parse_url($property->target_moveroo_subdomain_url, PHP_URL_HOST);

        return is_string($targetMoverooHost) && $targetMoverooHost !== ''
            ? Str::lower($targetMoverooHost)
            : null;
    }

    /**
     * @return array{
     *   resolved_url: string|null,
     *   resolved_status: int|null,
     *   resolved_host_changed: bool|null
     * }
     */
    private function resolveLegacyEndpoint(string $url): array
    {
        $originalHost = parse_url($url, PHP_URL_HOST);
        $currentUrl = $url;
        $lastStatus = null;

        for ($attempt = 0; $attempt <= self::LEGACY_ENDPOINT_PROBE_REDIRECT_LIMIT; $attempt++) {
            if (! $this->isSafeLegacyEndpointProbeUrl($currentUrl)) {
                return $this->failedLegacyEndpointResolution();
            }

            try {
                $response = $this->http
                    ->timeout(self::LEGACY_ENDPOINT_PROBE_TIMEOUT_SECONDS)
                    ->withoutRedirecting()
                    ->withHeaders([
                        'Accept' => 'text/html,application/xhtml+xml',
                        'User-Agent' => 'DomainMonitorConversionLinkScanner/1.0 (+https://monitor.again.com.au)',
                    ])
                    ->get($currentUrl);
            } catch (\Throwable) {
                return $this->failedLegacyEndpointResolution();
            }

            $status = $response->status();
            $lastStatus = $status;
            $location = $response->header('Location');

            if ($status >= 300 && $status < 400 && $location !== '' && $attempt < self::LEGACY_ENDPOINT_PROBE_REDIRECT_LIMIT) {
                $nextUrl = $this->normalizeUrl($location, $currentUrl);

                if ($nextUrl === null || ! $this->isSafeLegacyEndpointProbeUrl($nextUrl)) {
                    return $this->failedLegacyEndpointResolution($status);
                }

                $currentUrl = $nextUrl;

                continue;
            }

            $resolvedUrl = $this->sanitizeResolvedUrl($currentUrl);
            $resolvedHost = $resolvedUrl !== null
                ? parse_url($resolvedUrl, PHP_URL_HOST)
                : parse_url($currentUrl, PHP_URL_HOST);

            return [
                'resolved_url' => $resolvedUrl,
                'resolved_status' => $status,
                'resolved_host_changed' => is_string($originalHost) && is_string($resolvedHost)
                    ? Str::lower($originalHost) !== Str::lower($resolvedHost)
                    : null,
            ];
        }

        return $this->failedLegacyEndpointResolution($lastStatus);
    }

    /**
     * @param  array{resolved_url: string|null, resolved_status: int|null, resolved_host_changed: bool|null}  $resolution
     * @return array{resolved_url: string|null, resolved_status: int|null, resolved_host_changed: bool|null}
     */
    private function mergePreviousLegacyResolution(array $resolution, mixed $previousEntry, string $url): array
    {
        if ($resolution['resolved_status'] !== null || ! is_array($previousEntry)) {
            return $resolution;
        }

        $previousUrl = is_string($previousEntry['url'] ?? null) ? $previousEntry['url'] : null;

        if ($previousUrl !== $url) {
            return $resolution;
        }

        return [
            'resolved_url' => is_string($previousEntry['resolved_url'] ?? null)
                ? $this->sanitizeResolvedUrl($previousEntry['resolved_url'])
                : null,
            'resolved_status' => is_numeric($previousEntry['resolved_status'] ?? null)
                ? (int) $previousEntry['resolved_status']
                : null,
            'resolved_host_changed' => is_bool($previousEntry['resolved_host_changed'] ?? null)
                ? $previousEntry['resolved_host_changed']
                : null,
        ];
    }

    private function isSafeLegacyEndpointProbeUrl(string $url): bool
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

        return str_contains($host, '.')
            && ! Str::endsWith($host, ['.local', '.internal']);
    }

    private function sanitizeResolvedUrl(string $url): ?string
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

    /**
     * @return array{resolved_url: string|null, resolved_status: int|null, resolved_host_changed: bool|null}
     */
    private function failedLegacyEndpointResolution(?int $status = null): array
    {
        return [
            'resolved_url' => null,
            'resolved_status' => $status,
            'resolved_host_changed' => null,
        ];
    }

    /**
     * @return array{bucket: string|null, score: int}
     */
    private function classifyAnchor(string $text, string $href): array
    {
        $haystack = Str::lower(trim($text.' '.$href));

        if (! $this->containsWholeWord($haystack, ['quote', 'book', 'booking'])) {
            return ['bucket' => null, 'score' => 0];
        }

        $isBooking = $this->containsWholeWord($haystack, ['book', 'booking']);
        $isQuote = $this->containsWholeWord($haystack, ['quote']);
        $isVehicle = $this->containsWholeWord($haystack, ['vehicle', 'car', 'transport']);
        $isHousehold = $this->containsWholeWord($haystack, ['move', 'moving', 'removal', 'removalist', 'household', 'furniture', 'backloading']);

        $score = 0;
        $score += $isBooking ? 40 : 0;
        $score += $isQuote ? 35 : 0;
        $score += $isVehicle ? 25 : 0;
        $score += $isHousehold ? 20 : 0;

        if ($isBooking && $isVehicle) {
            return ['bucket' => 'vehicle_booking', 'score' => $score];
        }

        if ($isQuote && $isVehicle) {
            return ['bucket' => 'vehicle_quote', 'score' => $score];
        }

        if ($isBooking) {
            return ['bucket' => 'household_booking', 'score' => $score];
        }

        if ($isQuote) {
            return ['bucket' => 'household_quote', 'score' => $score];
        }

        return ['bucket' => null, 'score' => 0];
    }

    /**
     * @param  array<int, string>  $terms
     */
    private function containsWholeWord(string $haystack, array $terms): bool
    {
        foreach ($terms as $term) {
            if ($term === '') {
                continue;
            }

            if (preg_match('/(?<![a-z0-9])'.preg_quote(Str::lower($term), '/').'(?![a-z0-9])/i', $haystack) === 1) {
                return true;
            }
        }

        return false;
    }
}
