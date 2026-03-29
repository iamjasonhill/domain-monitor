<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\DomainSeoBaseline;
use App\Models\PropertyAnalyticsSource;
use App\Models\WebProperty;
use App\Models\WebPropertyDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ImportMatomoSearchConsoleBaselineCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_a_matomo_search_console_baseline_for_a_mapped_domain(): void
    {
        $domain = Domain::factory()->create([
            'domain' => 'removalsinterstate.com.au',
        ]);

        $property = WebProperty::factory()->create([
            'name' => 'Removals Interstate',
            'primary_domain_id' => $domain->id,
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $domain->id,
            'usage_type' => 'production',
            'is_canonical' => true,
        ]);

        $source = PropertyAnalyticsSource::create([
            'web_property_id' => $property->id,
            'provider' => 'matomo',
            'external_id' => '29',
            'external_name' => 'Removals Interstate',
            'is_primary' => true,
            'status' => 'active',
        ]);

        $path = tempnam(sys_get_temp_dir(), 'sc-baseline-');

        file_put_contents($path, json_encode([
            'source_system' => 'matamo_search_console',
            'contract_version' => 1,
            'generated_at' => '2026-03-29T03:45:00Z',
            'baselines' => [
                [
                    'domain' => 'removalsinterstate.com.au',
                    'baseline_type' => 'pre_rebuild',
                    'captured_at' => '2026-03-29T03:46:00Z',
                    'captured_by' => 'codex',
                    'source_provider' => 'matomo',
                    'matomo_site_id' => '29',
                    'search_console_property_uri' => 'https://removalsinterstate.com.au/',
                    'search_type' => 'web',
                    'date_range_start' => '2025-12-29',
                    'date_range_end' => '2026-03-28',
                    'import_method' => 'matomo_plus_manual_csv',
                    'artifact_path' => 'MM BRAIN/memory/search-console-artifacts/removalsinterstate-com-au/2026-03-29/',
                    'clicks' => 6,
                    'impressions' => 5370,
                    'ctr' => 0.001117,
                    'average_position' => 52.378585,
                    'indexed_pages' => 30,
                    'not_indexed_pages' => 199,
                    'pages_with_redirect' => 17,
                    'not_found_404' => 1,
                    'blocked_by_robots' => 1,
                    'alternate_with_canonical' => 1,
                    'crawled_currently_not_indexed' => 179,
                    'top_pages_count' => 25,
                    'top_queries_count' => 25,
                    'inspected_url_count' => 10,
                    'inspection_indexed_url_count' => 2,
                    'inspection_non_indexed_url_count' => 8,
                    'amp_urls' => 0,
                    'mobile_issue_urls' => 0,
                    'rich_result_urls' => 2,
                    'rich_result_issue_urls' => 0,
                    'notes' => 'Initial baseline before changes.',
                    'raw_payload' => [
                        'search_type_totals' => [
                            ['search_type' => 'web', 'clicks' => 6, 'impressions' => 5370],
                        ],
                    ],
                ],
            ],
        ], JSON_PRETTY_PRINT));

        $exitCode = Artisan::call('analytics:import-search-console-baseline', ['path' => $path]);

        $this->assertSame(0, $exitCode);

        $baseline = DomainSeoBaseline::query()->first();

        $this->assertNotNull($baseline);
        $this->assertSame($domain->id, $baseline->domain_id);
        $this->assertSame($property->id, $baseline->web_property_id);
        $this->assertSame($source->id, $baseline->property_analytics_source_id);
        $this->assertSame('pre_rebuild', $baseline->baseline_type);
        $this->assertSame('29', $baseline->matomo_site_id);
        $this->assertSame(30, $baseline->indexed_pages);
        $this->assertSame(199, $baseline->not_indexed_pages);
        $this->assertSame(179, $baseline->crawled_currently_not_indexed);
        $this->assertSame(2, $baseline->rich_result_urls);
        $this->assertSame('Initial baseline before changes.', $baseline->notes);

        @unlink($path);
    }
}
