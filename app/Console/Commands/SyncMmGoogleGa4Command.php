<?php

namespace App\Console\Commands;

use App\Models\PropertyAnalyticsSource;
use App\Models\WebProperty;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class SyncMmGoogleGa4Command extends Command
{
    protected $signature = 'analytics:sync-mm-google-ga4
                            {--config-path=/Users/jasonhill/Projects/Business/operations/MM-Google/config/sites.json : Path to MM-Google sites.json}
                            {--workspace-path=/Users/jasonhill/Projects/Business/operations/MM-Google : Workspace path recorded on analytics source rows}
                            {--dry-run : Report changes without writing them}';

    protected $description = 'Sync GA4 bindings from MM-Google into property analytics sources';

    public function handle(): int
    {
        $configPath = $this->optionString('config-path');
        $workspacePath = $this->optionString('workspace-path');
        $dryRun = (bool) $this->option('dry-run');

        if ($configPath === null || ! File::exists($configPath)) {
            $this->error('Could not find the MM-Google config file.');

            return self::FAILURE;
        }

        $decoded = json_decode((string) File::get($configPath), true);

        if (! is_array($decoded)) {
            $this->error('MM-Google config did not decode into an array.');

            return self::FAILURE;
        }

        $defaults = is_array($decoded['defaults'] ?? null) ? $decoded['defaults'] : [];
        $sites = $decoded['sites'] ?? null;

        if (! is_array($sites)) {
            $this->error('MM-Google config is missing a valid sites array.');

            return self::FAILURE;
        }

        $matchedFleet = 0;
        $matchedNonFleet = 0;
        $siteKeysUpdated = 0;
        $created = 0;
        $updated = 0;
        $unchanged = 0;
        $unmatched = [];
        $syncedAt = now();

        foreach ($sites as $site) {
            if (! is_array($site)) {
                continue;
            }

            $host = $this->hostFromUrl(Arr::get($site, 'websiteUrl'));

            if ($host === null) {
                $unmatched[] = [
                    'key' => (string) Arr::get($site, 'key', 'unknown'),
                    'host' => null,
                    'reason' => 'invalid websiteUrl',
                ];

                continue;
            }

            $property = $this->matchPropertyByHost($host);

            if (! $property instanceof WebProperty) {
                $unmatched[] = [
                    'key' => (string) Arr::get($site, 'key', 'unknown'),
                    'host' => $host,
                    'reason' => 'no matching web property',
                ];

                continue;
            }

            if ($property->isFleetFocus()) {
                $matchedFleet++;
            } else {
                $matchedNonFleet++;
            }

            if ($this->syncPropertySiteKey($property, Arr::get($site, 'key'), $dryRun)) {
                $siteKeysUpdated++;
            }

            $source = $property->analyticsSources()
                ->where('provider', 'ga4')
                ->first();

            $attributes = $this->ga4SourceAttributes(
                $property,
                $source instanceof PropertyAnalyticsSource ? $source : null,
                $site,
                $defaults,
                $workspacePath,
                $syncedAt,
            );

            if (! $source instanceof PropertyAnalyticsSource) {
                $created++;

                $this->line(sprintf(
                    '[create] %s <- %s (%s)',
                    $property->slug,
                    (string) Arr::get($site, 'key', 'unknown'),
                    $host
                ));

                if (! $dryRun) {
                    PropertyAnalyticsSource::query()->create([
                        'web_property_id' => $property->id,
                        ...$attributes,
                    ]);
                }

                continue;
            }

            if ($this->sourceMatches($source, $attributes)) {
                $unchanged++;
                $this->line(sprintf(
                    '[unchanged] %s <- %s (%s)',
                    $property->slug,
                    (string) Arr::get($site, 'key', 'unknown'),
                    $host
                ));

                continue;
            }

            $updated++;

            $this->line(sprintf(
                '[update] %s <- %s (%s)',
                $property->slug,
                (string) Arr::get($site, 'key', 'unknown'),
                $host
            ));

            if (! $dryRun) {
                $source->forceFill($attributes)->save();
            }
        }

        $this->newLine();
        $this->info('MM-Google GA4 sync summary');
        $this->line(sprintf('Matched in registry and fleet: %d', $matchedFleet));
        $this->line(sprintf('Matched in registry but not fleet: %d', $matchedNonFleet));
        $this->line(sprintf('Property site keys updated: %d', $siteKeysUpdated));
        $this->line(sprintf('Created GA4 sources: %d', $created));
        $this->line(sprintf('Updated GA4 sources: %d', $updated));
        $this->line(sprintf('Unchanged GA4 sources: %d', $unchanged));
        $this->line(sprintf('Unmatched MM-Google sites: %d', count($unmatched)));

        if ($unmatched !== []) {
            $this->newLine();
            $this->warn('Unmatched entries:');

            foreach ($unmatched as $entry) {
                $this->line(sprintf(
                    '  - %s (%s): %s',
                    $entry['key'],
                    $entry['host'] ?? 'no-host',
                    $entry['reason']
                ));
            }
        }

        if ($dryRun) {
            $this->newLine();
            $this->info('Dry run complete. No changes were written.');
        }

        return self::SUCCESS;
    }

    private function optionString(string $name): ?string
    {
        $value = $this->option($name);

        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }

    private function hostFromUrl(mixed $url): ?string
    {
        if (! is_string($url) || trim($url) === '') {
            return null;
        }

        $host = parse_url(trim($url), PHP_URL_HOST);

        if (! is_string($host) || trim($host) === '') {
            return null;
        }

        return Str::lower($host);
    }

    private function matchPropertyByHost(string $host): ?WebProperty
    {
        return WebProperty::query()
            ->with(['domains.tags', 'primaryDomain.tags'])
            ->where(function ($query) use ($host): void {
                $query
                    ->whereHas('domains', fn ($domainQuery) => $domainQuery->where('domain', $host))
                    ->orWhereHas('primaryDomain', fn ($domainQuery) => $domainQuery->where('domain', $host))
                    ->orWhere('production_url', 'like', 'https://'.$host.'%')
                    ->orWhere('production_url', 'like', 'http://'.$host.'%');
            })
            ->orderByDesc('priority')
            ->first();
    }

    private function syncPropertySiteKey(WebProperty $property, mixed $siteKey, bool $dryRun): bool
    {
        $normalizedSiteKey = $this->nullableString($siteKey);

        if ($normalizedSiteKey === null || $property->site_key === $normalizedSiteKey) {
            return false;
        }

        $this->line(sprintf(
            '[site-key] %s <- %s',
            $property->slug,
            $normalizedSiteKey
        ));

        if (! $dryRun) {
            $property->forceFill([
                'site_key' => $normalizedSiteKey,
            ])->save();
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $site
     * @param  array<string, mixed>  $defaults
     * @return array<string, mixed>
     */
    private function ga4SourceAttributes(
        WebProperty $property,
        ?PropertyAnalyticsSource $existingSource,
        array $site,
        array $defaults,
        ?string $workspacePath,
        Carbon $syncedAt,
    ): array {
        $measurementId = $this->nullableString(Arr::get($site, 'measurementId'));
        $propertyId = $this->nullableString(Arr::get($site, 'propertyId'));
        $streamId = $this->nullableString(Arr::get($site, 'streamId'));
        $switchReady = $this->switchReadyState($site, $measurementId);
        $provisioningState = $this->provisioningState($site, $measurementId, $switchReady);
        $hasNonGa4Primary = $property->analyticsSources()
            ->where('is_primary', true)
            ->where('provider', '!=', 'ga4')
            ->exists();
        $hasExistingPrimary = $property->analyticsSources()
            ->where('is_primary', true)
            ->exists();

        return [
            'provider' => 'ga4',
            'external_id' => $measurementId ?? $propertyId ?? (string) Arr::get($site, 'key', $property->slug),
            'external_name' => $this->nullableString(Arr::get($site, 'displayName')) ?? $property->name,
            'workspace_path' => $workspacePath,
            'provider_config' => [
                'site_key' => $this->nullableString(Arr::get($site, 'key')),
                'website_url' => $this->nullableString(Arr::get($site, 'websiteUrl')),
                'analytics_account' => $this->nullableString(Arr::get($site, 'analyticsAccount')),
                'bigquery_project' => $this->nullableString(Arr::get($site, 'bigQueryProject')),
                'property_id' => $propertyId,
                'stream_id' => $streamId,
                'measurement_id' => $measurementId,
                'measurement_protocol_secret_name' => $this->nullableString(Arr::get($site, 'measurementProtocolSecretName')),
                'time_zone' => $this->nullableString(Arr::get($site, 'timeZone')) ?? $this->nullableString(Arr::get($defaults, 'timeZone')),
                'currency_code' => $this->nullableString(Arr::get($site, 'currencyCode')) ?? $this->nullableString(Arr::get($defaults, 'currencyCode')),
                'industry_category' => $this->nullableString(Arr::get($site, 'industryCategory')) ?? $this->nullableString(Arr::get($defaults, 'industryCategory')),
                'bigquery_dataset_location' => $this->nullableString(Arr::get($site, 'bigQueryDatasetLocation')) ?? $this->nullableString(Arr::get($defaults, 'bigQueryDatasetLocation')),
                'provisioning' => $this->normalizedArray(Arr::get($site, 'provisioning')) ?? $this->normalizedArray(Arr::get($defaults, 'provisioning')),
                'provisioning_state' => $provisioningState,
                'switch_ready' => $switchReady,
                'tags' => $this->normalizedList(Arr::get($site, 'tags')),
                'key_events' => $this->normalizedList(Arr::get($site, 'keyEvents')) ?? $this->normalizedList(Arr::get($defaults, 'keyEvents')),
                'custom_dimensions' => $this->normalizedArray(Arr::get($site, 'customDimensions')) ?? $this->normalizedArray(Arr::get($defaults, 'customDimensions')),
                'source_system' => 'MM-Google',
                'synced_from' => 'MM-Google/config/sites.json',
                'last_synced_at' => $syncedAt->toIso8601String(),
            ],
            'is_primary' => $hasNonGa4Primary ? false : ($existingSource instanceof PropertyAnalyticsSource ? $existingSource->is_primary : ! $hasExistingPrimary),
            'status' => $measurementId !== null ? 'active' : 'planned',
            'notes' => 'Synced from MM-Google by website host match.',
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function sourceMatches(PropertyAnalyticsSource $source, array $attributes): bool
    {
        return $source->provider === $attributes['provider']
            && $source->external_id === $attributes['external_id']
            && $source->external_name === $attributes['external_name']
            && $source->workspace_path === $attributes['workspace_path']
            && $source->is_primary === $attributes['is_primary']
            && $source->status === $attributes['status']
            && $source->notes === $attributes['notes']
            && $source->provider_config === $attributes['provider_config'];
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }

    /**
     * @param  array<string, mixed>  $site
     */
    private function switchReadyState(array $site, ?string $measurementId): ?bool
    {
        foreach (['switchReady', 'switch_ready'] as $key) {
            $value = Arr::get($site, $key);

            if (is_bool($value)) {
                return $value;
            }
        }

        $readinessStatus = $this->nullableString(Arr::get($site, 'readinessStatus'))
            ?? $this->nullableString(Arr::get($site, 'readiness_status'));

        if ($readinessStatus !== null) {
            return in_array(Str::lower($readinessStatus), ['ready', 'switch_ready', 'switch-ready'], true);
        }

        return $measurementId !== null ? true : null;
    }

    /**
     * @param  array<string, mixed>  $site
     */
    private function provisioningState(array $site, ?string $measurementId, ?bool $switchReady): string
    {
        $explicit = $this->nullableString(Arr::get($site, 'provisioningState'))
            ?? $this->nullableString(Arr::get($site, 'provisioning_state'))
            ?? $this->nullableString(Arr::get($site, 'readinessStatus'))
            ?? $this->nullableString(Arr::get($site, 'readiness_status'));

        if ($explicit !== null) {
            return Str::snake($explicit, '_');
        }

        if ($switchReady === true || $measurementId !== null) {
            return 'switch_ready';
        }

        return 'provisioning';
    }

    /**
     * @return array<int|string, mixed>|null
     */
    private function normalizedArray(mixed $value): ?array
    {
        return is_array($value) ? $value : null;
    }

    /**
     * @return array<int, string>|null
     */
    private function normalizedList(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        $items = array_values(array_filter(array_map(function (mixed $item): ?string {
            return is_string($item) && trim($item) !== '' ? trim($item) : null;
        }, $value)));

        return $items !== [] ? $items : null;
    }
}
