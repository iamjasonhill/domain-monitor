<?php

namespace App\Services\BrandStyle;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class MarketingSiteStyleExtractor
{
    /**
     * @param  array<string, mixed>  $publishedMetadata
     * @return array<string, mixed>
     */
    public function extract(string $hostname, string $sourceMarketingDomain, array $publishedMetadata = []): array
    {
        $sourceUrl = 'https://'.$this->normalizeHostname($sourceMarketingDomain);
        $response = Http::timeout(10)
            ->accept('text/html')
            ->get($sourceUrl);

        if (! $response->successful()) {
            return [
                'hostname' => $hostname,
                'source_marketing_domain' => $sourceMarketingDomain,
                'proposal_status' => 'extraction_failed',
                'approval_status' => 'blocked',
                'review_reason' => sprintf('Marketing site returned HTTP %d during style extraction.', $response->status()),
                'candidate' => [],
                'evidence' => [],
                'publish_gate' => [
                    'can_publish' => false,
                    'reason' => 'extraction_failed',
                ],
            ];
        }

        $html = $response->body();
        $capturedAt = now()->toIso8601String();
        $brand = $this->brandCandidate($html, $publishedMetadata);
        $theme = $this->themeCandidate($html, $publishedMetadata);
        $copy = $this->copyCandidate($html, $publishedMetadata);

        return [
            'hostname' => $hostname,
            'source_marketing_domain' => $sourceMarketingDomain,
            'property_slug' => $publishedMetadata['property_slug'] ?? null,
            'surface_slug' => $publishedMetadata['surface_slug'] ?? null,
            'journey_type' => $publishedMetadata['journey_type'] ?? null,
            'proposal_status' => 'draft_review_required',
            'approval_status' => 'needs_review',
            'review_reason' => 'Marketing-site style extraction requires review before it can annotate the published surface.',
            'candidate' => [
                'brand' => $brand,
                'theme' => $theme,
                'copy' => $copy,
            ],
            'evidence' => $this->evidence($sourceUrl, $capturedAt, $brand, $theme, $copy),
            'publish_gate' => [
                'can_publish' => false,
                'reason' => 'draft_requires_human_or_trusted_review',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $publishedMetadata
     * @return array<string, mixed>
     */
    private function brandCandidate(string $html, array $publishedMetadata): array
    {
        $configuredBrand = is_array($publishedMetadata['brand'] ?? null) ? $publishedMetadata['brand'] : [];
        $displayName = $this->metaContent($html, 'og:site_name')
            ?? $this->title($html)
            ?? ($configuredBrand['display_name'] ?? null);

        return array_filter([
            'display_name' => $displayName,
            'brand_key' => $configuredBrand['brand_key'] ?? null,
            'tagline' => $configuredBrand['tagline'] ?? null,
            'mark_text' => $configuredBrand['mark_text'] ?? null,
        ], fn (mixed $value): bool => $value !== null && $value !== '');
    }

    /**
     * @param  array<string, mixed>  $publishedMetadata
     * @return array<string, mixed>
     */
    private function themeCandidate(string $html, array $publishedMetadata): array
    {
        $configuredTheme = is_array($publishedMetadata['theme'] ?? null) ? $publishedMetadata['theme'] : [];
        $colors = $this->safeHexColors($html);
        $fontFamily = $this->firstFontFamily($html);
        $accent = array_key_exists(0, $colors) ? $colors[0] : null;
        $accentStrong = array_key_exists(1, $colors) ? $colors[1] : null;

        return [
            'theme_key' => $configuredTheme['theme_key'] ?? null,
            'mode' => 'auto',
            'fonts' => array_filter([
                'body_family' => $fontFamily,
                'heading_family' => $fontFamily,
            ]),
            'colors' => array_filter([
                'accent' => $accent,
                'accent_strong' => $accentStrong,
            ]),
            'exact_tokens' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $publishedMetadata
     * @return array<string, mixed>
     */
    private function copyCandidate(string $html, array $publishedMetadata): array
    {
        $configuredCopy = is_array($publishedMetadata['copy'] ?? null) ? $publishedMetadata['copy'] : [];

        return array_filter([
            'headline' => $this->heading($html) ?? ($configuredCopy['headline'] ?? null),
            'subheading' => $this->metaContent($html, 'description') ?? ($configuredCopy['subheading'] ?? null),
        ], fn (mixed $value): bool => $value !== null && $value !== '');
    }

    /**
     * @param  array<string, mixed>  $brand
     * @param  array<string, mixed>  $theme
     * @param  array<string, mixed>  $copy
     * @return array<int, array<string, mixed>>
     */
    private function evidence(string $sourceUrl, string $capturedAt, array $brand, array $theme, array $copy): array
    {
        return collect([
            ['field' => 'brand.display_name', 'value' => $brand['display_name'] ?? null],
            ['field' => 'theme.fonts.body_family', 'value' => $theme['fonts']['body_family'] ?? null],
            ['field' => 'theme.colors.accent', 'value' => $theme['colors']['accent'] ?? null],
            ['field' => 'copy.headline', 'value' => $copy['headline'] ?? null],
            ['field' => 'copy.subheading', 'value' => $copy['subheading'] ?? null],
        ])
            ->filter(fn (array $item): bool => $item['value'] !== null && $item['value'] !== '')
            ->map(fn (array $item): array => [
                'field' => $item['field'],
                'value' => $item['value'],
                'source_url' => $sourceUrl,
                'source_type' => 'marketing_site_extraction',
                'confidence' => 'medium',
                'captured_at' => $capturedAt,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function safeHexColors(string $html): array
    {
        preg_match_all('/#[0-9a-fA-F]{6}\b/', $html, $matches);

        $colors = collect($matches[0])
            ->map(fn (string $color): string => Str::lower($color))
            ->reject(fn (string $color): bool => in_array($color, ['#000000', '#ffffff'], true))
            ->unique()
            ->values();

        return array_values($colors
            ->take(2)
            ->all());
    }

    private function firstFontFamily(string $html): ?string
    {
        if (! preg_match('/font-family\s*:\s*([^;}]+)/i', $html, $matches)) {
            return null;
        }

        $family = trim((string) Str::of($matches[1])->before(',')->trim(" \t\n\r\0\x0B'\""));

        return preg_match('/^[A-Za-z0-9 -]{1,60}$/', $family) === 1 ? $family : null;
    }

    private function metaContent(string $html, string $name): ?string
    {
        $quotedName = preg_quote($name, '/');

        foreach ([
            '/<meta[^>]+(?:name|property)=["\']'.$quotedName.'["\'][^>]+content=["\']([^"\']+)["\'][^>]*>/i',
            '/<meta[^>]+content=["\']([^"\']+)["\'][^>]+(?:name|property)=["\']'.$quotedName.'["\'][^>]*>/i',
        ] as $pattern) {
            if (preg_match($pattern, $html, $matches)) {
                return $this->cleanText($matches[1]);
            }
        }

        return null;
    }

    private function title(string $html): ?string
    {
        if (! preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $matches)) {
            return null;
        }

        return $this->cleanText($matches[1]);
    }

    private function heading(string $html): ?string
    {
        if (! preg_match('/<h1[^>]*>(.*?)<\/h1>/is', $html, $matches)) {
            return null;
        }

        return $this->cleanText($matches[1]);
    }

    private function cleanText(string $text): ?string
    {
        $cleaned = trim(html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5));

        return $cleaned === '' ? null : Str::limit($cleaned, 180, '');
    }

    private function normalizeHostname(string $hostname): string
    {
        return Str::of($hostname)
            ->lower()
            ->replaceStart('https://', '')
            ->replaceStart('http://', '')
            ->before('/')
            ->trim('.')
            ->toString();
    }
}
