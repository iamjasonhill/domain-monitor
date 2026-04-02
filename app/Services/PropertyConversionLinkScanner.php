<?php

namespace App\Services;

use App\Models\WebProperty;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Str;

class PropertyConversionLinkScanner
{
    public function __construct(
        private readonly HttpFactory $http,
    ) {}

    /**
     * @return array{
     *   current_household_quote_url: string|null,
     *   current_household_booking_url: string|null,
     *   current_vehicle_quote_url: string|null,
     *   current_vehicle_booking_url: string|null,
     *   conversion_links_scanned_at: \Illuminate\Support\Carbon
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
            ->withHeaders([
                'Accept' => 'text/html,application/xhtml+xml',
                'User-Agent' => 'DomainMonitorConversionLinkScanner/1.0 (+https://monitor.again.com.au)',
            ])
            ->get($homepageUrl);

        if ($response->failed()) {
            throw new RequestException($response);
        }

        $anchors = $this->extractAnchors($response->body(), $homepageUrl);

        return [
            'current_household_quote_url' => $this->pickLink($anchors, 'household_quote'),
            'current_household_booking_url' => $this->pickLink($anchors, 'household_booking'),
            'current_vehicle_quote_url' => $this->pickLink($anchors, 'vehicle_quote'),
            'current_vehicle_booking_url' => $this->pickLink($anchors, 'vehicle_booking'),
            'conversion_links_scanned_at' => now(),
        ];
    }

    /**
     * @return array<int, array{href: string, text: string, bucket: string|null, score: int}>
     */
    public function extractAnchors(string $html, string $baseUrl): array
    {
        $dom = new DOMDocument;

        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();

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
            ->filter(fn (?array $anchor): bool => is_array($anchor) && $anchor['bucket'] !== null)
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
     *   conversion_links_scanned_at: \Illuminate\Support\Carbon
     * }
     */
    public function persistForProperty(WebProperty $property): array
    {
        $scan = $this->scanForProperty($property);
        $persistedScan = $this->mergeWithExisting($property, $scan);

        $property->fill($persistedScan)->save();

        return $persistedScan;
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

    /**
     * @param  array{
     *   current_household_quote_url: string|null,
     *   current_household_booking_url: string|null,
     *   current_vehicle_quote_url: string|null,
     *   current_vehicle_booking_url: string|null,
     *   conversion_links_scanned_at: \Illuminate\Support\Carbon
     * }  $scan
     * @return array{
     *   current_household_quote_url: string|null,
     *   current_household_booking_url: string|null,
     *   current_vehicle_quote_url: string|null,
     *   current_vehicle_booking_url: string|null,
     *   conversion_links_scanned_at: \Illuminate\Support\Carbon
     * }
     */
    private function mergeWithExisting(WebProperty $property, array $scan): array
    {
        foreach ([
            'current_household_quote_url',
            'current_household_booking_url',
            'current_vehicle_quote_url',
            'current_vehicle_booking_url',
        ] as $field) {
            if ($scan[$field] === null && is_string($property->{$field}) && $property->{$field} !== '') {
                $scan[$field] = $property->{$field};
            }
        }

        return $scan;
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
