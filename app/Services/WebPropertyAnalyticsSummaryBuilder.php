<?php

namespace App\Services;

use App\Models\AnalyticsInstallAudit;
use App\Models\MonitoringFinding;
use App\Models\PropertyAnalyticsSource;
use App\Models\WebProperty;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class WebPropertyAnalyticsSummaryBuilder
{
    /**
     * @return array{
     *   enabled: bool,
     *   provider: string|null,
     *   config: array<string, string|null>,
     *   ga4: array<string, mixed>
     * }
     */
    public function build(WebProperty $property): array
    {
        $source = $this->primarySource($property);
        $ga4Source = $this->ga4Source($property);

        if (! $source instanceof PropertyAnalyticsSource) {
            return $this->defaultSummary($property, $ga4Source);
        }

        $provider = $this->normalizedText($source->provider);
        if ($provider === null) {
            return $this->defaultSummary($property, $ga4Source);
        }

        $config = $this->providerConfig($source, $provider);

        return [
            'enabled' => $this->isEnabled($source, $provider, $config),
            'provider' => $provider,
            'config' => $config,
            'ga4' => $this->ga4LookupSummary($property, $ga4Source),
        ];
    }

    /**
     * @return array{
     *   enabled: bool,
     *   provider: string|null,
     *   config: array<string, string|null>,
     *   ga4: array<string, mixed>
     * }
     */
    private function defaultSummary(WebProperty $property, ?PropertyAnalyticsSource $ga4Source): array
    {
        return [
            'enabled' => false,
            'provider' => null,
            'config' => [],
            'ga4' => $this->ga4LookupSummary($property, $ga4Source),
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

    private function ga4Source(WebProperty $property): ?PropertyAnalyticsSource
    {
        /** @var Collection<int, PropertyAnalyticsSource>|null $loadedSources */
        $loadedSources = $property->relationLoaded('analyticsSources')
            ? $property->getRelation('analyticsSources')
            : null;

        if ($loadedSources instanceof Collection) {
            $ga4Source = $loadedSources
                ->where('provider', 'ga4')
                ->sortByDesc(fn (PropertyAnalyticsSource $source): bool => $source->is_primary)
                ->values()
                ->first();

            return $ga4Source instanceof PropertyAnalyticsSource ? $ga4Source : null;
        }

        $ga4Source = $property->analyticsSources()
            ->where('provider', 'ga4')
            ->with('latestInstallAudit')
            ->orderByDesc('is_primary')
            ->first();

        return $ga4Source instanceof PropertyAnalyticsSource ? $ga4Source : null;
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
     * @return array{
     *   provider:string,
     *   property_slug:string|null,
     *   domain:string|null,
     *   site_key:string|null,
     *   source_system:string|null,
     *   measurement_id:string|null,
     *   property_id:string|null,
     *   stream_id:string|null,
     *   status:string,
     *   label:string,
     *   reason:string|null,
     *   switch_ready:bool|null,
     *   provisioning_state:string|null,
     *   external_name:string|null,
     *   last_synced_at:string|null,
     *   last_verified_at:string|null,
     *   last_live_check_at:string|null,
     *   detection: array{
     *     verdict:string|null,
     *     detected_measurement_ids: array<int, string>,
     *     issue_id:string|null
     *   }
     * }
     */
    private function ga4LookupSummary(WebProperty $property, ?PropertyAnalyticsSource $source): array
    {
        $default = [
            'provider' => 'ga4',
            'property_slug' => $property->slug,
            'domain' => $this->summaryDomain($property),
            'site_key' => $property->siteKey(),
            'source_system' => null,
            'measurement_id' => null,
            'property_id' => null,
            'stream_id' => null,
            'status' => 'missing',
            'label' => 'Missing',
            'reason' => 'No GA4 binding is stored for this property yet.',
            'switch_ready' => null,
            'provisioning_state' => null,
            'external_name' => null,
            'last_synced_at' => null,
            'last_verified_at' => null,
            'last_live_check_at' => null,
            'detection' => [
                'verdict' => null,
                'detected_measurement_ids' => [],
                'issue_id' => null,
            ],
        ];

        if (! $source instanceof PropertyAnalyticsSource) {
            return $default;
        }

        $config = is_array($source->provider_config) ? $source->provider_config : [];
        $measurementId = $this->ga4ConfigValue($source, 'measurement_id') ?? $this->normalizedText($source->external_id);
        $finding = $this->ga4MonitoringFinding($property);
        $detection = $this->ga4DetectionSummary($finding);
        $derivedState = $this->ga4LookupState($source, $measurementId, $finding);

        return [
            'provider' => 'ga4',
            'property_slug' => $property->slug,
            'domain' => $this->summaryDomain($property),
            'site_key' => $this->normalizedText($config['site_key'] ?? null) ?? $property->siteKey(),
            'source_system' => $this->normalizedText($config['source_system'] ?? null) ?? $this->sourceSystemLabel($source),
            'measurement_id' => $measurementId,
            'property_id' => $this->ga4ConfigValue($source, 'property_id'),
            'stream_id' => $this->ga4ConfigValue($source, 'stream_id'),
            'status' => $derivedState['status'],
            'label' => $derivedState['label'],
            'reason' => $derivedState['reason'],
            'switch_ready' => $this->normalizedBoolean($config['switch_ready'] ?? null),
            'provisioning_state' => $this->normalizedText($config['provisioning_state'] ?? null),
            'external_name' => $source->external_name,
            'last_synced_at' => $this->timestampString($config['last_synced_at'] ?? null),
            'last_verified_at' => null,
            'last_live_check_at' => $finding?->last_detected_at?->toIso8601String(),
            'detection' => $detection,
        ];
    }

    private function sourceSystemLabel(PropertyAnalyticsSource $source): ?string
    {
        if (is_string($source->workspace_path) && str_contains($source->workspace_path, 'MM-Google')) {
            return 'MM-Google';
        }

        return null;
    }

    private function summaryDomain(WebProperty $property): ?string
    {
        if ($property->relationLoaded('primaryDomain') && is_string($property->primaryDomain?->domain)) {
            return $property->primaryDomain->domain;
        }

        if ($property->relationLoaded('propertyDomains')) {
            $domain = $property->propertyDomains
                ->sortByDesc(fn (mixed $link): mixed => $link->is_canonical ?? false)
                ->first()?->domain?->domain;

            return is_string($domain) ? $domain : null;
        }

        if (! $property->exists) {
            return null;
        }

        return $property->primaryDomainName();
    }

    private function normalizedBoolean(mixed $value): ?bool
    {
        return is_bool($value) ? $value : null;
    }

    private function timestampString(mixed $value): ?string
    {
        $normalized = $this->normalizedText($value);

        if ($normalized === null) {
            return null;
        }

        try {
            return Carbon::parse($normalized)->toIso8601String();
        } catch (\Throwable) {
            return null;
        }
    }

    private function ga4MonitoringFinding(WebProperty $property): ?MonitoringFinding
    {
        /** @var Collection<int, MonitoringFinding>|null $loadedFindings */
        $loadedFindings = $property->relationLoaded('monitoringFindings')
            ? $property->getRelation('monitoringFindings')
            : null;

        if ($loadedFindings instanceof Collection) {
            $finding = $loadedFindings
                ->where('finding_type', 'marketing.ga4_install')
                ->sortByDesc(fn (MonitoringFinding $finding): mixed => $finding->last_detected_at)
                ->first();

            return $finding instanceof MonitoringFinding ? $finding : null;
        }

        if (! $property->exists) {
            return null;
        }

        $finding = $property->monitoringFindings()
            ->where('status', MonitoringFinding::STATUS_OPEN)
            ->where('finding_type', 'marketing.ga4_install')
            ->orderByDesc('last_detected_at')
            ->first();

        return $finding instanceof MonitoringFinding ? $finding : null;
    }

    /**
     * @return array{
     *   status:string,
     *   label:string,
     *   reason:string|null
     * }
     */
    private function ga4LookupState(
        PropertyAnalyticsSource $source,
        ?string $measurementId,
        ?MonitoringFinding $finding
    ): array {
        if ($source->status === 'planned') {
            return [
                'status' => 'provisioning',
                'label' => 'Provisioning',
                'reason' => 'MM-Google has matched the property but the GA4 measurement ID is not provisioned yet.',
            ];
        }

        if ($measurementId === null) {
            return [
                'status' => 'missing',
                'label' => 'Missing',
                'reason' => 'The GA4 source is present but no measurement ID is stored yet.',
            ];
        }

        if (! $finding instanceof MonitoringFinding) {
            return [
                'status' => 'configured',
                'label' => 'Configured',
                'reason' => 'A GA4 measurement ID is stored for this property.',
            ];
        }

        $verdict = $this->normalizedText(data_get($finding->evidence, 'verdict'));

        return match ($verdict) {
            'missing_ga4', 'missing_expected_measurement_id' => [
                'status' => 'live_missing',
                'label' => 'Live missing',
                'reason' => $finding->summary,
            ],
            'wrong_measurement_id' => [
                'status' => 'live_wrong',
                'label' => 'Live wrong',
                'reason' => $finding->summary,
            ],
            default => [
                'status' => 'needs_attention',
                'label' => 'Needs attention',
                'reason' => $finding->summary,
            ],
        };
    }

    /**
     * @return array{
     *   verdict:string|null,
     *   detected_measurement_ids: array<int, string>,
     *   issue_id:string|null
     * }
     */
    private function ga4DetectionSummary(?MonitoringFinding $finding): array
    {
        if (! $finding instanceof MonitoringFinding) {
            return [
                'verdict' => null,
                'detected_measurement_ids' => [],
                'issue_id' => null,
            ];
        }

        $detectedMeasurementIds = data_get($finding->evidence, 'detected_measurement_ids');

        return [
            'verdict' => $this->normalizedText(data_get($finding->evidence, 'verdict')),
            'detected_measurement_ids' => is_array($detectedMeasurementIds)
                ? array_values(array_filter($detectedMeasurementIds, fn (mixed $value): bool => is_string($value) && trim($value) !== ''))
                : [],
            'issue_id' => $finding->issue_id,
        ];
    }

    /**
     * @param  array<string, string|null>  $config
     */
    private function filledConfigValue(array $config, string $key): bool
    {
        return isset($config[$key]) && $config[$key] !== '';
    }
}
