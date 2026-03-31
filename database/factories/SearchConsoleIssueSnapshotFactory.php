<?php

namespace Database\Factories;

use App\Models\Domain;
use App\Models\SearchConsoleIssueSnapshot;
use App\Models\WebProperty;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SearchConsoleIssueSnapshot>
 */
class SearchConsoleIssueSnapshotFactory extends Factory
{
    protected $model = SearchConsoleIssueSnapshot::class;

    public function definition(): array
    {
        $domainName = $this->faker->domainName();
        $exampleUrl = 'https://'.$domainName.'/';

        return [
            'domain_id' => Domain::factory(),
            'web_property_id' => WebProperty::factory(),
            'property_analytics_source_id' => null,
            'issue_class' => 'page_with_redirect_in_sitemap',
            'source_issue_label' => 'Page with redirect',
            'capture_method' => 'gsc_drilldown_zip',
            'source_report' => 'search_console_page_indexing_drilldown',
            'source_property' => 'sc-domain:'.$domainName,
            'artifact_path' => 'search-console-issue-evidence/example/example.zip',
            'captured_at' => now(),
            'captured_by' => 'factory',
            'first_detected_at' => now()->subDays(7)->toDateString(),
            'last_updated_at' => now()->toDateString(),
            'property_scope' => 'All known pages',
            'affected_url_count' => 1,
            'sample_urls' => [$exampleUrl],
            'examples' => [[
                'url' => $exampleUrl,
                'last_crawled' => now()->subDay()->toDateString(),
            ]],
            'chart_points' => [[
                'date' => now()->toDateString(),
                'affected_pages' => 1,
            ]],
            'normalized_payload' => [
                'affected_urls' => [$exampleUrl],
            ],
            'raw_payload' => [
                'metadata' => [
                    ['Property' => 'Issue', 'Value' => 'Page with redirect'],
                ],
            ],
        ];
    }
}
