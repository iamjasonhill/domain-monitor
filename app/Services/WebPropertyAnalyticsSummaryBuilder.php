<?php

namespace App\Services;

use App\Models\AnalyticsInstallAudit;
use App\Models\PropertyAnalyticsSource;
use App\Models\WebProperty;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class WebPropertyAnalyticsSummaryBuilder
{
    /**
     * @return array{
     *   enabled: bool,
     *   provider: string|null,
     *   config: array<string, string|null>
     * }
     */
    public function build(WebProperty $property): array
    {
        $source = $this->primarySource($property);

        if (! $source instanceof PropertyAnalyticsSource) {
            return $this->defaultSummary();
        }

        $provider = $this->normalizedText($source->provider);
        if ($provider === null) {
            return $this->defaultSummary();
        }

        $config = $this->providerConfig($source, $provider);

        return [
            'enabled' => $this->isEnabled($source, $provider, $config),
            'provider' => $provider,
            'config' => $config,
        ];
    }

    /**
     * @return array{
     *   enabled: bool,
     *   provider: string|null,
     *   config: array<string, string|null>
     * }
     */
    private function defaultSummary(): array
    {
        return [
            'enabled' => false,
            'provider' => null,
            'config' => [],
        ];
    }

    private function primarySource(WebProperty $property): ?PropertyAnalyticsSource
    {
        /** @var Collection<int, PropertyAnalyticsSource>|null $loadedSources */
        $loadedSources = $property->relationLoaded('analyticsSources')
            ? $property->getRelation('analyticsSources')
            : null;

        if ($loadedSources instanceof Collection) {
            $primarySource = $loadedSources
                ->sortByDesc(fn (PropertyAnalyticsSource $source): bool => $source->is_primary)
                ->values()
                ->first();

            return $primarySource instanceof PropertyAnalyticsSource ? $primarySource : null;
        }

        $primarySource = $property->analyticsSources()
            ->with('latestInstallAudit')
            ->orderByDesc('is_primary')
            ->first();

        return $primarySource instanceof PropertyAnalyticsSource ? $primarySource : null;
    }

    /**
     * @return array<string, string|null>
     */
    private function providerConfig(PropertyAnalyticsSource $source, string $provider): array
    {
        return match ($provider) {
            'matomo' => [
                'base_url' => $this->matomoBaseUrl($source),
                'site_id' => $this->normalizedText($source->external_id),
            ],
            'gtm' => [
                'container_id' => $this->normalizedText($source->external_id),
            ],
            'ga4' => [
                'measurement_id' => $this->ga4ConfigValue($source, 'measurement_id') ?? $this->normalizedText($source->external_id),
                'property_id' => $this->ga4ConfigValue($source, 'property_id'),
                'stream_id' => $this->ga4ConfigValue($source, 'stream_id'),
                'analytics_account' => $this->ga4ConfigValue($source, 'analytics_account'),
                'bigquery_project' => $this->ga4ConfigValue($source, 'bigquery_project'),
                'measurement_protocol_secret_name' => $this->ga4ConfigValue($source, 'measurement_protocol_secret_name'),
            ],
            default => [],
        };
    }

    /**
     * @param  array<string, string|null>  $config
     */
    private function isEnabled(PropertyAnalyticsSource $source, string $provider, array $config): bool
    {
        if ($source->status !== 'active') {
            return false;
        }

        return match ($provider) {
            'matomo' => $this->filledConfigValue($config, 'base_url') && $this->filledConfigValue($config, 'site_id'),
            'gtm' => $this->filledConfigValue($config, 'container_id'),
            'ga4' => $this->filledConfigValue($config, 'measurement_id'),
            default => false,
        };
    }

    private function ga4ConfigValue(PropertyAnalyticsSource $source, string $key): ?string
    {
        $config = $source->provider_config;

        if (! is_array($config)) {
            return null;
        }

        return $this->normalizedText($config[$key] ?? null);
    }

    private function matomoBaseUrl(PropertyAnalyticsSource $source): ?string
    {
        $audit = $source->relationLoaded('latestInstallAudit')
            ? $source->latestInstallAudit
            : $source->latestInstallAudit()->first();

        if ($audit instanceof AnalyticsInstallAudit) {
            $expectedTrackerHost = $this->normalizedHost($audit->expected_tracker_host);
            if ($expectedTrackerHost !== null) {
                return 'https://'.$expectedTrackerHost;
            }

            foreach ($audit->detected_tracker_hosts ?? [] as $trackerHost) {
                $normalizedTrackerHost = $this->normalizedHost($trackerHost);
                if ($normalizedTrackerHost !== null) {
                    return 'https://'.$normalizedTrackerHost;
                }
            }
        }

        return $this->normalizedOriginUrl(config('services.matomo.base_url'));
    }

    private function normalizedText(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        return $normalized !== '' ? $normalized : null;
    }

    private function normalizedHost(mixed $value): ?string
    {
        $normalized = $this->normalizedText($value);
        if ($normalized === null) {
            return null;
        }

        if (str_starts_with($normalized, 'http://') || str_starts_with($normalized, 'https://')) {
            $parts = parse_url($normalized);
            if (! is_array($parts) || ! isset($parts['host'])) {
                return null;
            }

            return Str::lower((string) $parts['host']);
        }

        return Str::lower(rtrim($normalized, '.'));
    }

    private function normalizedOriginUrl(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $parts = parse_url(trim($value));

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        $origin = strtolower((string) $parts['scheme']).'://'.Str::lower((string) $parts['host']);

        if (isset($parts['port'])) {
            $origin .= ':'.$parts['port'];
        }

        return $origin;
    }

    /**
     * @param  array<string, string|null>  $config
     */
    private function filledConfigValue(array $config, string $key): bool
    {
        return isset($config[$key]) && $config[$key] !== '';
    }
}
