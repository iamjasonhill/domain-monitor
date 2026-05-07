<?php

namespace App\Console\Commands;

use App\Models\PropertyAnalyticsSource;
use App\Models\WebProperty;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ArchiveLegacyMatomoSources extends Command
{
    protected $signature = 'analytics:archive-legacy-matomo-sources
                            {--write : Apply the archive/promote changes. Without this flag the command is a dry run}
                            {--domain=* : Limit to one or more primary domain names}';

    protected $description = 'Archive legacy Matomo analytics sources and promote valid GA4 sources where available';

    public function handle(): int
    {
        $write = (bool) $this->option('write');
        $targetDomains = collect((array) $this->option('domain'))
            ->filter(fn ($domain): bool => is_string($domain) && trim($domain) !== '')
            ->map(fn (string $domain): string => mb_strtolower(trim($domain)))
            ->values();

        $properties = WebProperty::query()
            ->with(['primaryDomain', 'analyticsSources'])
            ->whereHas('analyticsSources', fn ($query) => $query->where('provider', 'matomo'))
            ->when(
                $targetDomains->isNotEmpty(),
                fn ($query) => $query->whereHas('primaryDomain', fn ($domainQuery) => $domainQuery->whereIn('domain', $targetDomains->all()))
            )
            ->orderBy('name')
            ->get();

        if ($properties->isEmpty()) {
            $this->warn('No Matomo analytics sources matched the requested scope.');

            return self::SUCCESS;
        }

        $rows = [];
        $matomoSourcesToArchive = 0;
        $ga4SourcesToPromote = 0;
        $propertiesWithoutGa4 = 0;
        $archivedAt = now();

        foreach ($properties as $property) {
            $matomoSources = $this->matomoSourcesFor($property);
            $validGa4Source = $this->validGa4SourceFor($property);

            if (! $validGa4Source instanceof PropertyAnalyticsSource) {
                $propertiesWithoutGa4++;
            } elseif (! $validGa4Source->is_primary) {
                $ga4SourcesToPromote++;
            }

            foreach ($matomoSources as $matomoSource) {
                if ($this->matomoNeedsArchive($matomoSource)) {
                    $matomoSourcesToArchive++;
                }

                $rows[] = [
                    $property->slug,
                    $property->primaryDomainName() ?? '-',
                    $matomoSource->external_id,
                    $matomoSource->status,
                    $matomoSource->is_primary ? 'yes' : 'no',
                    $validGa4Source instanceof PropertyAnalyticsSource ? $validGa4Source->external_id : 'none',
                    $validGa4Source instanceof PropertyAnalyticsSource ? 'promote_ga4' : 'ga4_gap',
                ];
            }

            if ($write) {
                $this->archiveMatomoSources($matomoSources, $archivedAt);
                $this->promoteGa4Source($property, $validGa4Source);
            }
        }

        $this->table(
            ['Property', 'Domain', 'Matomo ID', 'Matomo status', 'Matomo primary', 'Intended GA4', 'Action'],
            $rows
        );

        $this->table(
            ['Metric', 'Value'],
            [
                ['properties_considered', (string) $properties->count()],
                ['matomo_sources_to_archive', (string) $matomoSourcesToArchive],
                ['ga4_sources_to_promote', (string) $ga4SourcesToPromote],
                ['properties_without_valid_ga4', (string) $propertiesWithoutGa4],
                ['write_mode', $write ? 'true' : 'false'],
            ]
        );

        if (! $write) {
            $this->newLine();
            $this->info('Dry run complete. Re-run with --write to archive Matomo sources and promote valid GA4 sources.');
        }

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, PropertyAnalyticsSource>
     */
    private function matomoSourcesFor(WebProperty $property): Collection
    {
        return $property->analyticsSources
            ->filter(fn (PropertyAnalyticsSource $source): bool => $source->provider === 'matomo')
            ->values();
    }

    private function validGa4SourceFor(WebProperty $property): ?PropertyAnalyticsSource
    {
        return $property->analyticsSources
            ->filter(fn (PropertyAnalyticsSource $source): bool => $this->isValidGa4Source($source))
            ->sortByDesc(fn (PropertyAnalyticsSource $source): bool => $source->is_primary)
            ->first();
    }

    private function isValidGa4Source(PropertyAnalyticsSource $source): bool
    {
        return $source->provider === 'ga4'
            && $source->status === 'active'
            && $this->ga4MeasurementId($source) !== null;
    }

    private function ga4MeasurementId(PropertyAnalyticsSource $source): ?string
    {
        $measurementId = data_get($source->provider_config, 'measurement_id');

        if (is_string($measurementId) && trim($measurementId) !== '') {
            return trim($measurementId);
        }

        return trim($source->external_id) !== '' ? $source->external_id : null;
    }

    private function matomoNeedsArchive(PropertyAnalyticsSource $source): bool
    {
        return $source->status !== 'archived' || $source->is_primary;
    }

    /**
     * @param  Collection<int, PropertyAnalyticsSource>  $matomoSources
     */
    private function archiveMatomoSources(Collection $matomoSources, Carbon $archivedAt): void
    {
        foreach ($matomoSources as $source) {
            if (! $this->matomoNeedsArchive($source)) {
                continue;
            }

            $source->forceFill([
                'is_primary' => false,
                'status' => 'archived',
                'notes' => $this->archiveNotes($source, $archivedAt),
            ])->save();
        }
    }

    private function promoteGa4Source(WebProperty $property, ?PropertyAnalyticsSource $ga4Source): void
    {
        if (! $ga4Source instanceof PropertyAnalyticsSource) {
            return;
        }

        $property->analyticsSources()
            ->where('provider', 'ga4')
            ->where('id', '!=', $ga4Source->id)
            ->update(['is_primary' => false]);

        if (! $ga4Source->is_primary) {
            $ga4Source->forceFill(['is_primary' => true])->save();
        }
    }

    private function archiveNotes(PropertyAnalyticsSource $source, Carbon $archivedAt): string
    {
        $archiveNote = sprintf(
            'Archived by analytics:archive-legacy-matomo-sources on %s; legacy archive/backfill only.',
            $archivedAt->toDateString()
        );

        $currentNotes = is_string($source->notes) ? trim($source->notes) : '';

        if ($currentNotes === '') {
            return $archiveNote;
        }

        if (str_contains($currentNotes, 'analytics:archive-legacy-matomo-sources')) {
            return $currentNotes;
        }

        return $currentNotes."\n".$archiveNote;
    }
}
