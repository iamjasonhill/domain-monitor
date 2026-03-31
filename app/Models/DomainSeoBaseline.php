<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * @property string $id
 * @property string $domain_id
 * @property string|null $web_property_id
 * @property string|null $property_analytics_source_id
 * @property string $baseline_type
 * @property \Illuminate\Support\Carbon $captured_at
 * @property string|null $captured_by
 * @property string $source_provider
 * @property string|null $matomo_site_id
 * @property string|null $search_console_property_uri
 * @property string $search_type
 * @property \Illuminate\Support\Carbon|null $date_range_start
 * @property \Illuminate\Support\Carbon|null $date_range_end
 * @property string $import_method
 * @property string|null $artifact_path
 * @property float $clicks
 * @property float $impressions
 * @property float $ctr
 * @property float $average_position
 * @property int|null $indexed_pages
 * @property int|null $not_indexed_pages
 * @property int|null $pages_with_redirect
 * @property int|null $not_found_404
 * @property int|null $blocked_by_robots
 * @property int|null $alternate_with_canonical
 * @property int|null $crawled_currently_not_indexed
 * @property int|null $discovered_currently_not_indexed
 * @property int|null $duplicate_without_user_selected_canonical
 * @property int|null $top_pages_count
 * @property int|null $top_queries_count
 * @property int|null $inspected_url_count
 * @property int|null $inspection_indexed_url_count
 * @property int|null $inspection_non_indexed_url_count
 * @property int|null $amp_urls
 * @property int|null $mobile_issue_urls
 * @property int|null $rich_result_urls
 * @property int|null $rich_result_issue_urls
 * @property string|null $notes
 * @property array<string, mixed>|null $raw_payload
 */
class DomainSeoBaseline extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'domain_id',
        'web_property_id',
        'property_analytics_source_id',
        'baseline_type',
        'captured_at',
        'captured_by',
        'source_provider',
        'matomo_site_id',
        'search_console_property_uri',
        'search_type',
        'date_range_start',
        'date_range_end',
        'import_method',
        'artifact_path',
        'clicks',
        'impressions',
        'ctr',
        'average_position',
        'indexed_pages',
        'not_indexed_pages',
        'pages_with_redirect',
        'not_found_404',
        'blocked_by_robots',
        'alternate_with_canonical',
        'crawled_currently_not_indexed',
        'discovered_currently_not_indexed',
        'duplicate_without_user_selected_canonical',
        'top_pages_count',
        'top_queries_count',
        'inspected_url_count',
        'inspection_indexed_url_count',
        'inspection_non_indexed_url_count',
        'amp_urls',
        'mobile_issue_urls',
        'rich_result_urls',
        'rich_result_issue_urls',
        'notes',
        'raw_payload',
    ];

    protected function casts(): array
    {
        return [
            'captured_at' => 'datetime',
            'date_range_start' => 'date',
            'date_range_end' => 'date',
            'clicks' => 'float',
            'impressions' => 'float',
            'ctr' => 'float',
            'average_position' => 'float',
            'indexed_pages' => 'integer',
            'not_indexed_pages' => 'integer',
            'pages_with_redirect' => 'integer',
            'not_found_404' => 'integer',
            'blocked_by_robots' => 'integer',
            'alternate_with_canonical' => 'integer',
            'crawled_currently_not_indexed' => 'integer',
            'discovered_currently_not_indexed' => 'integer',
            'duplicate_without_user_selected_canonical' => 'integer',
            'top_pages_count' => 'integer',
            'top_queries_count' => 'integer',
            'inspected_url_count' => 'integer',
            'inspection_indexed_url_count' => 'integer',
            'inspection_non_indexed_url_count' => 'integer',
            'amp_urls' => 'integer',
            'mobile_issue_urls' => 'integer',
            'rich_result_urls' => 'integer',
            'rich_result_issue_urls' => 'integer',
            'raw_payload' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $baseline) {
            if (empty($baseline->id)) {
                $baseline->id = Str::uuid()->toString();
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

    public function baselineTypeLabel(): string
    {
        return str($this->baseline_type)->replace('_', ' ')->title()->toString();
    }

    public function importMethodLabel(): string
    {
        return str($this->import_method)->replace('_', ' ')->title()->toString();
    }

    /**
     * @return array<int, array{label:string, value:int}>
     */
    public function nonZeroIndexationIssues(): array
    {
        return collect([
            ['label' => 'Crawled - currently not indexed', 'value' => $this->crawled_currently_not_indexed],
            ['label' => 'Discovered - currently not indexed', 'value' => $this->discovered_currently_not_indexed],
            ['label' => 'Pages with redirect', 'value' => $this->pages_with_redirect],
            ['label' => '404 Not found', 'value' => $this->not_found_404],
            ['label' => 'Blocked by robots', 'value' => $this->blocked_by_robots],
            ['label' => 'Alternate with canonical', 'value' => $this->alternate_with_canonical],
            ['label' => 'Duplicate without user-selected canonical', 'value' => $this->duplicate_without_user_selected_canonical],
        ])->filter(fn (array $issue): bool => (int) ($issue['value'] ?? 0) > 0)
            ->values()
            ->all();
    }

    public function issueCount(string $issueClass): ?int
    {
        $countField = config('domain_monitor.search_console_issue_catalog.'.$issueClass.'.count_field');

        if (! is_string($countField) || $countField === '') {
            return null;
        }

        $count = $this->getAttribute($countField);

        return is_numeric($count) ? (int) $count : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function issueEvidence(string $issueClass): array
    {
        $catalogEntry = config('domain_monitor.search_console_issue_catalog.'.$issueClass);

        if (! is_array($catalogEntry)) {
            return [];
        }

        $affectedUrls = $this->extractAffectedUrlsForLabels(array_values(array_filter($catalogEntry['labels'] ?? [], 'is_string')));
        $count = $this->issueCount($issueClass);

        return array_filter([
            'affected_urls' => $affectedUrls !== [] ? $affectedUrls : null,
            'affected_url_count' => $affectedUrls !== [] ? count($affectedUrls) : $count,
            'exact_example_count' => $affectedUrls !== [] ? count($affectedUrls) : null,
            'is_example_set_truncated' => null,
            'sample_urls' => $affectedUrls !== [] ? array_slice($affectedUrls, 0, 10) : null,
            'source_report' => 'search_console_page_indexing_summary',
            'source_capture_method' => $this->import_method,
            'source_property' => $this->search_console_property_uri,
            'captured_at' => $this->captured_at->toIso8601String(),
        ], static fn (mixed $value): bool => $value !== null && $value !== []);
    }

    /**
     * @param  array<int, string>  $labels
     * @return array<int, string>
     */
    private function extractAffectedUrlsForLabels(array $labels): array
    {
        if ($labels === []) {
            return [];
        }

        $matches = collect();

        foreach ($this->candidateIssuePayloads() as $payload) {
            $this->collectUrlsFromPayload($payload, $labels, $matches);
        }

        return $matches
            ->map(fn (string $url): string => trim($url))
            ->filter(fn (string $url): bool => str_starts_with($url, 'http://') || str_starts_with($url, 'https://'))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function candidateIssuePayloads(): array
    {
        $payloads = [];

        foreach ([
            $this->raw_payload,
            data_get($this->raw_payload, 'parsed_export'),
            data_get($this->raw_payload, 'issue_details'),
            data_get($this->raw_payload, 'page_indexing_details'),
        ] as $payload) {
            if (is_array($payload)) {
                $payloads[] = $payload;
            }
        }

        return $payloads;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, string>  $labels
     * @param  Collection<int, string>  $matches
     */
    private function collectUrlsFromPayload(array $payload, array $labels, Collection $matches): void
    {
        $issueLabel = data_get($payload, 'label');
        $url = data_get($payload, 'url');

        if (is_string($issueLabel) && in_array($issueLabel, $labels, true) && is_string($url)) {
            $matches->push($url);
        }

        foreach ($payload as $value) {
            if (is_array($value)) {
                if ($this->isListArray($value)) {
                    foreach ($value as $entry) {
                        if (is_array($entry)) {
                            $this->collectUrlsFromPayload($entry, $labels, $matches);
                        }
                    }

                    continue;
                }

                $this->collectUrlsFromPayload($value, $labels, $matches);
            }
        }
    }

    /**
     * @param  array<mixed>  $value
     */
    private function isListArray(array $value): bool
    {
        return array_is_list($value);
    }
}
