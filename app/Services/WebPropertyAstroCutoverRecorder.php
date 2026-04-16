<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\WebProperty;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;

class WebPropertyAstroCutoverRecorder
{
    /**
     * @return array{
     *   success: bool,
     *   property_slug: string,
     *   astro_cutover: array{
     *     recorded_at: string,
     *     baseline_refresh_requested: bool,
     *     baseline_refresh: array{
     *       status: string,
     *       baseline_type: string,
     *       message: string|null
     *     }
     *   },
     *   data: array<string, mixed>
     * }
     */
    public function record(
        string $slug,
        ?Carbon $astroCutoverAt = null,
        bool $refreshSeoBaseline = true,
        ?string $capturedBy = null,
        ?string $notes = null
    ): array {
        $property = $this->findProperty($slug);
        $recordedAt = ($astroCutoverAt ?? now())->copy();

        $property->forceFill([
            'astro_cutover_at' => $recordedAt,
        ])->save();

        $property = $this->findProperty($slug);

        return [
            'success' => true,
            'property_slug' => $property->slug,
            'astro_cutover' => [
                'recorded_at' => $recordedAt->toIso8601String(),
                'baseline_refresh_requested' => $refreshSeoBaseline,
                'baseline_refresh' => $this->refreshSeoBaseline(
                    $property,
                    $refreshSeoBaseline,
                    $recordedAt,
                    $capturedBy,
                    $notes,
                ),
            ],
            'data' => $this->findProperty($slug)->brainSummary(includeFullExternalLinks: false),
        ];
    }

    /**
     * @return array{
     *   status: string,
     *   baseline_type: string,
     *   message: string|null
     * }
     */
    private function refreshSeoBaseline(
        WebProperty $property,
        bool $refreshSeoBaseline,
        Carbon $recordedAt,
        ?string $capturedBy,
        ?string $notes
    ): array {
        $baselineType = 'astro_cutover';

        if (! $refreshSeoBaseline) {
            return [
                'status' => 'skipped',
                'baseline_type' => $baselineType,
                'message' => 'SEO baseline refresh was not requested.',
            ];
        }

        $domain = $property->primaryDomainModel();

        if (! $domain instanceof Domain) {
            return [
                'status' => 'skipped',
                'baseline_type' => $baselineType,
                'message' => 'Property has no primary domain linked for baseline refresh.',
            ];
        }

        if (! $property->primaryAnalyticsSource('matomo')) {
            return [
                'status' => 'skipped',
                'baseline_type' => $baselineType,
                'message' => 'Property does not have a Matomo analytics binding yet.',
            ];
        }

        $cutoverNotes = sprintf(
            'Astro cutover checkpoint recorded for %s at %s.',
            $property->slug,
            $recordedAt->toIso8601String(),
        );

        if (is_string($notes) && trim($notes) !== '') {
            $cutoverNotes .= ' '.trim($notes);
        }

        $exitCode = Artisan::call('analytics:sync-search-console-baseline', [
            '--domain' => $domain->domain,
            '--baseline-type' => $baselineType,
            '--captured-by' => $capturedBy ?: 'fleet',
            '--notes' => $cutoverNotes,
        ]);

        $output = trim(Artisan::output());

        return [
            'status' => $exitCode === 0 ? 'synced' : 'failed',
            'baseline_type' => $baselineType,
            'message' => $output !== '' ? $output : null,
        ];
    }

    private function findProperty(string $slug): WebProperty
    {
        $property = WebProperty::query()
            ->with([
                'primaryDomain.tags',
                'repositories',
                'analyticsSources',
                'analyticsSources.latestInstallAudit',
                'seoBaselines' => fn ($query) => $query
                    ->orderByDesc('captured_at')
                    ->orderByDesc('created_at')
                    ->limit(12),
                'propertyDomains.domain' => fn ($query) => $query
                    ->withLatestCheckStatuses()
                    ->with([
                        'platform',
                        'tags',
                        'deployments.domain',
                        'alerts' => fn ($alertQuery) => $alertQuery->whereNull('resolved_at'),
                    ]),
            ])
            ->where('slug', $slug)
            ->first();

        if (! $property instanceof WebProperty) {
            throw (new ModelNotFoundException)->setModel(WebProperty::class, [$slug]);
        }

        return $property;
    }
}
