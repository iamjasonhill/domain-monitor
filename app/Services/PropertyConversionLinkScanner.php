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

        if ($anchors === []) {
            $fallbackNodes = $xpath->query('//body//a[@href]');

            if ($fallbackNodes !== false) {
                /** @var DOMElement $node */
                foreach ($fallbackNodes as $node) {
                    $anchors[] = $node;
                }
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

        $property->forceFill($scan)->save();

        return $scan;
    }

    private function homepageUrlForProperty(WebProperty $property): ?string
    {
        $url = $property->production_url;

        if (is_string($url) && $url !== '') {
            return rtrim($url, '/');
        }

        $domain = $property->primaryDomainName();

        if (! is_string($domain) || $domain === '') {
            return null;
        }

        return 'https://'.$domain;
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

        if (! Str::contains($haystack, ['quote', 'book', 'booking'])) {
            return ['bucket' => null, 'score' => 0];
        }

        $isBooking = Str::contains($haystack, ['book', 'booking']);
        $isQuote = Str::contains($haystack, ['quote']);
        $isVehicle = Str::contains($haystack, ['vehicle', 'car', 'transport']);
        $isHousehold = Str::contains($haystack, ['move', 'moving', 'removal', 'removalist', 'household', 'furniture', 'backloading']);

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
}
