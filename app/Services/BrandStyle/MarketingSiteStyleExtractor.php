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
        $stylesheetCss = $this->stylesheetCss($html, $sourceUrl);
        $styleSource = trim($html."\n".$stylesheetCss);
        $capturedAt = now()->toIso8601String();
        $brand = $this->brandCandidate($html, $publishedMetadata);
        $theme = $this->themeCandidate($html, $styleSource, $publishedMetadata);
        $copy = $this->copyCandidate($html, $publishedMetadata);
        $visualIdentity = $this->visualIdentityCandidate($hostname, $sourceMarketingDomain, $theme, $publishedMetadata);

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
                'visual_identity' => $visualIdentity,
            ],
            'evidence' => $this->evidence($sourceUrl, $stylesheetCss !== '', $capturedAt, $brand, $theme, $copy, $visualIdentity),
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
    private function themeCandidate(string $html, string $styleSource, array $publishedMetadata): array
    {
        $configuredTheme = is_array($publishedMetadata['theme'] ?? null) ? $publishedMetadata['theme'] : [];
        $variables = $this->cssVariables($styleSource);
        $fontFamily = $this->firstFontFamily($styleSource);
        $accent = $this->metaContent($html, 'theme-color')
            ?? $this->variableColor($variables, ['accent', 'brand', 'red', 'orange', 'primary'])
            ?? $this->firstUsefulColor($styleSource);
        $accentStrong = $this->variableColor($variables, ['strong', 'deep', 'dark-red', 'red-deep'])
            ?? $this->secondUsefulColor($styleSource, $accent);
        $background = $this->variableColor($variables, ['background', 'bg'])
            ?? $this->declarationColor($styleSource, ['body', 'theme-body', 'page'], ['background', 'background-color'], $variables);
        $surface = $this->variableColor($variables, ['surface', 'paper', 'card'])
            ?? $this->declarationColor($styleSource, ['card', 'paper', 'surface', 'article'], ['background', 'background-color'], $variables);
        $text = $this->variableColor($variables, ['text', 'ink', 'foreground'])
            ?? $this->declarationColor($styleSource, ['body', 'theme-body', 'page'], ['color'], $variables);
        $mutedText = $this->variableColor($variables, ['muted', 'soft', 'subtle']);
        $border = $this->variableColor($variables, ['border', 'line']);
        $primaryCta = $this->ctaToken($styleSource, ['primary', 'cta'], $variables);
        $secondaryCta = $this->ctaToken($styleSource, ['secondary'], $variables);

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
                'background' => $background,
                'text' => $text,
                'muted_text' => $mutedText,
                'surface' => $surface,
                'border' => $border,
            ]),
            'exact_tokens' => array_filter([
                'primary_cta' => $primaryCta,
                'secondary_cta' => $secondaryCta,
            ]),
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
     * @param  array<string, mixed>  $theme
     * @param  array<string, mixed>  $publishedMetadata
     * @return array<string, mixed>
     */
    private function visualIdentityCandidate(string $hostname, string $sourceMarketingDomain, array $theme, array $publishedMetadata): array
    {
        $colors = is_array($theme['colors'] ?? null) ? $theme['colors'] : [];
        $fonts = is_array($theme['fonts'] ?? null) ? $theme['fonts'] : [];
        $journeyType = is_string($publishedMetadata['journey_type'] ?? null) ? $publishedMetadata['journey_type'] : null;

        return array_filter([
            'status' => 'needs_review',
            'source_hostname' => $this->normalizeHostname($sourceMarketingDomain),
            'font_pairing' => array_filter([
                'heading' => $this->fontKey($fonts['heading_family'] ?? null),
                'body' => $this->fontKey($fonts['body_family'] ?? null),
            ]),
            'colors' => array_filter([
                'primary' => $this->primaryColor($theme, $colors),
                'accent' => $colors['accent'] ?? null,
                'accent_strong' => $colors['accent_strong'] ?? null,
                'background' => $colors['background'] ?? null,
                'surface' => $colors['surface'] ?? null,
                'text' => $colors['text'] ?? null,
                'muted_text' => $colors['muted_text'] ?? null,
            ]),
            'style' => array_filter([
                'visual_profile' => $this->visualProfile($hostname, $journeyType, $colors),
                'hero_treatment' => 'marketing_site_prominent_surface',
                'button_treatment' => isset($theme['exact_tokens']['primary_cta']) ? 'extracted_primary_cta' : null,
                'secondary_button_treatment' => isset($theme['exact_tokens']['secondary_cta']) ? 'extracted_secondary_cta' : null,
            ]),
        ], fn (mixed $value): bool => $value !== []);
    }

    /**
     * @param  array<string, mixed>  $brand
     * @param  array<string, mixed>  $theme
     * @param  array<string, mixed>  $copy
     * @param  array<string, mixed>  $visualIdentity
     * @return array<int, array<string, mixed>>
     */
    private function evidence(
        string $sourceUrl,
        bool $usedStylesheet,
        string $capturedAt,
        array $brand,
        array $theme,
        array $copy,
        array $visualIdentity
    ): array {
        $sourceType = $usedStylesheet ? 'marketing_site_stylesheet_extraction' : 'marketing_site_extraction';

        return collect([
            ['field' => 'brand.display_name', 'value' => $brand['display_name'] ?? null],
            ['field' => 'theme.fonts.body_family', 'value' => $theme['fonts']['body_family'] ?? null],
            ['field' => 'theme.colors.accent', 'value' => $theme['colors']['accent'] ?? null],
            ['field' => 'theme.colors.background', 'value' => $theme['colors']['background'] ?? null],
            ['field' => 'theme.colors.surface', 'value' => $theme['colors']['surface'] ?? null],
            ['field' => 'theme.colors.text', 'value' => $theme['colors']['text'] ?? null],
            ['field' => 'theme.exact_tokens.primary_cta.background', 'value' => $theme['exact_tokens']['primary_cta']['background'] ?? null],
            ['field' => 'theme.exact_tokens.secondary_cta.background', 'value' => $theme['exact_tokens']['secondary_cta']['background'] ?? null],
            ['field' => 'visual_identity.style.visual_profile', 'value' => $visualIdentity['style']['visual_profile'] ?? null],
            ['field' => 'copy.headline', 'value' => $copy['headline'] ?? null],
            ['field' => 'copy.subheading', 'value' => $copy['subheading'] ?? null],
        ])
            ->filter(fn (array $item): bool => $item['value'] !== null && $item['value'] !== '')
            ->map(fn (array $item): array => [
                'field' => $item['field'],
                'value' => $item['value'],
                'source_url' => $sourceUrl,
                'source_type' => $sourceType,
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
        preg_match_all('/#[0-9a-fA-F]{3}(?:[0-9a-fA-F]{3})?\b/', $html, $matches);

        $colors = collect($matches[0])
            ->map(fn (string $color): ?string => $this->normalizeColor($color))
            ->filter()
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
        $family = (string) Str::of($family)->replace(' Variable', '');

        return preg_match('/^[A-Za-z0-9 -]{1,60}$/', $family) === 1 ? $family : null;
    }

    /**
     * @return array<string, string>
     */
    private function cssVariables(string $css): array
    {
        preg_match_all('/(--[A-Za-z0-9_-]+)\s*:\s*([^;}]+)/', $css, $matches, PREG_SET_ORDER);

        return collect($matches)
            ->mapWithKeys(function (array $match): array {
                $value = $this->cleanCssValue((string) $match[2]);

                return $value === null ? [] : [Str::lower((string) $match[1]) => $value];
            })
            ->all();
    }

    /**
     * @param  array<string, string>  $variables
     * @param  array<int, string>  $nameHints
     */
    private function variableColor(array $variables, array $nameHints): ?string
    {
        foreach ($nameHints as $hint) {
            foreach ($variables as $name => $value) {
                if (! Str::contains($name, Str::lower($hint))) {
                    continue;
                }

                $color = $this->colorFromValue($value, $variables);

                if ($color !== null) {
                    return $color;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<int, string>  $selectorHints
     * @param  array<int, string>  $properties
     * @param  array<string, string>  $variables
     */
    private function declarationColor(string $css, array $selectorHints, array $properties, array $variables): ?string
    {
        foreach ($this->matchingRuleDeclarations($css, $selectorHints) as $declarations) {
            foreach ($properties as $property) {
                $value = $declarations[$property] ?? null;

                if ($value === null) {
                    continue;
                }

                $color = $this->colorFromValue($value, $variables);

                if ($color !== null) {
                    return $color;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<int, string>  $selectorHints
     * @param  array<string, string>  $variables
     * @return array<string, string>
     */
    private function ctaToken(string $css, array $selectorHints, array $variables): array
    {
        foreach ($this->matchingRuleDeclarations($css, $selectorHints) as $declarations) {
            $background = $this->colorFromValue($declarations['background'] ?? ($declarations['background-color'] ?? null), $variables);
            $text = $this->colorFromValue($declarations['color'] ?? null, $variables);
            $border = $this->colorFromValue($declarations['border'] ?? ($declarations['border-color'] ?? null), $variables);

            $token = array_filter([
                'background' => $background,
                'text' => $text,
                'border' => $border,
            ]);

            if ($token !== []) {
                return $token;
            }
        }

        return [];
    }

    /**
     * @param  array<int, string>  $selectorHints
     * @return array<int, array<string, string>>
     */
    private function matchingRuleDeclarations(string $css, array $selectorHints): array
    {
        preg_match_all('/([^{}]+)\{([^{}]+)\}/s', $css, $matches, PREG_SET_ORDER);

        return collect($matches)
            ->filter(function (array $match) use ($selectorHints): bool {
                $selector = Str::lower(trim((string) $match[1]));

                return collect($selectorHints)->contains(fn (string $hint): bool => Str::contains($selector, Str::lower($hint)));
            })
            ->map(fn (array $match): array => $this->declarations((string) $match[2]))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private function declarations(string $block): array
    {
        return collect(explode(';', $block))
            ->mapWithKeys(function (string $declaration): array {
                if (! str_contains($declaration, ':')) {
                    return [];
                }

                [$property, $value] = explode(':', $declaration, 2);
                $cleaned = $this->cleanCssValue($value);

                return $cleaned === null ? [] : [Str::lower(trim($property)) => $cleaned];
            })
            ->all();
    }

    /**
     * @param  array<string, string>  $variables
     */
    private function colorFromValue(?string $value, array $variables): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        preg_match_all('/var\(\s*(--[A-Za-z0-9_-]+)\s*\)/', $value, $variableMatches);

        foreach ($variableMatches[1] as $variableName) {
            $variableValue = $variables[Str::lower($variableName)] ?? null;
            $color = $this->colorFromValue($variableValue, $variables);

            if ($color !== null) {
                return $color;
            }
        }

        if (preg_match_all('/#[0-9a-fA-F]{3}(?:[0-9a-fA-F]{3})?\b/', $value, $hexMatches) && $hexMatches[0] !== []) {
            $normalized = collect($hexMatches[0])
                ->map(fn (string $color): ?string => $this->normalizeColor($color))
                ->filter()
                ->values();

            return $normalized->last();
        }

        if (preg_match('/rgba?\([^)]+\)/i', $value, $rgbMatch)) {
            return preg_replace('/\s+/', ' ', Str::lower($rgbMatch[0]));
        }

        return null;
    }

    private function normalizeColor(?string $color): ?string
    {
        if ($color === null) {
            return null;
        }

        $color = Str::lower(trim($color));

        if (preg_match('/^#[0-9a-f]{3}$/', $color) === 1) {
            return '#'.$color[1].$color[1].$color[2].$color[2].$color[3].$color[3];
        }

        return preg_match('/^#[0-9a-f]{6}$/', $color) === 1 ? $color : null;
    }

    private function cleanCssValue(string $value): ?string
    {
        $cleaned = trim((string) Str::of($value)->before('!important'));

        return $cleaned === '' ? null : $cleaned;
    }

    private function firstUsefulColor(string $source): ?string
    {
        return $this->safeHexColors($source)[0] ?? null;
    }

    private function secondUsefulColor(string $source, ?string $first): ?string
    {
        return collect($this->safeHexColors($source))
            ->first(fn (string $color): bool => $color !== $first);
    }

    /**
     * @param  array<string, mixed>  $theme
     * @param  array<string, mixed>  $colors
     */
    private function primaryColor(array $theme, array $colors): ?string
    {
        $secondaryCtaBackground = data_get($theme, 'exact_tokens.secondary_cta.background');

        if (is_string($secondaryCtaBackground) && preg_match('/^#[0-9a-f]{6}$/i', $secondaryCtaBackground) === 1) {
            return $secondaryCtaBackground;
        }

        return $this->darkestColor($colors);
    }

    /**
     * @param  array<string, mixed>  $colors
     */
    private function darkestColor(array $colors): ?string
    {
        return collect($colors)
            ->filter(fn (mixed $color): bool => is_string($color) && preg_match('/^#[0-9a-f]{6}$/i', $color) === 1)
            ->sortBy(fn (string $color): int => (int) hexdec(substr($color, 1, 2)) + (int) hexdec(substr($color, 3, 2)) + (int) hexdec(substr($color, 5, 2)))
            ->first();
    }

    /**
     * @param  array<string, mixed>  $colors
     */
    private function visualProfile(string $hostname, ?string $journeyType, array $colors): ?string
    {
        $accent = is_string($colors['accent'] ?? null) ? $colors['accent'] : null;
        $primary = $this->darkestColor($colors);

        if ($journeyType === 'vehicle_quote' && $accent !== null && $primary !== null) {
            return 'bold_budget_vehicle';
        }

        if (Str::contains($hostname, ['car', 'vehicle', 'auto'])) {
            return 'vehicle_transport';
        }

        return null;
    }

    private function fontKey(mixed $font): ?string
    {
        if (! is_string($font) || trim($font) === '') {
            return null;
        }

        return Str::of($font)
            ->lower()
            ->replace(' variable', '')
            ->replace(' ', '_')
            ->toString();
    }

    private function stylesheetCss(string $html, string $sourceUrl): string
    {
        preg_match_all('/<link\b[^>]*>/i', $html, $matches);

        return collect($matches[0])
            ->filter(fn (string $tag): bool => Str::contains(Str::lower($tag), 'stylesheet'))
            ->map(fn (string $tag): ?string => $this->attribute($tag, 'href'))
            ->filter()
            ->map(fn (string $href): ?string => $this->absoluteUrl($sourceUrl, $href))
            ->filter(fn (?string $url): bool => $url !== null && parse_url($url, PHP_URL_HOST) === parse_url($sourceUrl, PHP_URL_HOST))
            ->unique()
            ->take(5)
            ->map(function (string $url): ?string {
                $response = Http::timeout(10)
                    ->accept('text/css')
                    ->get($url);

                return $response->successful() ? $response->body() : null;
            })
            ->filter()
            ->implode("\n");
    }

    private function attribute(string $tag, string $attribute): ?string
    {
        if (! preg_match('/\b'.preg_quote($attribute, '/').'\s*=\s*["\']([^"\']+)["\']/i', $tag, $matches)) {
            return null;
        }

        return html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5);
    }

    private function absoluteUrl(string $baseUrl, string $href): ?string
    {
        if (Str::startsWith($href, ['http://', 'https://'])) {
            return $href;
        }

        if (Str::startsWith($href, '//')) {
            return 'https:'.$href;
        }

        $scheme = parse_url($baseUrl, PHP_URL_SCHEME) ?: 'https';
        $host = parse_url($baseUrl, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return null;
        }

        if (Str::startsWith($href, '/')) {
            return $scheme.'://'.$host.$href;
        }

        $path = trim((string) parse_url($baseUrl, PHP_URL_PATH), '/');
        $directory = $path === '' ? '' : Str::beforeLast($path, '/').'/';

        return $scheme.'://'.$host.'/'.$directory.$href;
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
