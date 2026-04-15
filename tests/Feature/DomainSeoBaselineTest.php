<?php

namespace Tests\Feature;

use App\Livewire\DomainDetail;
use App\Models\Domain;
use App\Models\DomainSeoBaseline;
use App\Models\PropertyAnalyticsSource;
use App\Models\WebProperty;
use App\Models\WebPropertyDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DomainSeoBaselineTest extends TestCase
{
    use RefreshDatabase;

    public function test_domain_detail_shows_empty_state_when_no_seo_baseline_exists(): void
    {
        $domain = Domain::factory()->create([
            'domain' => 'removalsinterstate.com.au',
        ]);

        Livewire::test(DomainDetail::class, ['domainId' => $domain->id])
            ->assertSee('SEO Baselines')
            ->assertSee('No SEO baseline snapshot has been stored for this domain yet.');
    }

    public function test_domain_detail_renders_latest_seo_baseline_summary_and_history(): void
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

        $analyticsSource = PropertyAnalyticsSource::create([
            'web_property_id' => $property->id,
            'provider' => 'matomo',
            'external_id' => '29',
            'external_name' => 'Removals Interstate',
            'is_primary' => true,
            'status' => 'active',
        ]);

        DomainSeoBaseline::create([
            'domain_id' => $domain->id,
            'web_property_id' => $property->id,
            'property_analytics_source_id' => $analyticsSource->id,
            'baseline_type' => 'manual_checkpoint',
            'captured_at' => now()->subDay(),
            'source_provider' => 'matomo',
            'matomo_site_id' => '29',
            'search_console_property_uri' => 'https://removalsinterstate.com.au/',
            'search_type' => 'web',
            'date_range_start' => '2025-12-29',
            'date_range_end' => '2026-03-28',
            'import_method' => 'matomo_api',
            'clicks' => 4,
            'impressions' => 4300,
            'ctr' => 0.00093,
            'average_position' => 55.40,
            'indexed_pages' => 26,
            'not_indexed_pages' => 182,
        ]);

        DomainSeoBaseline::create([
            'domain_id' => $domain->id,
            'web_property_id' => $property->id,
            'property_analytics_source_id' => $analyticsSource->id,
            'baseline_type' => 'pre_rebuild',
            'captured_at' => now(),
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
            'notes' => 'Initial Search Console and page indexing baseline before any rebuild work.',
        ]);

        Livewire::test(DomainDetail::class, ['domainId' => $domain->id])
            ->assertSee('SEO Baselines')
            ->assertSee('Latest Pre Rebuild')
            ->assertSee('5,370')
            ->assertSee('30')
            ->assertSee('199')
            ->assertSee('29')
            ->assertSee('Removals Interstate')
            ->assertSee('Indexed Trend')
            ->assertSee('Indexed Delta')
            ->assertSee('+4')
            ->assertSee('Not Indexed Delta')
            ->assertSee('+17')
            ->assertSee('Crawled - currently not indexed: 179')
            ->assertSee('Initial Search Console and page indexing baseline before any rebuild work.')
            ->assertSee('Recent Checkpoints')
            ->assertSee('Manual Checkpoint');
    }
}
