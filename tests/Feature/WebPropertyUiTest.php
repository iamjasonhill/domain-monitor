<?php

namespace Tests\Feature;

use App\Livewire\WebPropertyDetail;
use App\Models\AnalyticsInstallAudit;
use App\Models\DetectedIssueVerification;
use App\Models\Domain;
use App\Models\DomainCheck;
use App\Models\DomainSeoBaseline;
use App\Models\PropertyAnalyticsSource;
use App\Models\PropertyRepository;
use App\Models\SearchConsoleCoverageStatus;
use App\Models\SearchConsoleIssueSnapshot;
use App\Models\User;
use App\Models\WebProperty;
use App\Models\WebPropertyDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class WebPropertyUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_web_properties_index(): void
    {
        $user = User::factory()->create();
        $domain = Domain::factory()->create([
            'domain' => 'moveroo.com.au',
            'is_active' => true,
        ]);

        $property = WebProperty::factory()->create([
            'slug' => 'moveroo-website',
            'name' => 'Moveroo Website',
            'property_type' => 'marketing_site',
            'status' => 'active',
            'primary_domain_id' => $domain->id,
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $domain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        $response = $this->actingAs($user)->get('/web-properties');

        $response->assertOk();
        $response->assertSee('Web Properties');
        $response->assertSee('Moveroo Website');
        $response->assertSee('moveroo.com.au');
    }

    public function test_authenticated_user_can_view_web_property_detail(): void
    {
        $user = User::factory()->create();
        $primaryDomain = Domain::factory()->create([
            'domain' => 'movingagain.com.au',
            'is_active' => true,
            'dns_config_name' => 'DNS Hosting',
        ]);
        $aliasDomain = Domain::factory()->create([
            'domain' => 'movingagain.net.au',
            'is_active' => true,
            'dns_config_name' => 'Parked',
        ]);

        $property = WebProperty::factory()->create([
            'slug' => 'moving-again',
            'name' => 'Moving Again',
            'property_type' => 'marketing_site',
            'status' => 'active',
            'primary_domain_id' => $primaryDomain->id,
            'production_url' => 'https://movingagain.com.au',
            'astro_cutover_at' => now()->subDays(2),
            'canonical_origin_scheme' => 'https',
            'canonical_origin_host' => 'movingagain.com.au',
            'canonical_origin_policy' => 'known',
            'canonical_origin_enforcement_eligible' => true,
            'canonical_origin_excluded_subdomains' => ['cartransport.movingagain.com.au'],
            'canonical_origin_sitemap_policy_known' => true,
            'notes' => 'Review alias grouping before merge.',
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $primaryDomain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $aliasDomain->id,
            'usage_type' => 'alias',
            'is_canonical' => false,
        ]);

        PropertyRepository::create([
            'web_property_id' => $property->id,
            'repo_name' => 'moving-again-astro',
            'repo_provider' => 'local_only',
            'local_path' => '/Users/jasonhill/Projects/websites/moving-again-astro',
            'framework' => 'Astro',
            'is_primary' => true,
        ]);

        PropertyAnalyticsSource::create([
            'web_property_id' => $property->id,
            'provider' => 'matomo',
            'external_id' => '7',
            'external_name' => 'Car transport by Moving Again',
            'workspace_path' => '/Users/jasonhill/Projects/2026 Projects/Matamo',
            'is_primary' => true,
            'status' => 'active',
        ]);

        $source = PropertyAnalyticsSource::query()->where('web_property_id', $property->id)->firstOrFail();

        AnalyticsInstallAudit::create([
            'property_analytics_source_id' => $source->id,
            'web_property_id' => $property->id,
            'provider' => 'matomo',
            'external_id' => '7',
            'external_name' => 'Car transport by Moving Again',
            'expected_tracker_host' => 'stats.redirection.com.au',
            'install_verdict' => 'not_detected',
            'best_url' => 'https://movingagain.com.au/',
            'detected_site_ids' => [],
            'detected_tracker_hosts' => [],
            'summary' => 'No Matomo snippet detected.',
            'checked_at' => now(),
            'raw_payload' => ['verdict' => 'not_detected'],
        ]);

        DomainSeoBaseline::create([
            'domain_id' => $primaryDomain->id,
            'web_property_id' => $property->id,
            'property_analytics_source_id' => $source->id,
            'baseline_type' => 'pre_rebuild',
            'captured_at' => now()->subWeek(),
            'source_provider' => 'matomo',
            'matomo_site_id' => '7',
            'search_console_property_uri' => 'https://movingagain.com.au/',
            'search_type' => 'web',
            'import_method' => 'matomo_api',
            'clicks' => 12,
            'impressions' => 650,
            'ctr' => 0.0184,
            'average_position' => 18.2,
            'indexed_pages' => 11,
            'not_indexed_pages' => 74,
        ]);

        DomainSeoBaseline::create([
            'domain_id' => $primaryDomain->id,
            'web_property_id' => $property->id,
            'property_analytics_source_id' => $source->id,
            'baseline_type' => 'weekly_checkpoint',
            'captured_at' => now(),
            'source_provider' => 'matomo',
            'matomo_site_id' => '7',
            'search_console_property_uri' => 'https://movingagain.com.au/',
            'search_type' => 'web',
            'import_method' => 'matomo_api',
            'clicks' => 15,
            'impressions' => 710,
            'ctr' => 0.0211,
            'average_position' => 16.4,
            'indexed_pages' => 16,
            'not_indexed_pages' => 61,
        ]);

        $response = $this->actingAs($user)->get('/web-properties/moving-again');

        $response->assertOk();
        $response->assertSee('Moving Again');
        $response->assertSee('movingagain.com.au');
        $response->assertSee('movingagain.net.au');
        $response->assertSee('moving-again-astro');
        $response->assertSee('Car transport by Moving Again');
        $response->assertSee('not detected');
        $response->assertSee('No Matomo snippet detected.');
        $response->assertSee('Conversion Links');
        $response->assertSee('Target Links');
        $response->assertSee('Moveroo Subdomain');
        $response->assertSee('Contact Us Page');
        $response->assertSee('SEO Baseline Trend');
        $response->assertSee('Latest Checkpoint');
        $response->assertSee('Indexed Delta');
        $response->assertSee('+5');
        $response->assertSee('Not Indexed Delta');
        $response->assertSee('-13');
        $response->assertSee('Astro Cutover');
        $response->assertSee('Astro cutover recorded');
        $response->assertSee('Canonical Origin Policy');
        $response->assertSee('Save Canonical Policy');
        $response->assertSee('property_only');
        $response->assertSee('cartransport.movingagain.com.au');
        $response->assertSee('Add Owned Subdomain');
        $response->assertSee('Automation Checklist');
        $response->assertSee('Needs Matomo');
    }

    public function test_web_property_detail_shows_manual_csv_pending_checklist_state(): void
    {
        $user = User::factory()->create();
        $domain = Domain::factory()->create([
            'domain' => 'checklist.example.au',
            'is_active' => true,
        ]);

        $property = WebProperty::factory()->create([
            'slug' => 'checklist-site',
            'name' => 'Checklist Site',
            'property_type' => 'marketing_site',
            'status' => 'active',
            'primary_domain_id' => $domain->id,
            'production_url' => 'https://checklist.example.au',
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $domain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        PropertyRepository::create([
            'web_property_id' => $property->id,
            'repo_name' => 'checklist-site-repo',
            'repo_provider' => 'local',
            'local_path' => '/tmp/checklist-site',
            'framework' => 'Astro',
            'is_primary' => true,
            'status' => 'active',
        ]);

        $source = PropertyAnalyticsSource::create([
            'web_property_id' => $property->id,
            'provider' => 'matomo',
            'external_id' => '88',
            'external_name' => 'Checklist Site',
            'workspace_path' => '/tmp/matomo',
            'is_primary' => true,
            'status' => 'active',
        ]);

        AnalyticsInstallAudit::create([
            'property_analytics_source_id' => $source->id,
            'web_property_id' => $property->id,
            'provider' => 'matomo',
            'external_id' => '88',
            'external_name' => 'Checklist Site',
            'expected_tracker_host' => 'stats.example.au',
            'install_verdict' => 'installed_match',
            'best_url' => 'https://checklist.example.au/',
            'detected_site_ids' => ['88'],
            'detected_tracker_hosts' => ['stats.example.au'],
            'summary' => 'Tracker matches the linked Matomo site.',
            'checked_at' => now(),
            'raw_payload' => ['verdict' => 'installed_match'],
        ]);

        SearchConsoleCoverageStatus::create([
            'domain_id' => $domain->id,
            'web_property_id' => $property->id,
            'property_analytics_source_id' => $source->id,
            'source_provider' => 'matomo',
            'matomo_site_id' => '88',
            'matomo_site_name' => 'Checklist Site',
            'mapping_state' => 'domain_property',
            'property_uri' => 'sc-domain:checklist.example.au',
            'property_type' => 'domain',
            'latest_metric_date' => now()->subDay()->toDateString(),
            'checked_at' => now(),
        ]);

        DomainSeoBaseline::create([
            'domain_id' => $domain->id,
            'web_property_id' => $property->id,
            'property_analytics_source_id' => $source->id,
            'baseline_type' => 'search_console',
            'captured_at' => now(),
            'source_provider' => 'search_console',
            'matomo_site_id' => '88',
            'search_console_property_uri' => 'sc-domain:checklist.example.au',
            'search_type' => 'web',
            'import_method' => 'matomo_api',
            'clicks' => 20,
            'impressions' => 120,
            'ctr' => 0.16,
            'average_position' => 9.8,
            'pages_with_redirect' => 3,
        ]);

        SearchConsoleIssueSnapshot::factory()->create([
            'domain_id' => $domain->id,
            'web_property_id' => $property->id,
            'property_analytics_source_id' => $source->id,
            'issue_class' => 'page_with_redirect_in_sitemap',
            'source_issue_label' => 'Page with redirect',
            'capture_method' => 'gsc_drilldown_zip',
            'source_report' => 'search_console_page_indexing_drilldown',
            'source_property' => 'sc-domain:checklist.example.au',
            'captured_at' => now(),
            'affected_url_count' => 2,
            'sample_urls' => [
                'https://checklist.example.au/',
                'http://checklist.example.au/',
            ],
            'examples' => [
                ['url' => 'https://checklist.example.au/', 'last_crawled' => '2026-03-28'],
            ],
            'normalized_payload' => [
                'affected_urls' => [
                    'https://checklist.example.au/',
                    'http://checklist.example.au/',
                ],
            ],
        ]);

        SearchConsoleIssueSnapshot::factory()->create([
            'domain_id' => $domain->id,
            'web_property_id' => $property->id,
            'property_analytics_source_id' => $source->id,
            'issue_class' => 'discovered_currently_not_indexed',
            'source_issue_label' => 'Discovered - currently not indexed',
            'capture_method' => 'gsc_drilldown_zip',
            'source_report' => 'search_console_page_indexing_drilldown',
            'source_property' => 'sc-domain:checklist.example.au',
            'captured_at' => now(),
            'affected_url_count' => 1,
            'sample_urls' => [
                'https://checklist.example.au/new-page/',
            ],
            'examples' => [
                ['url' => 'https://checklist.example.au/new-page/', 'last_crawled' => '1970-01-01'],
            ],
            'normalized_payload' => [
                'affected_urls' => [
                    'https://checklist.example.au/new-page/',
                ],
            ],
            'raw_payload' => [
                'table' => [
                    ['URL' => 'https://checklist.example.au/new-page/', 'Last crawled' => 'N/A'],
                ],
            ],
        ]);

        $response = $this->actingAs($user)->get('/web-properties/checklist-site');

        $response->assertOk();
        $response->assertSee('Automation Checklist');
        $response->assertSee('Checklist Site');
        $response->assertSee('Manual CSV pending');
        $response->assertSee('Open related queue');
        $response->assertSee('Search Console Issue Evidence');
        $response->assertSee('Exact examples captured');
        $response->assertSee('https://checklist.example.au/');
        $response->assertSee('https://checklist.example.au/new-page/');
        $response->assertDontSee('1970-01-01');
    }

    public function test_web_property_detail_shows_external_link_inventory_for_linked_domains(): void
    {
        $user = User::factory()->create();
        $domain = Domain::factory()->create([
            'domain' => 'inventory-ui.example.com',
            'is_active' => true,
        ]);

        $property = WebProperty::factory()->create([
            'slug' => 'inventory-ui-site',
            'name' => 'Inventory UI Site',
            'property_type' => 'marketing_site',
            'status' => 'active',
            'primary_domain_id' => $domain->id,
            'production_url' => 'https://inventory-ui.example.com',
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $domain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        DomainCheck::withoutEvents(function () use ($domain): void {
            DomainCheck::factory()->create([
                'id' => (string) Str::uuid(),
                'domain_id' => $domain->id,
                'check_type' => 'external_links',
                'status' => 'ok',
                'started_at' => now()->subMinute(),
                'finished_at' => now(),
                'duration_ms' => 800,
                'payload' => [
                    'domain' => 'inventory-ui.example.com',
                    'pages_scanned' => 3,
                    'external_links_count' => 2,
                    'unique_hosts_count' => 2,
                    'external_links' => [
                        [
                            'url' => 'https://quotes.inventory-ui.example.com/start',
                            'host' => 'quotes.inventory-ui.example.com',
                            'relationship' => 'subdomain',
                            'found_on' => 'https://inventory-ui.example.com/',
                            'found_on_pages' => ['https://inventory-ui.example.com/'],
                        ],
                        [
                            'url' => 'https://partner.example.org/book',
                            'host' => 'partner.example.org',
                            'relationship' => 'external',
                            'found_on' => 'https://inventory-ui.example.com/contact',
                            'found_on_pages' => ['https://inventory-ui.example.com/contact'],
                        ],
                    ],
                ],
                'retry_count' => 0,
            ]);
        });

        $response = $this->actingAs($user)->get('/web-properties/inventory-ui-site');

        $response->assertOk();
        $response->assertSee('External Links');
        $response->assertSee('View all 2 links');
        $response->assertSee('quotes.inventory-ui.example.com/start');
        $response->assertSee('partner.example.org/book');
        $response->assertSee('3 pages scanned');
    }

    public function test_web_property_external_links_inventory_modal_can_be_opened_for_linked_domain(): void
    {
        $domain = Domain::factory()->create([
            'domain' => 'inventory-modal.example.com',
            'is_active' => true,
        ]);

        $property = WebProperty::factory()->create([
            'slug' => 'inventory-modal-site',
            'name' => 'Inventory Modal Site',
            'property_type' => 'marketing_site',
            'status' => 'active',
            'primary_domain_id' => $domain->id,
            'production_url' => 'https://inventory-modal.example.com',
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $domain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        DomainCheck::withoutEvents(function () use ($domain): void {
            DomainCheck::factory()->create([
                'id' => (string) Str::uuid(),
                'domain_id' => $domain->id,
                'check_type' => 'external_links',
                'status' => 'ok',
                'started_at' => now()->subMinute(),
                'finished_at' => now(),
                'duration_ms' => 500,
                'payload' => [
                    'domain' => 'inventory-modal.example.com',
                    'pages_scanned' => 2,
                    'external_links_count' => 2,
                    'unique_hosts_count' => 2,
                    'external_links' => [
                        [
                            'url' => 'https://quotes.inventory-modal.example.com/start',
                            'host' => 'quotes.inventory-modal.example.com',
                            'relationship' => 'subdomain',
                            'found_on' => 'https://inventory-modal.example.com/',
                            'found_on_pages' => ['https://inventory-modal.example.com/'],
                        ],
                        [
                            'url' => 'https://partner.example.org/book',
                            'host' => 'partner.example.org',
                            'relationship' => 'external',
                            'found_on' => 'https://inventory-modal.example.com/contact',
                            'found_on_pages' => ['https://inventory-modal.example.com/contact'],
                        ],
                    ],
                ],
                'retry_count' => 0,
            ]);
        });

        Livewire::test(WebPropertyDetail::class, ['propertySlug' => 'inventory-modal-site'])
            ->call('openExternalLinksModal', $domain->id)
            ->assertSet('showExternalLinksModal', true)
            ->assertSet('selectedExternalLinksDomainName', 'inventory-modal.example.com')
            ->assertSee('External Link Inventory')
            ->assertSee('quotes.inventory-modal.example.com/start')
            ->assertSee('partner.example.org/book')
            ->call('closeExternalLinksModal')
            ->assertSet('showExternalLinksModal', false);
    }

    public function test_web_property_detail_hides_suppressed_search_console_issue_summaries(): void
    {
        $user = User::factory()->create();
        $domain = Domain::factory()->create([
            'domain' => 'suppressed-summary.example.au',
            'is_active' => true,
        ]);

        $property = WebProperty::factory()->create([
            'slug' => 'suppressed-summary-site',
            'name' => 'Suppressed Summary Site',
            'property_type' => 'marketing_site',
            'status' => 'active',
            'primary_domain_id' => $domain->id,
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
            'issue_class' => 'page_with_redirect_in_sitemap',
            'source_issue_label' => 'Page with redirect',
            'capture_method' => 'gsc_drilldown_zip',
            'source_report' => 'search_console_page_indexing_drilldown',
            'source_property' => 'sc-domain:suppressed-summary.example.au',
            'captured_at' => now()->subDays(2),
            'captured_by' => 'test',
            'affected_url_count' => 17,
            'sample_urls' => ['https://suppressed-summary.example.au/'],
            'examples' => [
                ['url' => 'https://suppressed-summary.example.au/', 'last_crawled' => now()->subDays(3)->toDateString()],
            ],
            'normalized_payload' => [
                'affected_urls' => ['https://suppressed-summary.example.au/'],
            ],
        ]);

        SearchConsoleIssueSnapshot::factory()->create([
            'domain_id' => $domain->id,
            'web_property_id' => $property->id,
            'issue_class' => 'blocked_by_robots_in_indexing',
            'source_issue_label' => 'Blocked by robots.txt',
            'capture_method' => 'gsc_drilldown_zip',
            'source_report' => 'search_console_page_indexing_drilldown',
            'source_property' => 'sc-domain:suppressed-summary.example.au',
            'captured_at' => now(),
            'captured_by' => 'test',
            'affected_url_count' => 1,
            'sample_urls' => ['https://suppressed-summary.example.au/wp-admin/'],
            'examples' => [
                ['url' => 'https://suppressed-summary.example.au/wp-admin/', 'last_crawled' => now()->toDateString()],
            ],
            'normalized_payload' => [
                'affected_urls' => ['https://suppressed-summary.example.au/wp-admin/'],
            ],
        ]);

        $issueId = app(\App\Services\DetectedIssueIdentityService::class)
            ->makeIssueId($domain->id, $property->slug, 'page_with_redirect_in_sitemap');

        DetectedIssueVerification::create([
            'issue_id' => $issueId,
            'property_slug' => $property->slug,
            'domain' => $domain->domain,
            'issue_class' => 'page_with_redirect_in_sitemap',
            'status' => 'verified_fixed_pending_recrawl',
            'hidden_until' => now()->addDays(14),
            'verification_source' => 'fleet-control',
            'verification_notes' => ['Verified live'],
            'verified_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($user)->get('/web-properties/suppressed-summary-site');

        $response->assertOk();
        $response->assertDontSee('Page with redirect');
        $response->assertDontSee('17 affected URLs');
        $response->assertSee('Blocked by robots.txt');
        $response->assertSee('1 affected URLs');
    }

    public function test_web_property_detail_hides_suppressed_search_console_issue_summaries_when_primary_domain_id_is_missing(): void
    {
        $user = User::factory()->create();
        $domain = Domain::factory()->create([
            'domain' => 'suppressed-summary-fallback.example.au',
            'is_active' => true,
        ]);

        $property = WebProperty::factory()->create([
            'slug' => 'suppressed-summary-fallback-site',
            'name' => 'Suppressed Summary Fallback Site',
            'property_type' => 'marketing_site',
            'status' => 'active',
            'primary_domain_id' => null,
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
            'issue_class' => 'page_with_redirect_in_sitemap',
            'source_issue_label' => 'Page with redirect',
            'capture_method' => 'gsc_drilldown_zip',
            'source_report' => 'search_console_page_indexing_drilldown',
            'source_property' => 'sc-domain:suppressed-summary-fallback.example.au',
            'captured_at' => now()->subDays(2),
            'captured_by' => 'test',
            'affected_url_count' => 4,
            'sample_urls' => ['https://suppressed-summary-fallback.example.au/'],
            'examples' => [
                ['url' => 'https://suppressed-summary-fallback.example.au/', 'last_crawled' => now()->subDays(3)->toDateString()],
            ],
            'normalized_payload' => [
                'affected_urls' => ['https://suppressed-summary-fallback.example.au/'],
            ],
        ]);

        SearchConsoleIssueSnapshot::factory()->create([
            'domain_id' => $domain->id,
            'web_property_id' => $property->id,
            'issue_class' => 'blocked_by_robots_in_indexing',
            'source_issue_label' => 'Blocked by robots.txt',
            'capture_method' => 'gsc_drilldown_zip',
            'source_report' => 'search_console_page_indexing_drilldown',
            'source_property' => 'sc-domain:suppressed-summary-fallback.example.au',
            'captured_at' => now(),
            'captured_by' => 'test',
            'affected_url_count' => 1,
            'sample_urls' => ['https://suppressed-summary-fallback.example.au/wp-admin/'],
            'examples' => [
                ['url' => 'https://suppressed-summary-fallback.example.au/wp-admin/', 'last_crawled' => now()->toDateString()],
            ],
            'normalized_payload' => [
                'affected_urls' => ['https://suppressed-summary-fallback.example.au/wp-admin/'],
            ],
        ]);

        $issueId = app(\App\Services\DetectedIssueIdentityService::class)
            ->makeIssueId($domain->id, $property->slug, 'page_with_redirect_in_sitemap');

        DetectedIssueVerification::create([
            'issue_id' => $issueId,
            'property_slug' => $property->slug,
            'domain' => $domain->domain,
            'issue_class' => 'page_with_redirect_in_sitemap',
            'status' => 'verified_fixed_pending_recrawl',
            'hidden_until' => now()->addDays(14),
            'verification_source' => 'fleet-control',
            'verification_notes' => ['Verified live'],
            'verified_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($user)->get('/web-properties/suppressed-summary-fallback-site');

        $response->assertOk();
        $response->assertDontSee('Page with redirect');
        $response->assertDontSee('4 affected URLs');
        $response->assertSee('Blocked by robots.txt');
        $response->assertSee('1 affected URLs');
    }

    public function test_web_property_detail_hides_intentional_admin_login_search_console_noise(): void
    {
        $user = User::factory()->create();
        $domain = Domain::factory()->create([
            'domain' => 'intentional-admin-ui.example.au',
            'is_active' => true,
        ]);

        $property = WebProperty::factory()->create([
            'slug' => 'intentional-admin-ui-site',
            'name' => 'Intentional Admin UI Site',
            'property_type' => 'website',
            'status' => 'active',
            'primary_domain_id' => $domain->id,
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $domain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        DomainCheck::create([
            'domain_id' => $domain->id,
            'check_type' => 'seo',
            'status' => 'ok',
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
            'duration_ms' => 100,
            'payload' => [
                'results' => [
                    'robots' => [
                        'url' => 'https://intentional-admin-ui.example.au/robots.txt',
                        'has_standard_wordpress_admin_rule' => true,
                        'allow_admin_ajax' => true,
                    ],
                ],
            ],
            'retry_count' => 0,
        ]);

        SearchConsoleIssueSnapshot::factory()->create([
            'domain_id' => $domain->id,
            'web_property_id' => $property->id,
            'issue_class' => 'blocked_by_robots_in_indexing',
            'source_issue_label' => 'Blocked by robots.txt',
            'capture_method' => 'gsc_drilldown_zip',
            'source_report' => 'search_console_page_indexing_drilldown',
            'source_property' => 'sc-domain:intentional-admin-ui.example.au',
            'captured_at' => now(),
            'captured_by' => 'test',
            'affected_url_count' => 1,
            'sample_urls' => ['https://intentional-admin-ui.example.au/wp-admin/'],
            'examples' => [
                ['url' => 'https://intentional-admin-ui.example.au/wp-admin/', 'last_crawled' => now()->toDateString()],
            ],
            'normalized_payload' => [
                'affected_urls' => ['https://intentional-admin-ui.example.au/wp-admin/'],
            ],
        ]);

        SearchConsoleIssueSnapshot::factory()->create([
            'domain_id' => $domain->id,
            'web_property_id' => $property->id,
            'issue_class' => 'excluded_by_noindex',
            'source_issue_label' => "Excluded by 'noindex' tag",
            'capture_method' => 'gsc_drilldown_zip',
            'source_report' => 'search_console_page_indexing_drilldown',
            'source_property' => 'sc-domain:intentional-admin-ui.example.au',
            'captured_at' => now(),
            'captured_by' => 'test',
            'affected_url_count' => 1,
            'sample_urls' => ['https://intentional-admin-ui.example.au/wp-login.php?redirect_to=/wp-admin/'],
            'examples' => [
                ['url' => 'https://intentional-admin-ui.example.au/wp-login.php?redirect_to=/wp-admin/', 'last_crawled' => now()->toDateString()],
            ],
            'normalized_payload' => [
                'affected_urls' => ['https://intentional-admin-ui.example.au/wp-login.php?redirect_to=/wp-admin/'],
            ],
        ]);

        $response = $this->actingAs($user)->get('/web-properties/intentional-admin-ui-site');

        $response->assertOk();
        $response->assertDontSee('Blocked by robots.txt');
        $response->assertDontSee("Excluded by 'noindex' tag");
    }

    public function test_web_property_detail_shows_only_surviving_404_examples_after_live_rechecks(): void
    {
        $user = User::factory()->create();
        $domain = Domain::factory()->create([
            'domain' => 'ui-filtered-404.example.au',
            'is_active' => true,
        ]);

        $property = WebProperty::factory()->create([
            'slug' => 'ui-filtered-404-site',
            'name' => 'UI Filtered 404 Site',
            'property_type' => 'website',
            'status' => 'active',
            'primary_domain_id' => $domain->id,
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
            'source_issue_label' => 'Not found (404)',
            'capture_method' => 'gsc_drilldown_zip',
            'source_report' => 'search_console_page_indexing_drilldown',
            'source_property' => 'sc-domain:ui-filtered-404.example.au',
            'captured_at' => now()->subDay(),
            'captured_by' => 'test',
            'affected_url_count' => 2,
            'sample_urls' => [
                'https://ui-filtered-404.example.au/fixed-page/',
                'https://ui-filtered-404.example.au/still-missing/',
            ],
            'examples' => [
                ['url' => 'https://ui-filtered-404.example.au/fixed-page/', 'last_crawled' => now()->subDays(2)->toDateString()],
                ['url' => 'https://ui-filtered-404.example.au/still-missing/', 'last_crawled' => now()->subDays(2)->toDateString()],
            ],
            'normalized_payload' => [
                'affected_urls' => [
                    'https://ui-filtered-404.example.au/fixed-page/',
                    'https://ui-filtered-404.example.au/still-missing/',
                ],
            ],
        ]);

        SearchConsoleIssueSnapshot::factory()->create([
            'domain_id' => $domain->id,
            'web_property_id' => $property->id,
            'issue_class' => 'not_found_404',
            'source_issue_label' => 'Not found (404)',
            'capture_method' => 'gsc_live_recheck',
            'source_report' => 'search_console_live_http_recheck',
            'source_property' => 'sc-domain:ui-filtered-404.example.au',
            'captured_at' => now(),
            'captured_by' => 'test',
            'affected_url_count' => 2,
            'sample_urls' => [
                'https://ui-filtered-404.example.au/fixed-page/',
                'https://ui-filtered-404.example.au/still-missing/',
            ],
            'normalized_payload' => [
                'affected_urls' => [
                    'https://ui-filtered-404.example.au/fixed-page/',
                    'https://ui-filtered-404.example.au/still-missing/',
                ],
                'live_url_checks' => [
                    [
                        'url' => 'https://ui-filtered-404.example.au/fixed-page/',
                        'checked_at' => now()->toIso8601String(),
                        'final_url' => 'https://ui-filtered-404.example.au/fixed-page/',
                        'final_status' => 200,
                        'resolved_ok' => true,
                        'host_changed' => false,
                    ],
                    [
                        'url' => 'https://ui-filtered-404.example.au/still-missing/',
                        'checked_at' => now()->toIso8601String(),
                        'final_url' => 'https://ui-filtered-404.example.au/still-missing/',
                        'final_status' => 404,
                        'resolved_ok' => false,
                        'host_changed' => false,
                    ],
                ],
            ],
        ]);

        $response = $this->actingAs($user)->get('/web-properties/ui-filtered-404-site');

        $response->assertOk();
        $response->assertSee('Not found (404)');
        $response->assertSee('1 affected URLs');
        $response->assertSee('https://ui-filtered-404.example.au/still-missing/');
        $response->assertDontSee('https://ui-filtered-404.example.au/fixed-page/');
    }
}
