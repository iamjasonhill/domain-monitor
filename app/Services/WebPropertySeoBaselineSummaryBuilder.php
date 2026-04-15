<?php

namespace App\Services;

use App\Models\DomainSeoBaseline;
use App\Models\WebProperty;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class WebPropertySeoBaselineSummaryBuilder
{
    /**
     * @return array{
     *   has_baseline: bool,
     *   latest: array{
     *     captured_at: string|null,
     *     baseline_type: string|null,
     *     indexed_pages: int|null,
     *     not_indexed_pages: int|null,
     *     clicks: float|null,
     *     impressions: float|null,
     *     ctr: float|null,
     *     average_position: float|null
     *   },
     *   trend: array{
     *     window: string,
     *     point_count: int,
     *     indexed_pages_delta: int|null,
     *     not_indexed_pages_delta: int|null,
     *     points: array<int, array{
     *       captured_at: string|null,
     *       baseline_type: string|null,
     *       indexed_pages: int|null,
     *       not_indexed_pages: int|null,
     *       clicks: float|null,
     *       impressions: float|null
     *     }>
     *   }
     * }
     */
    public function build(WebProperty $property): array
    {
        $points = $this->recentBaselines($property);
        $latest = $points->last();

        return [
            'has_baseline' => $latest instanceof DomainSeoBaseline,
            'latest' => $this->latestSummary($latest),
            'trend' => [
                'window' => 'last_12_checkpoints',
                'point_count' => $points->count(),
                'indexed_pages_delta' => $this->delta($points, 'indexed_pages'),
                'not_indexed_pages_delta' => $this->delta($points, 'not_indexed_pages'),
                'points' => $points
                    ->map(fn (DomainSeoBaseline $baseline): array => [
                        'captured_at' => $this->capturedAt($baseline),
                        'baseline_type' => $baseline->baseline_type,
                        'indexed_pages' => $this->nullableInt($baseline->indexed_pages),
                        'not_indexed_pages' => $this->nullableInt($baseline->not_indexed_pages),
                        'clicks' => $this->nullableFloat($baseline->clicks),
                        'impressions' => $this->nullableFloat($baseline->impressions),
                    ])
                    ->values()
                    ->all(),
            ],
        ];
    }

    /**
     * @return Collection<int, DomainSeoBaseline>
     */
    private function recentBaselines(WebProperty $property): Collection
    {
        /** @var Collection<int, DomainSeoBaseline>|null $loadedBaselines */
        $loadedBaselines = $property->relationLoaded('seoBaselines')
            ? $property->getRelation('seoBaselines')
            : null;

        /** @var Collection<int, DomainSeoBaseline> $recentBaselines */
        $recentBaselines = $loadedBaselines instanceof Collection
            ? $loadedBaselines->take(12)->values()
            : $property->seoBaselines()
                ->orderByDesc('captured_at')
                ->orderByDesc('created_at')
                ->limit(12)
                ->get();

        return $recentBaselines->reverse()->values();
    }

    /**
     * @return array{
     *   captured_at: string|null,
     *   baseline_type: string|null,
     *   indexed_pages: int|null,
     *   not_indexed_pages: int|null,
     *   clicks: float|null,
     *   impressions: float|null,
     *   ctr: float|null,
     *   average_position: float|null
     * }
     */
    private function latestSummary(?DomainSeoBaseline $baseline): array
    {
        return [
            'captured_at' => $this->capturedAt($baseline),
            'baseline_type' => $baseline?->baseline_type,
            'indexed_pages' => $this->nullableInt($baseline?->indexed_pages),
            'not_indexed_pages' => $this->nullableInt($baseline?->not_indexed_pages),
            'clicks' => $this->nullableFloat($baseline?->clicks),
            'impressions' => $this->nullableFloat($baseline?->impressions),
            'ctr' => $this->nullableFloat($baseline?->ctr),
            'average_position' => $this->nullableFloat($baseline?->average_position),
        ];
    }

    /**
     * @param  Collection<int, DomainSeoBaseline>  $points
     */
    private function delta(Collection $points, string $field): ?int
    {
        $first = $this->nullableInt($points->first()?->getAttribute($field));
        $last = $this->nullableInt($points->last()?->getAttribute($field));

        if ($first === null || $last === null) {
            return null;
        }

        return $last - $first;
    }

    private function capturedAt(?DomainSeoBaseline $baseline): ?string
    {
        if (! $baseline instanceof DomainSeoBaseline) {
            return null;
        }

        $rawValue = $baseline->getRawOriginal('captured_at');

        if (! is_string($rawValue) || trim($rawValue) === '') {
            return null;
        }

        return Carbon::parse($rawValue)->toIso8601String();
    }

    private function nullableInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private function nullableFloat(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }
}
