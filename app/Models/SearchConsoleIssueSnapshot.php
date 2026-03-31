<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property string $id
 * @property string $domain_id
 * @property string $web_property_id
 * @property string|null $property_analytics_source_id
 * @property string $issue_class
 * @property string|null $source_issue_label
 * @property string $capture_method
 * @property string|null $source_report
 * @property string|null $source_property
 * @property string|null $artifact_path
 * @property \Illuminate\Support\Carbon $captured_at
 * @property string|null $captured_by
 * @property \Illuminate\Support\Carbon|null $first_detected_at
 * @property \Illuminate\Support\Carbon|null $last_updated_at
 * @property string|null $property_scope
 * @property int|null $affected_url_count
 * @property array<int, string>|null $sample_urls
 * @property array<int, array<string, mixed>>|null $examples
 * @property array<int, array<string, mixed>>|null $chart_points
 * @property array<string, mixed>|null $normalized_payload
 * @property array<string, mixed>|null $raw_payload
 */
class SearchConsoleIssueSnapshot extends Model
{
    /** @use HasFactory<\Database\Factories\SearchConsoleIssueSnapshotFactory> */
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'domain_id',
        'web_property_id',
        'property_analytics_source_id',
        'issue_class',
        'source_issue_label',
        'capture_method',
        'source_report',
        'source_property',
        'artifact_path',
        'captured_at',
        'captured_by',
        'first_detected_at',
        'last_updated_at',
        'property_scope',
        'affected_url_count',
        'sample_urls',
        'examples',
        'chart_points',
        'normalized_payload',
        'raw_payload',
    ];

    protected function casts(): array
    {
        return [
            'captured_at' => 'datetime',
            'first_detected_at' => 'date',
            'last_updated_at' => 'date',
            'affected_url_count' => 'integer',
            'sample_urls' => 'array',
            'examples' => 'array',
            'chart_points' => 'array',
            'normalized_payload' => 'array',
            'raw_payload' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $snapshot): void {
            if (empty($snapshot->id)) {
                $snapshot->id = Str::uuid()->toString();
            }
        });
    }

    /**
     * @return BelongsTo<Domain, $this>
     */
    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    /**
     * @return BelongsTo<WebProperty, $this>
     */
    public function webProperty(): BelongsTo
    {
        return $this->belongsTo(WebProperty::class);
    }

    /**
     * @return BelongsTo<PropertyAnalyticsSource, $this>
     */
    public function propertyAnalyticsSource(): BelongsTo
    {
        return $this->belongsTo(PropertyAnalyticsSource::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function issueEvidence(): array
    {
        /** @var array<string, mixed> $normalizedPayload */
        $normalizedPayload = $this->normalized_payload ?? [];
        /** @var array<int, array<string, mixed>> $exampleRows */
        $exampleRows = is_array($this->examples) ? $this->examples : [];
        $examples = [];

        foreach ($exampleRows as $example) {
            $url = is_string($example['url'] ?? null) ? $example['url'] : null;

            if ($url === null || $url === '') {
                continue;
            }

            $examples[] = [
                'url' => $url,
                'last_crawled' => $this->normalizeLastCrawledValue($example['last_crawled'] ?? null),
            ];
        }
        /** @var array<int, string> $affectedUrlRows */
        $affectedUrlRows = is_array($normalizedPayload['affected_urls'] ?? null) ? $normalizedPayload['affected_urls'] : [];
        $affectedUrls = collect($affectedUrlRows)
            ->filter(fn (string $url): bool => $url !== '')
            ->values()
            ->all();

        if ($affectedUrls === [] && $examples !== []) {
            $affectedUrls = array_values(array_unique(array_map(
                static fn (array $example): string => $example['url'],
                $examples
            )));
        }

        $exactExampleCount = count($affectedUrls);
        $affectedUrlCount = $this->affected_url_count;
        $isExampleSetTruncated = $exactExampleCount > 0
            && is_int($affectedUrlCount)
            && $affectedUrlCount > $exactExampleCount;

        return array_filter([
            'affected_urls' => $affectedUrls !== [] ? $affectedUrls : null,
            'affected_url_count' => $affectedUrlCount,
            'exact_example_count' => $exactExampleCount > 0 ? $exactExampleCount : null,
            'is_example_set_truncated' => $isExampleSetTruncated ? true : null,
            'sample_urls' => is_array($this->sample_urls) && $this->sample_urls !== [] ? $this->sample_urls : array_slice($affectedUrls, 0, 10),
            'examples' => $examples !== [] ? $examples : null,
            'first_detected' => $this->first_detected_at?->toDateString(),
            'last_update' => $this->last_updated_at?->toDateString(),
            'source_report' => $this->source_report,
            'source_capture_method' => $this->capture_method,
            'source_property' => $this->source_property,
            'captured_at' => $this->captured_at->toIso8601String(),
            'url_inspection' => is_array($normalizedPayload['url_inspection'] ?? null) ? $normalizedPayload['url_inspection'] : null,
            'sitemaps' => is_array($normalizedPayload['sitemaps'] ?? null) ? $normalizedPayload['sitemaps'] : null,
            'referring_urls' => is_array($normalizedPayload['referring_urls'] ?? null) ? $normalizedPayload['referring_urls'] : null,
            'canonical_state' => is_array($normalizedPayload['canonical_state'] ?? null) ? $normalizedPayload['canonical_state'] : null,
            'search_analytics' => is_array($normalizedPayload['search_analytics'] ?? null) ? $normalizedPayload['search_analytics'] : null,
        ], static fn (mixed $value): bool => $value !== null && $value !== []);
    }

    private function normalizeLastCrawledValue(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        if (in_array(strtolower($trimmed), ['n/a', 'na', '-', '--', 'not available'], true)) {
            return null;
        }

        if ($trimmed === '1970-01-01') {
            return null;
        }

        return $trimmed;
    }
}
