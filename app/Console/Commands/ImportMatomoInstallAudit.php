<?php

namespace App\Console\Commands;

use App\Models\AnalyticsInstallAudit;
use App\Models\AnalyticsSourceObservation;
use App\Models\PropertyAnalyticsSource;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class ImportMatomoInstallAudit extends Command
{
    protected $signature = 'analytics:import-matomo-audit
                            {path : Path to the Matomo install verification JSON export}';

    protected $description = 'Import a Matomo install audit export and attach the latest audit state to linked analytics sources.';

    public function handle(): int
    {
        $path = (string) $this->argument('path');

        if (! is_file($path)) {
            $this->error(sprintf('Audit file not found at [%s].', $path));

            return self::FAILURE;
        }

        $payload = json_decode((string) file_get_contents($path), true);

        if (! is_array($payload)) {
            $this->error('Audit file does not contain valid JSON.');

            return self::FAILURE;
        }

        if (($payload['source_system'] ?? null) !== 'matamo') {
            $this->error('Expected a Matomo audit export.');

            return self::FAILURE;
        }

        if (($payload['contract_version'] ?? null) !== 1) {
            $this->error('Unsupported Matomo audit contract version.');

            return self::FAILURE;
        }

        $audits = $payload['install_audits'] ?? $payload['sites'] ?? null;

        if (! is_array($audits)) {
            $this->error('Audit payload is missing install_audits.');

            return self::FAILURE;
        }

        $imported = 0;
        $unmapped = 0;

        foreach ($audits as $audit) {
            if (! is_array($audit)) {
                continue;
            }

            $externalId = (string) ($audit['id_site'] ?? '');
            if ($externalId === '') {
                continue;
            }

            $source = PropertyAnalyticsSource::query()
                ->where('provider', 'matomo')
                ->where('external_id', $externalId)
                ->first();

            $observationAttributes = [
                'external_name' => $audit['site_name'] ?? null,
                'expected_tracker_host' => $audit['expected_tracker_host'] ?? null,
                'install_verdict' => $audit['verdict'] ?? 'unknown',
                'best_url' => $audit['best_url'] ?? null,
                'detected_site_ids' => $audit['detected_site_ids'] ?? [],
                'detected_tracker_hosts' => $audit['detected_tracker_hosts'] ?? [],
                'summary' => $audit['summary'] ?? null,
                'checked_at' => isset($payload['generated_at']) ? Carbon::parse($payload['generated_at']) : now(),
                'raw_payload' => $audit,
            ];

            if (! $source instanceof PropertyAnalyticsSource) {
                AnalyticsSourceObservation::query()->updateOrCreate(
                    [
                        'provider' => 'matomo',
                        'external_id' => $externalId,
                    ],
                    array_merge($observationAttributes, [
                        'matched_property_analytics_source_id' => null,
                        'matched_web_property_id' => null,
                    ])
                );

                $unmapped++;
                $this->warn(sprintf('No linked property analytics source found for Matomo site [%s].', $externalId));

                continue;
            }

            if (($audit['site_name'] ?? null) && $source->external_name !== $audit['site_name']) {
                $source->forceFill([
                    'external_name' => $audit['site_name'],
                ])->save();
            }

            AnalyticsSourceObservation::query()->updateOrCreate(
                [
                    'provider' => 'matomo',
                    'external_id' => $externalId,
                ],
                array_merge($observationAttributes, [
                    'matched_property_analytics_source_id' => $source->id,
                    'matched_web_property_id' => $source->web_property_id,
                ])
            );

            AnalyticsInstallAudit::query()->updateOrCreate(
                ['property_analytics_source_id' => $source->id],
                [
                    'web_property_id' => $source->web_property_id,
                    'provider' => 'matomo',
                    'external_id' => $source->external_id,
                    'external_name' => $audit['site_name'] ?? $source->external_name,
                    'expected_tracker_host' => $audit['expected_tracker_host'] ?? null,
                    'install_verdict' => $audit['verdict'] ?? 'unknown',
                    'best_url' => $audit['best_url'] ?? null,
                    'detected_site_ids' => $audit['detected_site_ids'] ?? [],
                    'detected_tracker_hosts' => $audit['detected_tracker_hosts'] ?? [],
                    'summary' => $audit['summary'] ?? null,
                    'checked_at' => isset($payload['generated_at']) ? Carbon::parse($payload['generated_at']) : now(),
                    'raw_payload' => $audit,
                ]
            );

            $imported++;
        }

        $this->info(sprintf('Imported %d Matomo install audit records.', $imported));

        if ($unmapped > 0) {
            $this->warn(sprintf('%d Matomo site(s) were not mapped to a property analytics source.', $unmapped));
        }

        return self::SUCCESS;
    }
}
