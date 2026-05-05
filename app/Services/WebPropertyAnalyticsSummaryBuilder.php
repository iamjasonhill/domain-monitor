<?php

namespace App\Services;

use App\Models\AnalyticsEventContract;
use App\Models\AnalyticsInstallAudit;
use App\Models\MonitoringFinding;
use App\Models\PropertyAnalyticsSource;
use App\Models\WebProperty;
use App\Models\WebPropertyEventContract;
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
            return [
                ...$default,
                'marketing_interaction_v2' => $this->marketingInteractionV2Summary($property, $default),
            ];
        }

        $config = is_array($source->provider_config) ? $source->provider_config : [];
        $measurementId = $this->ga4ConfigValue($source, 'measurement_id') ?? $this->normalizedText($source->external_id);
        $finding = $this->ga4MonitoringFinding($property);
        $detection = $this->ga4DetectionSummary($finding);
        $derivedState = $this->ga4LookupState($source, $measurementId, $finding);
        $ga4Summary = [
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

        return [
            ...$ga4Summary,
            'marketing_interaction_v2' => $this->marketingInteractionV2Summary($property, $ga4Summary),
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
                ->where('status', MonitoringFinding::STATUS_OPEN)
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
     * @param  array<string, mixed>  $ga4Summary
     * @return array<string, mixed>
     */
    private function marketingInteractionV2Summary(WebProperty $property, array $ga4Summary): array
    {
        $assignment = $this->marketingInteractionV2Assignment($property);
        $contract = $assignment?->eventContract;
        $definition = $contract?->contract;

        if (! $assignment instanceof WebPropertyEventContract || ! $contract instanceof AnalyticsEventContract) {
            return [
                'status' => 'missing',
                'label' => 'Missing',
                'reason' => 'No MM-Google marketing interaction v2 contract is assigned to this property yet.',
                'source_system' => 'MM-Google',
                'contract_key' => 'marketing-interaction-v2',
                'contract_version' => 'v2',
                'source_repo' => 'MM-Google',
                'source_path' => 'docs/event-taxonomy.md',
                'rollout_status' => null,
                'verified_at' => null,
                'base_readiness' => $this->marketingInteractionBaseReadiness($ga4Summary, null),
                'events' => [],
                'standard_parameters' => [],
                'optional_surface_events' => [],
                'transition_aliases' => [],
            ];
        }

        $rolloutStatus = $this->normalizedText($assignment->rollout_status) ?? 'defined';
        $state = $this->marketingInteractionV2State($rolloutStatus);

        return [
            'status' => $state['status'],
            'label' => $state['label'],
            'reason' => $state['reason'],
            'source_system' => $contract->source_repo,
            'contract_key' => $contract->key,
            'contract_version' => $contract->version,
            'source_repo' => $contract->source_repo,
            'source_path' => $contract->source_path,
            'rollout_status' => $rolloutStatus,
            'verified_at' => $assignment->verified_at?->toIso8601String(),
            'base_readiness' => $this->marketingInteractionBaseReadiness($ga4Summary, $rolloutStatus),
            'events' => $this->stringList(data_get($definition, 'events')),
            'standard_parameters' => $this->stringList(data_get($definition, 'standard_parameters')),
            'optional_surface_events' => $this->arrayMapOfStringLists(data_get($definition, 'optional_surface_events')),
            'transition_aliases' => $this->stringList(data_get($definition, 'transition_aliases')),
        ];
    }

    private function marketingInteractionV2Assignment(WebProperty $property): ?WebPropertyEventContract
    {
        /** @var Collection<int, WebPropertyEventContract>|null $loadedAssignments */
        $loadedAssignments = $property->relationLoaded('eventContractAssignments')
            ? $property->getRelation('eventContractAssignments')
            : null;

        if (! $loadedAssignments instanceof Collection && ! $property->exists) {
            return null;
        }

        $assignments = $loadedAssignments instanceof Collection
            ? $loadedAssignments
            : $property->eventContractAssignments()->with('eventContract')->get();

        $assignment = $assignments
            ->sortByDesc(fn (WebPropertyEventContract $assignment): bool => $assignment->is_primary)
            ->first(fn (WebPropertyEventContract $assignment): bool => $assignment->eventContract?->key === 'marketing-interaction-v2');

        return $assignment instanceof WebPropertyEventContract ? $assignment : null;
    }

    /**
     * @return array{status: string, label: string, reason: string}
     */
    private function marketingInteractionV2State(string $rolloutStatus): array
    {
        return match ($rolloutStatus) {
            'verified', 'ready' => [
                'status' => 'ready',
                'label' => 'Ready',
                'reason' => 'MM-Google or Fleet evidence marks marketing interaction v2 as ready for the relevant site surfaces.',
            ],
            'instrumented', 'partial' => [
                'status' => 'partial',
                'label' => 'Partial',
                'reason' => 'Some marketing interaction v2 evidence is present, but the relevant surface set is not fully verified yet.',
            ],
            'not_applicable', 'suppressed' => [
                'status' => 'not_applicable',
                'label' => 'Not applicable',
                'reason' => 'Marketing interaction v2 is not required for this property or surface set.',
            ],
            default => [
                'status' => 'missing',
                'label' => 'Missing',
                'reason' => 'The MM-Google marketing interaction v2 contract is assigned but has not been promoted with adoption evidence yet.',
            ],
        };
    }

    /**
     * @param  array<string, mixed>  $ga4Summary
     * @return array<string, array{status: string, label: string, reason: string|null}>
     */
    private function marketingInteractionBaseReadiness(array $ga4Summary, ?string $rolloutStatus): array
    {
        $ga4Status = $this->normalizedText($ga4Summary['status'] ?? null);
        $ga4Ready = $ga4Status === 'configured';
        $interactionStatus = $this->normalizedText($rolloutStatus);
        $hasInteractionEvidence = in_array($interactionStatus, ['instrumented', 'partial', 'verified', 'ready'], true);
        $isFullyVerified = in_array($interactionStatus, ['verified', 'ready'], true);

        return [
            'ga4_installed' => [
                'status' => $ga4Ready ? 'ready' : 'missing',
                'label' => $ga4Ready ? 'Ready' : 'Missing',
                'reason' => $ga4Ready
                    ? 'The expected MM-Google GA4 measurement ID is configured for this property.'
                    : $this->normalizedText($ga4Summary['reason'] ?? null),
            ],
            'mmtrack_present' => [
                'status' => $hasInteractionEvidence ? 'ready' : 'unknown',
                'label' => $hasInteractionEvidence ? 'Ready' : 'Unknown',
                'reason' => $hasInteractionEvidence
                    ? 'Interaction evidence implies the shared mmTrack helper is present.'
                    : 'No MM-Google or Fleet interaction evidence has promoted the shared mmTrack helper yet.',
            ],
            'core_handoffs_present' => [
                'status' => $isFullyVerified ? 'ready' : 'unknown',
                'label' => $isFullyVerified ? 'Ready' : 'Unknown',
                'reason' => $isFullyVerified
                    ? 'The relevant core handoff instrumentation is verified for this property.'
                    : 'Core handoff readiness should be promoted from MM-Google or Fleet evidence.',
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn (mixed $item): ?string => $this->normalizedText($item),
            $value
        )));
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function arrayMapOfStringLists(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $mapped = [];

        foreach ($value as $key => $items) {
            if (! is_string($key)) {
                continue;
            }

            $strings = $this->stringList($items);
            if ($strings !== []) {
                $mapped[$key] = $strings;
            }
        }

        return $mapped;
    }

    /**
     * @param  array<string, string|null>  $config
     */
    private function filledConfigValue(array $config, string $key): bool
    {
        return isset($config[$key]) && $config[$key] !== '';
    }
}
