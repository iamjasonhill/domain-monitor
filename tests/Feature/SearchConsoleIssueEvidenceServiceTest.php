<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\SearchConsoleIssueSnapshot;
use App\Models\WebProperty;
use App\Models\WebPropertyDomain;
use App\Services\SearchConsoleIssueEvidenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SearchConsoleIssueEvidenceServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_uses_the_latest_snapshot_from_each_bucket_per_issue(): void
    {
        $domain = Domain::factory()->create([
            'domain' => 'evidence-example.com',
            'is_active' => true,
        ]);

        $property = WebProperty::factory()->create([
            'slug' => 'evidence-example',
            'name' => 'Evidence Example',
            'status' => 'active',
            'primary_domain_id' => $domain->id,
            'canonical_origin_scheme' => 'https',
            'canonical_origin_host' => 'evidence-example.com',
            'canonical_origin_policy' => 'known',
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $domain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        SearchConsoleIssueSnapshot::factory()->create([
            'domain_id' => $domain->id,
            'web_property_id' => $property->id,
            'issue_class' => 'not_found_404',
            'capture_method' => 'gsc_drilldown_zip',
            'captured_at' => Carbon::parse('2026-04-08 08:00:00', 'UTC'),
            'sample_urls' => ['https://evidence-example.com/old-missing'],
            'examples' => [['url' => 'https://evidence-example.com/old-missing', 'last_crawled' => '2026-04-07']],
            'normalized_payload' => [
                'affected_urls' => ['https://evidence-example.com/old-missing'],
            ],
        ]);

        SearchConsoleIssueSnapshot::factory()->create([
            'domain_id' => $domain->id,
            'web_property_id' => $property->id,
            'issue_class' => 'not_found_404',
            'capture_method' => 'gsc_drilldown_zip',
            'captured_at' => Carbon::parse('2026-04-08 09:00:00', 'UTC'),
            'sample_urls' => ['https://evidence-example.com/new-missing'],
            'examples' => [['url' => 'https://evidence-example.com/new-missing', 'last_crawled' => '2026-04-08']],
            'normalized_payload' => [
                'affected_urls' => ['https://evidence-example.com/new-missing'],
            ],
        ]);

        SearchConsoleIssueSnapshot::factory()->create([
            'domain_id' => $domain->id,
            'web_property_id' => $property->id,
            'issue_class' => 'not_found_404',
            'capture_method' => 'gsc_api',
            'source_report' => 'search_console_api_bundle_old',
            'captured_at' => Carbon::parse('2026-04-08 08:10:00', 'UTC'),
            'normalized_payload' => [
                'url_inspection' => [
                    'inspected_urls' => [
                        ['url' => 'https://evidence-example.com/old-missing', 'coverage_state' => 'Not found (404)'],
                    ],
                ],
            ],
        ]);

        SearchConsoleIssueSnapshot::factory()->create([
            'domain_id' => $domain->id,
            'web_property_id' => $property->id,
            'issue_class' => 'not_found_404',
            'capture_method' => 'gsc_mcp_api',
            'source_report' => 'search_console_api_bundle_new',
            'captured_at' => Carbon::parse('2026-04-08 09:10:00', 'UTC'),
            'normalized_payload' => [
                'url_inspection' => [
                    'inspected_urls' => [
                        ['url' => 'https://evidence-example.com/new-missing', 'coverage_state' => 'Not found (404)'],
                    ],
                ],
            ],
        ]);

        SearchConsoleIssueSnapshot::factory()->create([
            'domain_id' => $domain->id,
            'web_property_id' => $property->id,
            'issue_class' => 'not_found_404',
            'capture_method' => 'gsc_live_recheck',
            'source_report' => 'search_console_live_http_recheck',
            'captured_at' => Carbon::parse('2026-04-08 08:20:00', 'UTC'),
            'normalized_payload' => [
                'affected_urls' => ['https://evidence-example.com/old-missing'],
                'live_url_checks' => [[
                    'url' => 'https://evidence-example.com/old-missing',
                    'checked_at' => '2026-04-08T08:20:00+00:00',
                    'final_url' => 'https://evidence-example.com/old-missing',
                    'final_status' => 404,
                    'resolved_ok' => false,
                    'host_changed' => false,
                ]],
            ],
        ]);

        SearchConsoleIssueSnapshot::factory()->create([
            'domain_id' => $domain->id,
            'web_property_id' => $property->id,
            'issue_class' => 'not_found_404',
            'capture_method' => 'gsc_live_recheck',
            'source_report' => 'search_console_live_http_recheck',
            'captured_at' => Carbon::parse('2026-04-08 09:20:00', 'UTC'),
            'normalized_payload' => [
                'affected_urls' => ['https://evidence-example.com/new-missing'],
                'live_url_checks' => [[
                    'url' => 'https://evidence-example.com/new-missing',
                    'checked_at' => '2026-04-08T09:20:00+00:00',
                    'final_url' => 'https://evidence-example.com/new-missing',
                    'final_status' => 404,
                    'resolved_ok' => false,
                    'host_changed' => false,
                ]],
            ],
        ]);

        $evidence = app(SearchConsoleIssueEvidenceService::class)
            ->evidenceMapForProperties(collect([$property->fresh(['primaryDomain', 'propertyDomains.domain'])]));

        $issueEvidence = $evidence['evidence-example']['not_found_404'];

        $this->assertSame(['https://evidence-example.com/new-missing'], $issueEvidence['affected_urls']);
        $this->assertSame('gsc_drilldown_zip', $issueEvidence['source_capture_method']);
        $this->assertSame('search_console_page_indexing_drilldown', $issueEvidence['source_report']);
        $this->assertSame('gsc_mcp_api', $issueEvidence['api_source_capture_method']);
        $this->assertSame('search_console_api_bundle_new', $issueEvidence['api_source_report']);
        $this->assertSame('https://evidence-example.com/new-missing', $issueEvidence['live_url_checks'][0]['url']);
        $this->assertNotNull($issueEvidence['live_captured_at']);
    }

    public function test_it_breaks_same_timestamp_ties_with_newer_updated_at_within_a_bucket(): void
    {
        $domain = Domain::factory()->create([
            'domain' => 'tie-break-example.com',
            'is_active' => true,
        ]);

        $property = WebProperty::factory()->create([
            'slug' => 'tie-break-example',
            'name' => 'Tie Break Example',
            'status' => 'active',
            'primary_domain_id' => $domain->id,
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $domain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        $capturedAt = Carbon::parse('2026-04-08 10:00:00', 'UTC');

        SearchConsoleIssueSnapshot::factory()->create([
            'domain_id' => $domain->id,
            'web_property_id' => $property->id,
            'issue_class' => 'page_with_redirect_in_sitemap',
            'capture_method' => 'gsc_api',
            'source_report' => 'search_console_api_bundle_first',
            'captured_at' => $capturedAt,
            'created_at' => Carbon::parse('2026-04-08 10:00:01', 'UTC'),
            'updated_at' => Carbon::parse('2026-04-08 10:00:01', 'UTC'),
            'normalized_payload' => [
                'sitemaps' => [
                    ['path' => 'https://tie-break-example.com/old-sitemap.xml', 'warnings' => 0, 'errors' => 0],
                ],
            ],
        ]);

        SearchConsoleIssueSnapshot::factory()->create([
            'domain_id' => $domain->id,
            'web_property_id' => $property->id,
            'issue_class' => 'page_with_redirect_in_sitemap',
            'capture_method' => 'gsc_mcp_api',
            'source_report' => 'search_console_api_bundle_second',
            'captured_at' => $capturedAt,
            'created_at' => Carbon::parse('2026-04-08 10:00:01', 'UTC'),
            'updated_at' => Carbon::parse('2026-04-08 10:00:02', 'UTC'),
            'normalized_payload' => [
                'sitemaps' => [
                    ['path' => 'https://tie-break-example.com/new-sitemap.xml', 'warnings' => 0, 'errors' => 0],
                ],
            ],
        ]);

        $evidence = app(SearchConsoleIssueEvidenceService::class)
            ->evidenceMapForProperties(collect([$property->fresh(['primaryDomain', 'propertyDomains.domain'])]));

        $issueEvidence = $evidence['tie-break-example']['page_with_redirect_in_sitemap'];

        $this->assertSame('gsc_mcp_api', $issueEvidence['source_capture_method']);
        $this->assertSame('search_console_api_bundle_second', $issueEvidence['source_report']);
        $this->assertSame('https://tie-break-example.com/new-sitemap.xml', $issueEvidence['sitemaps'][0]['path']);
    }
}
