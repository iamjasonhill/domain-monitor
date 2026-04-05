<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\DomainCheck;
use App\Models\DomainSeoBaseline;
use App\Models\PropertyRepository;
use App\Models\SearchConsoleIssueSnapshot;
use App\Models\WebProperty;
use App\Models\WebPropertyDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DetectedIssueApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_issues_endpoint_returns_normalized_open_issue_feed(): void
    {
        config()->set('services.domain_monitor.brain_api_key', 'test-api-key');

        $redirectDomain = Domain::factory()->create([
            'domain' => 'redirect-issue.example.com',
            'expires_at' => null,
            'is_active' => true,
            'platform' => 'WordPress',
            'hosting_provider' => 'DreamIT Host',
        ]);

        $redirectProperty = WebProperty::factory()->create([
            'slug' => 'redirect-issue-site',
            'name' => 'Redirect Issue Site',
            'property_type' => 'website',
            'status' => 'active',
            'primary_domain_id' => $redirectDomain->id,
            'target_household_quote_url' => 'https://quote.redirect-issue.example.com/household',
            'target_moveroo_subdomain_url' => 'https://redirect-issue.moveroo.com.au',
            'target_contact_us_page_url' => 'https://redirect-issue.example.com/contact-us',
            'target_legacy_bookings_replacement_url' => 'https://removalist.net/booking/create',
            'target_legacy_payments_replacement_url' => 'https://redirect-issue.moveroo.com.au/contact',
            'legacy_moveroo_endpoint_scan' => [
                'legacy_booking_endpoint' => [
                    'classification' => 'legacy_booking_endpoint',
                    'found_on' => 'https://redirect-issue.example.com',
                    'url' => 'https://redirect-issue.moveroo.com.au/bookings',
                    'resolved_url' => 'https://removalist.net/booking/create',
                    'resolved_status' => 200,
                    'resolved_host_changed' => true,
                ],
                'legacy_payment_endpoint' => [
                    'classification' => 'legacy_payment_endpoint',
                    'found_on' => 'https://redirect-issue.example.com',
                    'url' => 'https://redirect-issue.moveroo.com.au/payments',
                    'resolved_url' => 'https://redirect-issue.moveroo.com.au/contact',
                    'resolved_status' => 200,
                    'resolved_host_changed' => false,
                ],
            ],
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $redirectProperty->id,
            'domain_id' => $redirectDomain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        PropertyRepository::create([
            'web_property_id' => $redirectProperty->id,
            'repo_name' => '_wp-house',
            'repo_provider' => 'local_only',
            'local_path' => '/Users/jasonhill/Projects/websites/_wp-house',
            'framework' => 'WordPress',
            'is_primary' => true,
        ]);

        DomainSeoBaseline::create([
            'domain_id' => $redirectDomain->id,
            'web_property_id' => $redirectProperty->id,
            'baseline_type' => 'search_console',
            'captured_at' => now(),
            'captured_by' => 'test',
            'source_provider' => 'matomo',
            'matomo_site_id' => '30',
            'search_console_property_uri' => 'sc-domain:redirect-issue.example.com',
            'search_type' => 'web',
            'date_range_start' => now()->subDays(28)->toDateString(),
            'date_range_end' => now()->toDateString(),
            'import_method' => 'matomo_api',
            'clicks' => 10,
            'impressions' => 100,
            'ctr' => 0.1,
            'average_position' => 12.3,
            'indexed_pages' => 20,
            'not_indexed_pages' => 5,
            'pages_with_redirect' => 7,
            'blocked_by_robots' => 2,
            'raw_payload' => ['issues' => [['label' => 'Page with redirect', 'count' => 7]]],
        ]);

        SearchConsoleIssueSnapshot::factory()->create([
            'domain_id' => $redirectDomain->id,
            'web_property_id' => $redirectProperty->id,
            'issue_class' => 'page_with_redirect_in_sitemap',
            'source_issue_label' => 'Page with redirect',
            'capture_method' => 'gsc_drilldown_zip',
            'source_report' => 'search_console_page_indexing_drilldown',
            'source_property' => 'sc-domain:redirect-issue.example.com',
            'captured_at' => now(),
            'captured_by' => 'test',
            'affected_url_count' => 7,
            'sample_urls' => [
                'https://redirect-issue.example.com/',
                'http://redirect-issue.example.com/',
            ],
            'examples' => [
                ['url' => 'https://redirect-issue.example.com/', 'last_crawled' => '2026-03-28'],
                ['url' => 'http://redirect-issue.example.com/', 'last_crawled' => '2026-03-27'],
            ],
            'normalized_payload' => [
                'affected_urls' => [
                    'https://redirect-issue.example.com/',
                    'http://redirect-issue.example.com/',
                ],
            ],
            'raw_payload' => ['source' => 'drilldown'],
        ]);

        SearchConsoleIssueSnapshot::factory()->create([
            'domain_id' => $redirectDomain->id,
            'web_property_id' => $redirectProperty->id,
            'issue_class' => 'page_with_redirect_in_sitemap',
            'source_issue_label' => 'Page with redirect',
            'capture_method' => 'gsc_api',
            'source_report' => 'search_console_api_bundle',
            'source_property' => 'sc-domain:redirect-issue.example.com',
            'captured_at' => now()->addMinute(),
            'captured_by' => 'test',
            'normalized_payload' => [
                'url_inspection' => [
                    'inspected_urls' => [
                        [
                            'url' => 'https://redirect-issue.example.com/',
                            'coverage_state' => 'Page with redirect',
                            'page_fetch_state' => 'SUCCESSFUL',
                        ],
                    ],
                ],
                'sitemaps' => [
                    ['path' => 'https://redirect-issue.example.com/sitemap_index.xml', 'warnings' => 0, 'errors' => 0],
                ],
                'search_analytics' => [
                    'date_range' => ['start' => '2026-03-01', 'end' => '2026-03-28'],
                    'totals' => ['clicks' => 10, 'impressions' => 100],
                ],
            ],
            'raw_payload' => ['source' => 'api'],
        ]);

        SearchConsoleIssueSnapshot::factory()->create([
            'domain_id' => $redirectDomain->id,
            'web_property_id' => $redirectProperty->id,
            'issue_class' => 'blocked_by_robots_in_indexing',
            'source_issue_label' => 'Blocked by robots.txt',
            'capture_method' => 'gsc_api',
            'source_report' => 'search_console_api',
            'source_property' => 'sc-domain:redirect-issue.example.com',
            'captured_at' => now(),
            'captured_by' => 'test',
            'normalized_payload' => [
                'url_inspection' => [
                    'coverageState' => 'Blocked by robots.txt',
                    'robotsTxtState' => 'BLOCKED',
                    'indexingState' => 'BLOCKED_BY_ROBOTS',
                    'lastCrawlTime' => '2026-03-28T00:00:00Z',
                ],
                'sitemaps' => [
                    ['path' => 'https://redirect-issue.example.com/sitemap_index.xml', 'warnings' => 0, 'errors' => 0],
                ],
                'referring_urls' => ['https://redirect-issue.example.com/sitemap_index.xml'],
                'canonical_state' => [
                    'google_canonical' => 'https://redirect-issue.example.com/blocked-page/',
                    'user_canonical' => 'https://redirect-issue.example.com/blocked-page/',
                ],
            ],
            'raw_payload' => ['source' => 'api'],
        ]);

        SearchConsoleIssueSnapshot::factory()->create([
            'domain_id' => $redirectDomain->id,
            'web_property_id' => $redirectProperty->id,
            'issue_class' => 'excluded_by_noindex',
            'source_issue_label' => "Excluded by 'noindex' tag",
            'capture_method' => 'gsc_drilldown_zip',
            'source_report' => 'search_console_page_indexing_drilldown',
            'source_property' => 'sc-domain:redirect-issue.example.com',
            'captured_at' => now(),
            'captured_by' => 'test',
            'affected_url_count' => 1,
            'sample_urls' => [
                'https://redirect-issue.example.com/noindex-page/',
            ],
            'examples' => [
                ['url' => 'https://redirect-issue.example.com/noindex-page/', 'last_crawled' => '2026-03-26'],
            ],
            'normalized_payload' => [
                'affected_urls' => [
                    'https://redirect-issue.example.com/noindex-page/',
                ],
            ],
            'raw_payload' => ['source' => 'drilldown'],
        ]);

        SearchConsoleIssueSnapshot::factory()->create([
            'domain_id' => $redirectDomain->id,
            'web_property_id' => $redirectProperty->id,
            'issue_class' => 'discovered_currently_not_indexed',
            'source_issue_label' => 'Discovered - currently not indexed',
            'capture_method' => 'gsc_drilldown_zip',
            'source_report' => 'search_console_page_indexing_drilldown',
            'source_property' => 'sc-domain:redirect-issue.example.com',
            'captured_at' => now(),
            'captured_by' => 'test',
            'affected_url_count' => 1,
            'sample_urls' => [
                'https://redirect-issue.example.com/new-page/',
            ],
            'examples' => [
                ['url' => 'https://redirect-issue.example.com/new-page/', 'last_crawled' => '1970-01-01'],
            ],
            'normalized_payload' => [
                'affected_urls' => [
                    'https://redirect-issue.example.com/new-page/',
                ],
            ],
            'raw_payload' => [
                'table' => [
                    ['URL' => 'https://redirect-issue.example.com/new-page/', 'Last crawled' => 'N/A'],
                ],
            ],
        ]);

        $headersDomain = Domain::factory()->create([
            'domain' => 'headers-issue.example.com',
            'expires_at' => null,
            'is_active' => true,
            'platform' => 'Astro',
            'hosting_provider' => 'Vercel',
        ]);

        $headersProperty = WebProperty::factory()->create([
            'slug' => 'headers-issue-site',
            'name' => 'Headers Issue Site',
            'property_type' => 'marketing_site',
            'status' => 'active',
            'primary_domain_id' => $headersDomain->id,
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $headersProperty->id,
            'domain_id' => $headersDomain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        PropertyRepository::create([
            'web_property_id' => $headersProperty->id,
            'repo_name' => 'moveroo/headers-issue-site',
            'repo_provider' => 'github',
            'repo_url' => 'https://github.com/moveroo/headers-issue-site',
            'local_path' => '/Users/jasonhill/Projects/websites/headers-issue-site',
            'framework' => 'Astro',
            'is_primary' => true,
            'is_controller' => true,
            'deployment_provider' => 'vercel',
            'deployment_project_name' => 'headers-issue-site',
            'deployment_project_id' => 'prj_headers123',
        ]);

        DomainCheck::factory()->create([
            'domain_id' => $headersDomain->id,
            'check_type' => 'security_headers',
            'status' => 'warn',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer test-api-key',
        ])->getJson('/api/issues');

        $response
            ->assertOk()
            ->assertJsonPath('source_system', 'domain-monitor-issues')
            ->assertJsonPath('contract_version', 1)
            ->assertJsonPath('stats.issue_class_counts.page_with_redirect_in_sitemap', 1)
            ->assertJsonPath('stats.issue_class_counts.blocked_by_robots_in_indexing', 1)
            ->assertJsonPath('stats.issue_class_counts.excluded_by_noindex', 1)
            ->assertJsonPath('stats.issue_class_counts.discovered_currently_not_indexed', 1);

        $issueClassCounts = $response->json('stats.issue_class_counts');
        $this->assertSame(1, $issueClassCounts['page_with_redirect_in_sitemap'] ?? null);
        $this->assertSame(1, $issueClassCounts['blocked_by_robots_in_indexing'] ?? null);
        $this->assertSame(1, $issueClassCounts['excluded_by_noindex'] ?? null);
        $this->assertSame(1, $issueClassCounts['discovered_currently_not_indexed'] ?? null);
        $this->assertSame(1, $issueClassCounts['security.headers_baseline'] ?? null);

        /** @var array<int, array<string, mixed>> $payloadIssues */
        $payloadIssues = $response->json('issues');
        $issues = collect($payloadIssues);

        $redirectIssue = $issues->firstWhere('property_slug', 'redirect-issue-site');
        $blockedIssue = $issues->firstWhere('issue_class', 'blocked_by_robots_in_indexing');
        $noindexIssue = $issues->firstWhere('issue_class', 'excluded_by_noindex');
        $discoveredIssue = $issues->firstWhere('issue_class', 'discovered_currently_not_indexed');
        $headersIssue = $issues->firstWhere('property_slug', 'headers-issue-site');

        $this->assertNotNull($redirectIssue);
        $this->assertNotNull($blockedIssue);
        $this->assertNotNull($noindexIssue);
        $this->assertNotNull($discoveredIssue);
        $this->assertNotNull($headersIssue);
        $this->assertSame('page_with_redirect_in_sitemap', $redirectIssue['issue_class']);
        $this->assertSame('must_fix', $redirectIssue['severity']);
        $this->assertSame('seo.robots_and_sitemap_consistency', $redirectIssue['control_id']);
        $this->assertSame('domain_only', $redirectIssue['rollout_scope']);
        $this->assertSame('domain_monitor.priority_queue', $redirectIssue['detector']);
        $this->assertSame('controlled', $redirectIssue['control_state']);
        $this->assertSame('fleet_wordpress_controlled', $redirectIssue['execution_surface']);
        $this->assertTrue($redirectIssue['fleet_managed']);
        $this->assertSame('_wp-house', $redirectIssue['controller_repo']);
        $this->assertNull($redirectIssue['controller_repo_url']);
        $this->assertSame('https://quote.redirect-issue.example.com/household', data_get($redirectIssue, 'conversion_links.target.household_quote'));
        $this->assertSame('https://redirect-issue.moveroo.com.au', data_get($redirectIssue, 'conversion_links.target.moveroo_subdomain'));
        $this->assertSame('https://redirect-issue.example.com/contact-us', data_get($redirectIssue, 'conversion_links.target.contact_us_page'));
        $this->assertSame('https://removalist.net/booking/create', data_get($redirectIssue, 'conversion_links.target.legacy_bookings_replacement'));
        $this->assertSame('https://redirect-issue.moveroo.com.au/contact', data_get($redirectIssue, 'conversion_links.target.legacy_payments_replacement'));
        $this->assertSame('https://redirect-issue.moveroo.com.au/bookings', data_get($redirectIssue, 'conversion_links.legacy_endpoints.legacy_booking_endpoint.url'));
        $this->assertSame('https://removalist.net/booking/create', data_get($redirectIssue, 'conversion_links.legacy_endpoints.legacy_booking_endpoint.resolved_url'));
        $this->assertTrue((bool) data_get($redirectIssue, 'conversion_links.legacy_endpoints.legacy_booking_endpoint.resolved_host_changed'));
        $this->assertSame(['Search Console reports page with redirect (7 URLs)'], $redirectIssue['evidence']['primary_reasons']);
        $this->assertSame(
            ['https://redirect-issue.example.com/', 'http://redirect-issue.example.com/'],
            $redirectIssue['evidence']['affected_urls']
        );
        $this->assertSame(7, $redirectIssue['evidence']['affected_url_count']);
        $this->assertSame(2, $redirectIssue['evidence']['exact_example_count']);
        $this->assertTrue($redirectIssue['evidence']['is_example_set_truncated']);
        $this->assertSame('search_console_page_indexing_drilldown', $redirectIssue['evidence']['source_report']);
        $this->assertSame('gsc_drilldown_zip', $redirectIssue['evidence']['source_capture_method']);
        $this->assertSame('gsc_api', $redirectIssue['evidence']['api_source_capture_method']);
        $this->assertSame('search_console_api_bundle', $redirectIssue['evidence']['api_source_report']);
        $this->assertSame('2026-03-28', $redirectIssue['evidence']['examples'][0]['last_crawled']);
        $this->assertSame('Page with redirect', data_get($redirectIssue, 'evidence.url_inspection.inspected_urls.0.coverage_state'));
        $this->assertSame(10, data_get($redirectIssue, 'evidence.search_analytics.totals.clicks'));
        $this->assertSame('must_fix', $blockedIssue['severity']);
        $this->assertSame('seo.robots_and_sitemap_consistency', $blockedIssue['control_id']);
        $this->assertSame('BLOCKED', data_get($blockedIssue, 'evidence.url_inspection.robotsTxtState'));
        $this->assertSame(
            ['https://redirect-issue.example.com/sitemap_index.xml'],
            data_get($blockedIssue, 'evidence.referring_urls')
        );
        $this->assertSame(
            'https://redirect-issue.example.com/blocked-page/',
            data_get($blockedIssue, 'evidence.canonical_state.google_canonical')
        );
        $this->assertSame('should_fix', $noindexIssue['severity']);
        $this->assertSame('seo.indexation_coverage', $noindexIssue['control_id']);
        $this->assertSame('https://redirect-issue.example.com/noindex-page/', data_get($noindexIssue, 'evidence.examples.0.url'));
        $this->assertSame('should_fix', $discoveredIssue['severity']);
        $this->assertSame('seo.indexation_coverage', $discoveredIssue['control_id']);
        $this->assertNull(data_get($discoveredIssue, 'evidence.examples.0.last_crawled'));
        $this->assertSame('security.headers_baseline', $headersIssue['issue_class']);
        $this->assertSame('controlled', $headersIssue['control_state']);
        $this->assertSame('astro_repo_controlled', $headersIssue['execution_surface']);
        $this->assertTrue($headersIssue['fleet_managed']);
        $this->assertSame('moveroo/headers-issue-site', $headersIssue['controller_repo']);
        $this->assertSame('https://github.com/moveroo/headers-issue-site', $headersIssue['controller_repo_url']);

        $detailResponse = $this->withHeaders([
            'Authorization' => 'Bearer test-api-key',
        ])->getJson('/api/issues/'.urlencode($redirectIssue['issue_id']));

        $detailResponse
            ->assertOk()
            ->assertJsonPath('issue_id', $redirectIssue['issue_id'])
            ->assertJsonPath('issue_class', 'page_with_redirect_in_sitemap')
            ->assertJsonPath('conversion_links.target.moveroo_subdomain', 'https://redirect-issue.moveroo.com.au')
            ->assertJsonPath('conversion_links.target.contact_us_page', 'https://redirect-issue.example.com/contact-us')
            ->assertJsonPath('conversion_links.target.legacy_bookings_replacement', 'https://removalist.net/booking/create')
            ->assertJsonPath('conversion_links.legacy_endpoints.legacy_payment_endpoint.resolved_url', 'https://redirect-issue.moveroo.com.au/contact')
            ->assertJsonPath('evidence.source_domain_id', $redirectDomain->id)
            ->assertJsonPath('evidence.examples.0.url', 'https://redirect-issue.example.com/');
    }

    public function test_issues_endpoint_emits_one_issue_per_issue_family_for_one_property(): void
    {
        config()->set('services.domain_monitor.brain_api_key', 'test-api-key');

        $domain = Domain::factory()->create([
            'domain' => 'multi-issue.example.com',
            'expires_at' => null,
            'is_active' => true,
            'platform' => 'Astro',
            'hosting_provider' => 'Vercel',
        ]);

        $property = WebProperty::factory()->create([
            'slug' => 'multi-issue-site',
            'name' => 'Multi Issue Site',
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

        PropertyRepository::create([
            'web_property_id' => $property->id,
            'repo_name' => 'multi-issue-site',
            'repo_provider' => 'local_only',
            'local_path' => '/Users/jasonhill/Projects/websites/multi-issue-site',
            'framework' => 'Astro',
            'is_primary' => true,
        ]);

        DomainSeoBaseline::create([
            'domain_id' => $domain->id,
            'web_property_id' => $property->id,
            'baseline_type' => 'search_console',
            'captured_at' => now(),
            'captured_by' => 'test',
            'source_provider' => 'matomo',
            'matomo_site_id' => '44',
            'search_console_property_uri' => 'sc-domain:multi-issue.example.com',
            'search_type' => 'web',
            'date_range_start' => now()->subDays(28)->toDateString(),
            'date_range_end' => now()->toDateString(),
            'import_method' => 'matomo_plus_manual_csv',
            'clicks' => 10,
            'impressions' => 100,
            'ctr' => 0.1,
            'average_position' => 12.3,
            'indexed_pages' => 20,
            'not_indexed_pages' => 5,
            'blocked_by_robots' => 2,
            'duplicate_without_user_selected_canonical' => 6,
            'raw_payload' => [
                'issues' => [
                    ['label' => 'Blocked by robots.txt', 'count' => 2],
                    ['label' => 'Duplicate without user-selected canonical', 'count' => 6],
                ],
            ],
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer test-api-key',
        ])->getJson('/api/issues');

        $response->assertOk();

        /** @var array<int, array<string, mixed>> $payloadIssues */
        $payloadIssues = $response->json('issues');
        /** @var \Illuminate\Support\Collection<int, array<string, mixed>> $issues */
        $issues = collect($payloadIssues)->where('property_slug', 'multi-issue-site')->values();

        $this->assertCount(2, $issues);
        $this->assertSame(
            ['blocked_by_robots_in_indexing', 'duplicate_without_user_selected_canonical'],
            $issues->pluck('issue_class')->sort()->values()->all()
        );
        $blockedIssue = $issues->firstWhere('issue_class', 'blocked_by_robots_in_indexing');
        $duplicateIssue = $issues->firstWhere('issue_class', 'duplicate_without_user_selected_canonical');
        $this->assertIsArray($blockedIssue);
        $this->assertIsArray($duplicateIssue);
        $this->assertSame('must_fix', $blockedIssue['severity']);
        $this->assertSame('seo.robots_and_sitemap_consistency', $blockedIssue['control_id']);
        $this->assertSame('should_fix', $duplicateIssue['severity']);
        $this->assertSame('seo.canonical_consistency', $duplicateIssue['control_id']);
        /** @var array<int, string> $relatedIssueClasses */
        $relatedIssueClasses = is_array($issues->first()['evidence']['related_issue_classes'] ?? null)
            ? $issues->first()['evidence']['related_issue_classes']
            : [];
        $this->assertSame(
            ['blocked_by_robots_in_indexing', 'duplicate_without_user_selected_canonical'],
            collect($relatedIssueClasses)->sort()->values()->all()
        );
        $this->assertCount(2, $issues->pluck('issue_id')->unique());
    }

    public function test_issues_endpoint_hides_intentional_wordpress_admin_exclusions_but_detail_remains_available(): void
    {
        config()->set('services.domain_monitor.brain_api_key', 'test-api-key');

        $domain = Domain::factory()->create([
            'domain' => 'intentional-admin.example.com',
            'expires_at' => null,
            'is_active' => true,
            'platform' => 'WordPress',
            'hosting_provider' => 'DreamIT Host',
        ]);

        $property = WebProperty::factory()->create([
            'slug' => 'intentional-admin-site',
            'name' => 'Intentional Admin Site',
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
                        'url' => 'https://intentional-admin.example.com/robots.txt',
                        'has_standard_wordpress_admin_rule' => true,
                        'allow_admin_ajax' => true,
                    ],
                ],
            ],
            'retry_count' => 0,
        ]);

        DomainSeoBaseline::create([
            'domain_id' => $domain->id,
            'web_property_id' => $property->id,
            'baseline_type' => 'search_console',
            'captured_at' => now(),
            'captured_by' => 'test',
            'source_provider' => 'matomo',
            'matomo_site_id' => '42',
            'search_console_property_uri' => 'sc-domain:intentional-admin.example.com',
            'search_type' => 'web',
            'date_range_start' => now()->subDays(28)->toDateString(),
            'date_range_end' => now()->toDateString(),
            'import_method' => 'matomo_api',
            'blocked_by_robots' => 1,
            'raw_payload' => [
                'issues' => [
                    ['label' => 'Blocked by robots.txt', 'count' => 1],
                ],
            ],
        ]);

        SearchConsoleIssueSnapshot::factory()->create([
            'domain_id' => $domain->id,
            'web_property_id' => $property->id,
            'issue_class' => 'blocked_by_robots_in_indexing',
            'source_issue_label' => 'Blocked by robots.txt',
            'capture_method' => 'gsc_drilldown_zip',
            'source_report' => 'search_console_page_indexing_drilldown',
            'source_property' => 'sc-domain:intentional-admin.example.com',
            'captured_at' => now(),
            'captured_by' => 'test',
            'affected_url_count' => 1,
            'sample_urls' => [
                'https://intentional-admin.example.com/wp-admin/',
            ],
            'examples' => [
                ['url' => 'https://intentional-admin.example.com/wp-admin/', 'last_crawled' => now()->toDateString()],
            ],
            'normalized_payload' => [
                'affected_urls' => [
                    'https://intentional-admin.example.com/wp-admin/',
                ],
            ],
        ]);

        SearchConsoleIssueSnapshot::factory()->create([
            'domain_id' => $domain->id,
            'web_property_id' => $property->id,
            'issue_class' => 'excluded_by_noindex',
            'source_issue_label' => "Excluded by 'noindex' tag",
            'capture_method' => 'gsc_drilldown_zip',
            'source_report' => 'search_console_page_indexing_drilldown',
            'source_property' => 'sc-domain:intentional-admin.example.com',
            'captured_at' => now(),
            'captured_by' => 'test',
            'affected_url_count' => 1,
            'sample_urls' => [
                'https://intentional-admin.example.com/wp-login.php?redirect_to=/wp-admin/',
            ],
            'examples' => [
                ['url' => 'https://intentional-admin.example.com/wp-login.php?redirect_to=/wp-admin/', 'last_crawled' => now()->toDateString()],
            ],
            'normalized_payload' => [
                'affected_urls' => [
                    'https://intentional-admin.example.com/wp-login.php?redirect_to=/wp-admin/',
                ],
            ],
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer test-api-key',
        ])->getJson('/api/issues');

        $response
            ->assertOk()
            ->assertJsonMissingPath('stats.issue_class_counts.blocked_by_robots_in_indexing')
            ->assertJsonMissingPath('stats.issue_class_counts.excluded_by_noindex');

        /** @var array<int, array<string, mixed>> $payloadIssues */
        $payloadIssues = $response->json('issues') ?? [];
        $issues = collect($payloadIssues);

        $this->assertNull($issues->firstWhere('issue_class', 'blocked_by_robots_in_indexing'));
        $this->assertNull($issues->firstWhere('issue_class', 'excluded_by_noindex'));

        $identity = app(\App\Services\DetectedIssueIdentityService::class);
        $blockedIssueId = $identity->makeIssueId($domain->id, $property->slug, 'blocked_by_robots_in_indexing');
        $noindexIssueId = $identity->makeIssueId($domain->id, $property->slug, 'excluded_by_noindex');

        $this->withHeaders([
            'Authorization' => 'Bearer test-api-key',
        ])->getJson('/api/issues/'.urlencode($blockedIssueId))
            ->assertOk()
            ->assertJsonPath('issue_class', 'blocked_by_robots_in_indexing')
            ->assertJsonPath('evidence.expected_exclusion.state', 'expected_robots_exclusion')
            ->assertJsonPath('evidence.expected_exclusion.code', 'intentional_admin_exclusion')
            ->assertJsonPath('evidence.expected_exclusion.robots.disallow_wp_admin', true);

        $this->withHeaders([
            'Authorization' => 'Bearer test-api-key',
        ])->getJson('/api/issues/'.urlencode($noindexIssueId))
            ->assertOk()
            ->assertJsonPath('issue_class', 'excluded_by_noindex')
            ->assertJsonPath('evidence.expected_exclusion.state', 'expected_noindex_exclusion')
            ->assertJsonPath('evidence.expected_exclusion.code', 'intentional_admin_exclusion');
    }

    public function test_issues_endpoint_keeps_admin_issue_visible_when_examples_are_truncated(): void
    {
        config()->set('services.domain_monitor.brain_api_key', 'test-api-key');

        $domain = Domain::factory()->create([
            'domain' => 'truncated-admin.example.com',
            'expires_at' => null,
            'is_active' => true,
            'platform' => 'WordPress',
            'hosting_provider' => 'DreamIT Host',
        ]);

        $property = WebProperty::factory()->create([
            'slug' => 'truncated-admin-site',
            'name' => 'Truncated Admin Site',
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
                        'url' => 'https://truncated-admin.example.com/robots.txt',
                        'has_standard_wordpress_admin_rule' => true,
                        'allow_admin_ajax' => true,
                    ],
                ],
            ],
            'retry_count' => 0,
        ]);

        DomainSeoBaseline::create([
            'domain_id' => $domain->id,
            'web_property_id' => $property->id,
            'baseline_type' => 'search_console',
            'captured_at' => now(),
            'captured_by' => 'test',
            'source_provider' => 'matomo',
            'matomo_site_id' => '84',
            'search_console_property_uri' => 'sc-domain:truncated-admin.example.com',
            'search_type' => 'web',
            'date_range_start' => now()->subDays(28)->toDateString(),
            'date_range_end' => now()->toDateString(),
            'import_method' => 'matomo_api',
            'blocked_by_robots' => 2,
            'raw_payload' => [
                'issues' => [
                    ['label' => 'Blocked by robots.txt', 'count' => 2],
                ],
            ],
        ]);

        SearchConsoleIssueSnapshot::factory()->create([
            'domain_id' => $domain->id,
            'web_property_id' => $property->id,
            'issue_class' => 'blocked_by_robots_in_indexing',
            'source_issue_label' => 'Blocked by robots.txt',
            'capture_method' => 'gsc_drilldown_zip',
            'source_report' => 'search_console_page_indexing_drilldown',
            'source_property' => 'sc-domain:truncated-admin.example.com',
            'captured_at' => now(),
            'captured_by' => 'test',
            'affected_url_count' => 2,
            'sample_urls' => [
                'https://truncated-admin.example.com/wp-admin/',
            ],
            'examples' => [
                ['url' => 'https://truncated-admin.example.com/wp-admin/', 'last_crawled' => now()->toDateString()],
            ],
            'normalized_payload' => [
                'affected_urls' => [
                    'https://truncated-admin.example.com/wp-admin/',
                ],
            ],
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer test-api-key',
        ])->getJson('/api/issues');

        $response->assertOk()
            ->assertJsonPath('stats.issue_class_counts.blocked_by_robots_in_indexing', 1);

        /** @var array<int, array<string, mixed>> $payloadIssues */
        $payloadIssues = $response->json('issues') ?? [];
        $blockedIssue = collect($payloadIssues)->firstWhere('issue_class', 'blocked_by_robots_in_indexing');

        $this->assertIsArray($blockedIssue);
        $this->assertNull(data_get($blockedIssue, 'evidence.expected_exclusion'));
    }

    public function test_issues_endpoint_keeps_admin_issue_visible_when_urls_are_on_a_non_property_host(): void
    {
        config()->set('services.domain_monitor.brain_api_key', 'test-api-key');

        $domain = Domain::factory()->create([
            'domain' => 'canonical-admin.example.com',
            'expires_at' => null,
            'is_active' => true,
            'platform' => 'WordPress',
            'hosting_provider' => 'DreamIT Host',
        ]);

        $property = WebProperty::factory()->create([
            'slug' => 'canonical-admin-site',
            'name' => 'Canonical Admin Site',
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
                        'url' => 'https://canonical-admin.example.com/robots.txt',
                        'has_standard_wordpress_admin_rule' => true,
                        'allow_admin_ajax' => true,
                    ],
                ],
            ],
            'retry_count' => 0,
        ]);

        DomainSeoBaseline::create([
            'domain_id' => $domain->id,
            'web_property_id' => $property->id,
            'baseline_type' => 'search_console',
            'captured_at' => now(),
            'captured_by' => 'test',
            'source_provider' => 'matomo',
            'matomo_site_id' => '85',
            'search_console_property_uri' => 'sc-domain:canonical-admin.example.com',
            'search_type' => 'web',
            'date_range_start' => now()->subDays(28)->toDateString(),
            'date_range_end' => now()->toDateString(),
            'import_method' => 'matomo_api',
            'blocked_by_robots' => 1,
            'raw_payload' => [
                'issues' => [
                    ['label' => 'Blocked by robots.txt', 'count' => 1],
                ],
            ],
        ]);

        SearchConsoleIssueSnapshot::factory()->create([
            'domain_id' => $domain->id,
            'web_property_id' => $property->id,
            'issue_class' => 'blocked_by_robots_in_indexing',
            'source_issue_label' => 'Blocked by robots.txt',
            'capture_method' => 'gsc_drilldown_zip',
            'source_report' => 'search_console_page_indexing_drilldown',
            'source_property' => 'sc-domain:canonical-admin.example.com',
            'captured_at' => now(),
            'captured_by' => 'test',
            'affected_url_count' => 1,
            'sample_urls' => [
                'https://staging.canonical-admin.example.com/wp-admin/',
            ],
            'examples' => [
                ['url' => 'https://staging.canonical-admin.example.com/wp-admin/', 'last_crawled' => now()->toDateString()],
            ],
            'normalized_payload' => [
                'affected_urls' => [
                    'https://staging.canonical-admin.example.com/wp-admin/',
                ],
            ],
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer test-api-key',
        ])->getJson('/api/issues');

        $response->assertOk()
            ->assertJsonPath('stats.issue_class_counts.blocked_by_robots_in_indexing', 1);

        /** @var array<int, array<string, mixed>> $payloadIssues */
        $payloadIssues = $response->json('issues') ?? [];
        $blockedIssue = collect($payloadIssues)->firstWhere('issue_class', 'blocked_by_robots_in_indexing');

        $this->assertIsArray($blockedIssue);
        $this->assertNull(data_get($blockedIssue, 'evidence.expected_exclusion'));
    }

    public function test_issues_endpoint_suppresses_expected_wordpress_system_404_noise_and_retired_author_archives(): void
    {
        config()->set('services.domain_monitor.brain_api_key', 'test-api-key');

        $systemDomain = Domain::factory()->create([
            'domain' => 'system-noise.example.com',
            'expires_at' => null,
            'is_active' => true,
            'platform' => 'WordPress',
            'hosting_provider' => 'DreamIT Host',
        ]);

        $systemProperty = WebProperty::factory()->create([
            'slug' => 'system-noise-site',
            'name' => 'System Noise Site',
            'property_type' => 'website',
            'status' => 'active',
            'primary_domain_id' => $systemDomain->id,
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $systemProperty->id,
            'domain_id' => $systemDomain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        PropertyRepository::create([
            'web_property_id' => $systemProperty->id,
            'repo_name' => 'system-noise-site',
            'repo_provider' => 'local_only',
            'local_path' => '/Users/jasonhill/Projects/websites/system-noise-site',
            'framework' => 'WordPress',
            'is_primary' => true,
        ]);

        DomainSeoBaseline::create([
            'domain_id' => $systemDomain->id,
            'web_property_id' => $systemProperty->id,
            'baseline_type' => 'search_console',
            'captured_at' => now(),
            'captured_by' => 'test',
            'source_provider' => 'matomo',
            'matomo_site_id' => '301',
            'search_console_property_uri' => 'sc-domain:system-noise.example.com',
            'search_type' => 'web',
            'date_range_start' => now()->subDays(28)->toDateString(),
            'date_range_end' => now()->toDateString(),
            'import_method' => 'matomo_api',
            'not_found_404' => 2,
            'raw_payload' => [
                'issues' => [
                    ['label' => 'Not found (404)', 'count' => 2],
                ],
            ],
        ]);

        SearchConsoleIssueSnapshot::factory()->create([
            'domain_id' => $systemDomain->id,
            'web_property_id' => $systemProperty->id,
            'issue_class' => 'not_found_404',
            'source_issue_label' => 'Not found (404)',
            'capture_method' => 'gsc_drilldown_zip',
            'source_report' => 'search_console_page_indexing_drilldown',
            'source_property' => 'sc-domain:system-noise.example.com',
            'captured_at' => now(),
            'captured_by' => 'test',
            'affected_url_count' => 2,
            'sample_urls' => [
                'https://system-noise.example.com/wp-content/plugins/revslider/readme.txt',
                'https://system-noise.example.com/wp-comments-post.php',
            ],
            'examples' => [
                ['url' => 'https://system-noise.example.com/wp-content/plugins/revslider/readme.txt', 'last_crawled' => now()->subDay()->toDateString()],
                ['url' => 'https://system-noise.example.com/wp-comments-post.php', 'last_crawled' => now()->subDay()->toDateString()],
            ],
            'normalized_payload' => [
                'affected_urls' => [
                    'https://system-noise.example.com/wp-content/plugins/revslider/readme.txt',
                    'https://system-noise.example.com/wp-comments-post.php',
                ],
            ],
        ]);

        $authorDomain = Domain::factory()->create([
            'domain' => 'author-archive.example.com',
            'expires_at' => null,
            'is_active' => true,
            'platform' => 'WordPress',
            'hosting_provider' => 'DreamIT Host',
        ]);

        $authorProperty = WebProperty::factory()->create([
            'slug' => 'author-archive-site',
            'name' => 'Author Archive Site',
            'property_type' => 'website',
            'status' => 'active',
            'primary_domain_id' => $authorDomain->id,
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $authorProperty->id,
            'domain_id' => $authorDomain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        PropertyRepository::create([
            'web_property_id' => $authorProperty->id,
            'repo_name' => 'author-archive-site',
            'repo_provider' => 'local_only',
            'local_path' => '/Users/jasonhill/Projects/websites/author-archive-site',
            'framework' => 'WordPress',
            'is_primary' => true,
        ]);

        DomainSeoBaseline::create([
            'domain_id' => $authorDomain->id,
            'web_property_id' => $authorProperty->id,
            'baseline_type' => 'search_console',
            'captured_at' => now(),
            'captured_by' => 'test',
            'source_provider' => 'matomo',
            'matomo_site_id' => '302',
            'search_console_property_uri' => 'sc-domain:author-archive.example.com',
            'search_type' => 'web',
            'date_range_start' => now()->subDays(28)->toDateString(),
            'date_range_end' => now()->toDateString(),
            'import_method' => 'matomo_api',
            'not_found_404' => 1,
            'raw_payload' => [
                'issues' => [
                    ['label' => 'Not found (404)', 'count' => 1],
                ],
            ],
        ]);

        SearchConsoleIssueSnapshot::factory()->create([
            'domain_id' => $authorDomain->id,
            'web_property_id' => $authorProperty->id,
            'issue_class' => 'not_found_404',
            'source_issue_label' => 'Not found (404)',
            'capture_method' => 'gsc_drilldown_zip',
            'source_report' => 'search_console_page_indexing_drilldown',
            'source_property' => 'sc-domain:author-archive.example.com',
            'captured_at' => now()->subDay(),
            'captured_by' => 'test',
            'affected_url_count' => 1,
            'sample_urls' => [
                'https://author-archive.example.com/author/removalist/',
            ],
            'examples' => [
                ['url' => 'https://author-archive.example.com/author/removalist/', 'last_crawled' => now()->subDay()->toDateString()],
            ],
            'normalized_payload' => [
                'affected_urls' => [
                    'https://author-archive.example.com/author/removalist/',
                ],
            ],
        ]);

        SearchConsoleIssueSnapshot::factory()->create([
            'domain_id' => $authorDomain->id,
            'web_property_id' => $authorProperty->id,
            'issue_class' => 'not_found_404',
            'source_issue_label' => 'Not found (404)',
            'capture_method' => 'gsc_api',
            'source_report' => 'search_console_api_bundle',
            'source_property' => 'sc-domain:author-archive.example.com',
            'captured_at' => now()->subHours(12),
            'captured_by' => 'test',
            'normalized_payload' => [
                'url_inspection' => [
                    'inspected_urls' => [
                        [
                            'url' => 'https://author-archive.example.com/author/removalist/',
                            'coverage_state' => 'Not found (404)',
                            'referring_urls' => [],
                            'sitemaps' => [],
                        ],
                    ],
                ],
            ],
        ]);

        SearchConsoleIssueSnapshot::factory()->create([
            'domain_id' => $authorDomain->id,
            'web_property_id' => $authorProperty->id,
            'issue_class' => 'not_found_404',
            'source_issue_label' => 'Not found (404)',
            'capture_method' => 'gsc_live_recheck',
            'source_report' => 'search_console_live_http_recheck',
            'source_property' => 'sc-domain:author-archive.example.com',
            'captured_at' => now(),
            'captured_by' => 'test',
            'affected_url_count' => 1,
            'sample_urls' => ['https://author-archive.example.com/author/removalist/'],
            'normalized_payload' => [
                'affected_urls' => ['https://author-archive.example.com/author/removalist/'],
                'live_url_checks' => [
                    [
                        'url' => 'https://author-archive.example.com/author/removalist/',
                        'checked_at' => now()->toIso8601String(),
                        'final_url' => 'https://author-archive.example.com/',
                        'final_status' => 200,
                        'resolved_ok' => true,
                        'host_changed' => false,
                    ],
                ],
            ],
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer test-api-key',
        ])->getJson('/api/issues');

        $response->assertOk()
            ->assertJsonMissingPath('stats.issue_class_counts.not_found_404');

        /** @var array<int, array<string, mixed>> $payloadIssues */
        $payloadIssues = $response->json('issues') ?? [];
        $this->assertNull(collect($payloadIssues)->first(function (array $issue): bool {
            return ($issue['property_slug'] ?? null) === 'system-noise-site'
                && ($issue['issue_class'] ?? null) === 'not_found_404';
        }));
        $this->assertNull(collect($payloadIssues)->first(function (array $issue): bool {
            return ($issue['property_slug'] ?? null) === 'author-archive-site'
                && ($issue['issue_class'] ?? null) === 'not_found_404';
        }));

        $identity = app(\App\Services\DetectedIssueIdentityService::class);

        $this->withHeaders([
            'Authorization' => 'Bearer test-api-key',
        ])->getJson('/api/issues/'.urlencode($identity->makeIssueId($systemDomain->id, $systemProperty->slug, 'not_found_404')))
            ->assertOk()
            ->assertJsonPath('evidence.expected_exclusion.state', 'expected_wordpress_system_404');

        $this->withHeaders([
            'Authorization' => 'Bearer test-api-key',
        ])->getJson('/api/issues/'.urlencode($identity->makeIssueId($authorDomain->id, $authorProperty->slug, 'not_found_404')))
            ->assertOk()
            ->assertJsonPath('evidence.expected_exclusion.state', 'retired_wordpress_author_archive');
    }

    public function test_issues_endpoint_keeps_only_still_failing_404_examples_after_live_rechecks(): void
    {
        config()->set('services.domain_monitor.brain_api_key', 'test-api-key');

        $domain = Domain::factory()->create([
            'domain' => 'mixed-404.example.com',
            'expires_at' => null,
            'is_active' => true,
            'platform' => 'WordPress',
            'hosting_provider' => 'DreamIT Host',
        ]);

        $property = WebProperty::factory()->create([
            'slug' => 'mixed-404-site',
            'name' => 'Mixed 404 Site',
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

        DomainSeoBaseline::create([
            'domain_id' => $domain->id,
            'web_property_id' => $property->id,
            'baseline_type' => 'search_console',
            'captured_at' => now(),
            'captured_by' => 'test',
            'source_provider' => 'matomo',
            'matomo_site_id' => '303',
            'search_console_property_uri' => 'sc-domain:mixed-404.example.com',
            'search_type' => 'web',
            'date_range_start' => now()->subDays(28)->toDateString(),
            'date_range_end' => now()->toDateString(),
            'import_method' => 'matomo_api',
            'not_found_404' => 2,
            'raw_payload' => [
                'issues' => [
                    ['label' => 'Not found (404)', 'count' => 2],
                ],
            ],
        ]);

        SearchConsoleIssueSnapshot::factory()->create([
            'domain_id' => $domain->id,
            'web_property_id' => $property->id,
            'issue_class' => 'not_found_404',
            'source_issue_label' => 'Not found (404)',
            'capture_method' => 'gsc_drilldown_zip',
            'source_report' => 'search_console_page_indexing_drilldown',
            'source_property' => 'sc-domain:mixed-404.example.com',
            'captured_at' => now()->subDay(),
            'captured_by' => 'test',
            'affected_url_count' => 2,
            'sample_urls' => [
                'https://mixed-404.example.com/fixed-page/',
                'https://mixed-404.example.com/still-missing/',
            ],
            'examples' => [
                ['url' => 'https://mixed-404.example.com/fixed-page/', 'last_crawled' => now()->subDays(2)->toDateString()],
                ['url' => 'https://mixed-404.example.com/still-missing/', 'last_crawled' => now()->subDays(2)->toDateString()],
            ],
            'normalized_payload' => [
                'affected_urls' => [
                    'https://mixed-404.example.com/fixed-page/',
                    'https://mixed-404.example.com/still-missing/',
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
            'source_property' => 'sc-domain:mixed-404.example.com',
            'captured_at' => now(),
            'captured_by' => 'test',
            'affected_url_count' => 2,
            'sample_urls' => [
                'https://mixed-404.example.com/fixed-page/',
                'https://mixed-404.example.com/still-missing/',
            ],
            'normalized_payload' => [
                'affected_urls' => [
                    'https://mixed-404.example.com/fixed-page/',
                    'https://mixed-404.example.com/still-missing/',
                ],
                'live_url_checks' => [
                    [
                        'url' => 'https://mixed-404.example.com/fixed-page/',
                        'checked_at' => now()->toIso8601String(),
                        'final_url' => 'https://mixed-404.example.com/fixed-page/',
                        'final_status' => 200,
                        'resolved_ok' => true,
                        'host_changed' => false,
                    ],
                    [
                        'url' => 'https://mixed-404.example.com/still-missing/',
                        'checked_at' => now()->toIso8601String(),
                        'final_url' => 'https://mixed-404.example.com/still-missing/',
                        'final_status' => 404,
                        'resolved_ok' => false,
                        'host_changed' => false,
                    ],
                ],
            ],
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer test-api-key',
        ])->getJson('/api/issues');

        $response->assertOk()
            ->assertJsonPath('stats.issue_class_counts.not_found_404', 1);

        /** @var array<int, array<string, mixed>> $payloadIssues */
        $payloadIssues = $response->json('issues') ?? [];
        $issue = collect($payloadIssues)->firstWhere('issue_class', 'not_found_404');

        $this->assertIsArray($issue);
        $this->assertSame(1, data_get($issue, 'evidence.affected_url_count'));
        $this->assertSame(
            ['https://mixed-404.example.com/still-missing/'],
            data_get($issue, 'evidence.affected_urls')
        );
        $this->assertSame(
            'https://mixed-404.example.com/still-missing/',
            data_get($issue, 'evidence.examples.0.url')
        );
        $this->assertSame(
            ['https://mixed-404.example.com/still-missing/'],
            collect((array) data_get($issue, 'evidence.live_url_checks'))
                ->pluck('url')
                ->values()
                ->all()
        );
    }

    public function test_issues_endpoint_does_not_treat_wp_json_as_expected_system_404_noise(): void
    {
        config()->set('services.domain_monitor.brain_api_key', 'test-api-key');

        $domain = Domain::factory()->create([
            'domain' => 'wp-json-404.example.com',
            'expires_at' => null,
            'is_active' => true,
            'platform' => 'WordPress',
            'hosting_provider' => 'DreamIT Host',
        ]);

        $property = WebProperty::factory()->create([
            'slug' => 'wp-json-404-site',
            'name' => 'WP JSON 404 Site',
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

        PropertyRepository::create([
            'web_property_id' => $property->id,
            'repo_name' => 'wp-json-404-site',
            'repo_provider' => 'local_only',
            'local_path' => '/Users/jasonhill/Projects/websites/wp-json-404-site',
            'framework' => 'WordPress',
            'is_primary' => true,
        ]);

        DomainSeoBaseline::create([
            'domain_id' => $domain->id,
            'web_property_id' => $property->id,
            'baseline_type' => 'search_console',
            'captured_at' => now(),
            'captured_by' => 'test',
            'source_provider' => 'matomo',
            'matomo_site_id' => '304',
            'search_console_property_uri' => 'sc-domain:wp-json-404.example.com',
            'search_type' => 'web',
            'date_range_start' => now()->subDays(28)->toDateString(),
            'date_range_end' => now()->toDateString(),
            'import_method' => 'matomo_api',
            'not_found_404' => 1,
            'raw_payload' => [
                'issues' => [
                    ['label' => 'Not found (404)', 'count' => 1],
                ],
            ],
        ]);

        SearchConsoleIssueSnapshot::factory()->create([
            'domain_id' => $domain->id,
            'web_property_id' => $property->id,
            'issue_class' => 'not_found_404',
            'source_issue_label' => 'Not found (404)',
            'capture_method' => 'gsc_drilldown_zip',
            'source_report' => 'search_console_page_indexing_drilldown',
            'source_property' => 'sc-domain:wp-json-404.example.com',
            'captured_at' => now(),
            'captured_by' => 'test',
            'affected_url_count' => 1,
            'sample_urls' => ['https://wp-json-404.example.com/wp-json/'],
            'examples' => [
                ['url' => 'https://wp-json-404.example.com/wp-json/', 'last_crawled' => now()->subDay()->toDateString()],
            ],
            'normalized_payload' => [
                'affected_urls' => ['https://wp-json-404.example.com/wp-json/'],
            ],
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer test-api-key',
        ])->getJson('/api/issues');

        $response->assertOk()
            ->assertJsonPath('stats.issue_class_counts.not_found_404', 1);

        /** @var array<int, array<string, mixed>> $payloadIssues */
        $payloadIssues = $response->json('issues') ?? [];
        $issue = collect($payloadIssues)->firstWhere('property_slug', 'wp-json-404-site');

        $this->assertIsArray($issue);
        $this->assertNull(data_get($issue, 'evidence.expected_exclusion'));
    }

    public function test_issues_endpoint_keeps_legacy_payment_404_active_without_explicit_replacement_target(): void
    {
        config()->set('services.domain_monitor.brain_api_key', 'test-api-key');

        $domain = Domain::factory()->create([
            'domain' => 'legacy-payment.example.com',
            'expires_at' => null,
            'is_active' => true,
            'platform' => 'WordPress',
            'hosting_provider' => 'DreamIT Host',
        ]);

        $property = WebProperty::factory()->create([
            'slug' => 'legacy-payment-site',
            'name' => 'Legacy Payment Site',
            'property_type' => 'website',
            'status' => 'active',
            'primary_domain_id' => $domain->id,
            'target_moveroo_subdomain_url' => 'https://quotes.legacy-payment.example.com',
            'legacy_moveroo_endpoint_scan' => [
                'legacy_payment_endpoint' => [
                    'classification' => 'legacy_payment_endpoint',
                    'found_on' => 'https://legacy-payment.example.com/',
                    'url' => 'https://quotes.legacy-payment.example.com/payments',
                    'resolved_url' => 'https://quotes.legacy-payment.example.com/contact',
                    'resolved_status' => 200,
                    'resolved_host_changed' => false,
                ],
            ],
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $domain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        PropertyRepository::create([
            'web_property_id' => $property->id,
            'repo_name' => 'legacy-payment-site',
            'repo_provider' => 'local_only',
            'local_path' => '/Users/jasonhill/Projects/websites/legacy-payment-site',
            'framework' => 'WordPress',
            'is_primary' => true,
        ]);

        DomainSeoBaseline::create([
            'domain_id' => $domain->id,
            'web_property_id' => $property->id,
            'baseline_type' => 'search_console',
            'captured_at' => now(),
            'captured_by' => 'test',
            'source_provider' => 'matomo',
            'matomo_site_id' => '305',
            'search_console_property_uri' => 'sc-domain:legacy-payment.example.com',
            'search_type' => 'web',
            'date_range_start' => now()->subDays(28)->toDateString(),
            'date_range_end' => now()->toDateString(),
            'import_method' => 'matomo_api',
            'not_found_404' => 1,
            'raw_payload' => [
                'issues' => [
                    ['label' => 'Not found (404)', 'count' => 1],
                ],
            ],
        ]);

        SearchConsoleIssueSnapshot::factory()->create([
            'domain_id' => $domain->id,
            'web_property_id' => $property->id,
            'issue_class' => 'not_found_404',
            'source_issue_label' => 'Not found (404)',
            'capture_method' => 'gsc_drilldown_zip',
            'source_report' => 'search_console_page_indexing_drilldown',
            'source_property' => 'sc-domain:legacy-payment.example.com',
            'captured_at' => now(),
            'captured_by' => 'test',
            'affected_url_count' => 1,
            'sample_urls' => ['https://quotes.legacy-payment.example.com/payments'],
            'examples' => [
                ['url' => 'https://quotes.legacy-payment.example.com/payments', 'last_crawled' => now()->subDay()->toDateString()],
            ],
            'normalized_payload' => [
                'affected_urls' => ['https://quotes.legacy-payment.example.com/payments'],
            ],
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer test-api-key',
        ])->getJson('/api/issues');

        $response->assertOk()
            ->assertJsonPath('stats.issue_class_counts.not_found_404', 1);

        /** @var array<int, array<string, mixed>> $payloadIssues */
        $payloadIssues = $response->json('issues') ?? [];
        $issue = collect($payloadIssues)->firstWhere('property_slug', 'legacy-payment-site');

        $this->assertIsArray($issue);
        $this->assertNull(data_get($issue, 'evidence.expected_exclusion'));
    }

    public function test_issues_endpoint_includes_broken_link_source_pages(): void
    {
        config()->set('services.domain_monitor.brain_api_key', 'test-api-key');

        $domain = Domain::factory()->create([
            'domain' => 'broken-links.example.com',
            'expires_at' => null,
            'is_active' => true,
            'platform' => 'Astro',
            'hosting_provider' => 'Vercel',
        ]);

        $property = WebProperty::factory()->create([
            'slug' => 'broken-links-site',
            'name' => 'Broken Links Site',
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

        PropertyRepository::create([
            'web_property_id' => $property->id,
            'repo_name' => 'moveroo/broken-links-site',
            'repo_provider' => 'github',
            'repo_url' => 'https://github.com/moveroo/broken-links-site',
            'local_path' => '/Users/jasonhill/Projects/websites/broken-links-site',
            'framework' => 'Astro',
            'is_primary' => true,
            'is_controller' => true,
            'deployment_provider' => 'vercel',
            'deployment_project_name' => 'broken-links-site',
            'deployment_project_id' => 'prj_brokenlinks123',
        ]);

        DomainCheck::factory()->create([
            'domain_id' => $domain->id,
            'check_type' => 'broken_links',
            'status' => 'warn',
            'created_at' => now()->subHour(),
            'payload' => [
                'broken_links_count' => 1,
                'pages_scanned' => 4,
                'broken_links' => [
                    [
                        'url' => 'https://broken-links.example.com/old-result/',
                        'status' => 404,
                        'found_on' => 'https://broken-links.example.com/old-page/',
                    ],
                ],
            ],
        ]);

        DomainCheck::factory()->create([
            'domain_id' => $domain->id,
            'check_type' => 'broken_links',
            'status' => 'fail',
            'created_at' => now(),
            'payload' => [
                'broken_links_count' => 2,
                'pages_scanned' => 12,
                'broken_links' => [
                    [
                        'url' => 'https://broken-links.example.com/missing-page/?token=secret',
                        'status' => 404,
                        'found_on' => 'https://broken-links.example.com/services/?preview=1',
                    ],
                    [
                        'url' => 'https://broken-links.example.com/old-booking/?session=abc',
                        'status' => 410,
                        'found_on' => 'https://broken-links.example.com/contact/',
                    ],
                ],
            ],
        ]);

        /** @var array<int, array<string, mixed>> $issues */
        $issues = $this->withHeaders([
            'Authorization' => 'Bearer test-api-key',
        ])->getJson('/api/issues')
            ->assertOk()
            ->json('issues');

        /** @var array<string, mixed>|null $brokenLinksIssue */
        $brokenLinksIssue = collect($issues)->firstWhere('issue_class', 'seo.broken_links');

        $this->assertIsArray($brokenLinksIssue);
        $this->assertSame(1, collect($issues)->where('issue_class', 'seo.broken_links')->count());
        $this->assertSame('broken-links-site', $brokenLinksIssue['property_slug']);
        $this->assertSame(2, data_get($brokenLinksIssue, 'evidence.broken_links_count'));
        $this->assertSame(12, data_get($brokenLinksIssue, 'evidence.pages_scanned'));
        $this->assertSame(
            'https://broken-links.example.com/missing-page/',
            data_get($brokenLinksIssue, 'evidence.broken_links.0.url')
        );
        $this->assertSame(
            404,
            data_get($brokenLinksIssue, 'evidence.broken_links.0.status')
        );
        $this->assertSame(
            'https://broken-links.example.com/services/',
            data_get($brokenLinksIssue, 'evidence.broken_links.0.found_on')
        );

        $detailResponse = $this->withHeaders([
            'Authorization' => 'Bearer test-api-key',
        ])->getJson('/api/issues/'.urlencode((string) $brokenLinksIssue['issue_id']))
            ->assertOk()
            ->assertJsonPath('evidence.broken_links.1.url', 'https://broken-links.example.com/old-booking/')
            ->assertJsonPath('evidence.broken_links.1.found_on', 'https://broken-links.example.com/contact/');

        $this->assertSame(
            ['https://broken-links.example.com/missing-page/', 'https://broken-links.example.com/old-booking/'],
            array_column($detailResponse->json('evidence.broken_links') ?? [], 'url')
        );
    }

    public function test_issues_endpoint_reports_uncontrolled_when_no_controller_path_exists(): void
    {
        config()->set('services.domain_monitor.brain_api_key', 'test-api-key');

        $domain = Domain::factory()->create([
            'domain' => 'uncontrolled.example.com',
            'expires_at' => null,
            'is_active' => true,
            'platform' => 'WordPress',
            'hosting_provider' => 'DreamIT Host',
        ]);

        $property = WebProperty::factory()->create([
            'slug' => 'uncontrolled-site',
            'name' => 'Uncontrolled Site',
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

        PropertyRepository::create([
            'web_property_id' => $property->id,
            'repo_name' => '_wp-house',
            'repo_provider' => 'local_only',
            'local_path' => null,
            'framework' => 'WordPress',
            'is_primary' => true,
        ]);

        DomainSeoBaseline::create([
            'domain_id' => $domain->id,
            'web_property_id' => $property->id,
            'baseline_type' => 'search_console',
            'captured_at' => now(),
            'captured_by' => 'test',
            'source_provider' => 'matomo',
            'matomo_site_id' => '31',
            'search_console_property_uri' => 'sc-domain:uncontrolled.example.com',
            'search_type' => 'web',
            'date_range_start' => now()->subDays(28)->toDateString(),
            'date_range_end' => now()->toDateString(),
            'import_method' => 'matomo_api',
            'clicks' => 10,
            'impressions' => 100,
            'ctr' => 0.1,
            'average_position' => 12.3,
            'indexed_pages' => 20,
            'not_indexed_pages' => 5,
            'pages_with_redirect' => 4,
            'raw_payload' => ['issues' => [['label' => 'Page with redirect', 'count' => 4]]],
        ]);

        /** @var array<int, array<string, mixed>> $issues */
        $issues = $this->withHeaders(['Authorization' => 'Bearer test-api-key'])
            ->getJson('/api/issues')
            ->assertOk()
            ->json('issues');

        $issue = collect($issues)->firstWhere('property_slug', 'uncontrolled-site');

        $this->assertNotNull($issue);
        $this->assertSame('uncontrolled', $issue['control_state']);
        $this->assertNull($issue['execution_surface']);
        $this->assertFalse($issue['fleet_managed']);
        $this->assertSame('_wp-house', $issue['controller_repo']);
        $this->assertNull($issue['controller_repo_url']);
    }

    public function test_issues_endpoint_prefers_explicit_controller_repo_and_requires_its_local_path(): void
    {
        config()->set('services.domain_monitor.brain_api_key', 'test-api-key');

        $domain = Domain::factory()->create([
            'domain' => 'cartransport.movingagain.com.au',
            'expires_at' => null,
            'is_active' => true,
            'platform' => 'Astro',
            'hosting_provider' => 'Vercel',
        ]);

        $property = WebProperty::factory()->create([
            'slug' => 'ma-car-transport',
            'name' => 'Moving Again Car Transport',
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

        PropertyRepository::create([
            'web_property_id' => $property->id,
            'repo_name' => 'cartransport-new-astro',
            'repo_provider' => 'github',
            'repo_url' => 'https://github.com/iamjasonhill/cartransport-astro',
            'local_path' => '/Users/jasonhill/Projects/websites/cartransport-new-astro',
            'framework' => 'Astro',
            'is_primary' => true,
            'deployment_provider' => 'vercel',
            'deployment_project_id' => 'prj_old123',
        ]);

        PropertyRepository::create([
            'web_property_id' => $property->id,
            'repo_name' => 'moveroo/ma-catrans-program',
            'repo_provider' => 'github',
            'repo_url' => 'https://github.com/moveroo/ma-catrans-program',
            'local_path' => null,
            'framework' => 'Astro',
            'is_controller' => true,
            'deployment_provider' => 'vercel',
            'deployment_project_name' => 'ma-catrans-program',
        ]);

        DomainCheck::factory()->create([
            'domain_id' => $domain->id,
            'check_type' => 'security_headers',
            'status' => 'warn',
        ]);

        /** @var array<int, array<string, mixed>> $issues */
        $issues = $this->withHeaders(['Authorization' => 'Bearer test-api-key'])
            ->getJson('/api/issues')
            ->assertOk()
            ->json('issues');

        $issue = collect($issues)->firstWhere('property_slug', 'ma-car-transport');

        $this->assertNotNull($issue);
        $this->assertSame('moveroo/ma-catrans-program', $issue['controller_repo']);
        $this->assertSame('https://github.com/moveroo/ma-catrans-program', $issue['controller_repo_url']);
        $this->assertSame('uncontrolled', $issue['control_state']);
        $this->assertNull($issue['execution_surface']);
        $this->assertFalse($issue['fleet_managed']);
    }

    public function test_issues_endpoint_can_mark_allowlisted_repository_controlled_property_as_fleet_managed(): void
    {
        config()->set('services.domain_monitor.brain_api_key', 'test-api-key');
        config()->set('domain_monitor.fleet_focus.repository_controlled_domains', [
            'transportnondrivablecars.com.au',
        ]);

        $domain = Domain::factory()->create([
            'domain' => 'transportnondrivablecars.com.au',
            'expires_at' => null,
            'is_active' => true,
            'platform' => 'Custom PHP',
            'hosting_provider' => 'Synergy Wholesale PTY',
        ]);

        $property = WebProperty::factory()->create([
            'slug' => 'transportnondrivablecars-com-au',
            'name' => 'transportnondrivablecars.com.au',
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

        PropertyRepository::create([
            'web_property_id' => $property->id,
            'repo_name' => 'transportnondrivablecars-com-au-php',
            'repo_provider' => 'github',
            'repo_url' => 'https://github.com/iamjasonhill/transportnondrivablecars-com-au-php',
            'local_path' => '/Users/jasonhill/Projects/websites/transportnondrivablecars-com-au-php',
            'framework' => 'Custom PHP',
            'is_primary' => true,
            'is_controller' => true,
        ]);

        DomainSeoBaseline::create([
            'domain_id' => $domain->id,
            'web_property_id' => $property->id,
            'baseline_type' => 'search_console',
            'source_provider' => 'matomo',
            'captured_at' => now(),
            'matomo_site_id' => '2',
            'search_console_property_uri' => 'sc-domain:transportnondrivablecars.com.au',
            'search_type' => 'web',
            'date_range_start' => now()->subDays(28)->toDateString(),
            'date_range_end' => now()->toDateString(),
            'import_method' => 'matomo_api',
            'clicks' => 10,
            'impressions' => 100,
            'ctr' => 0.1,
            'average_position' => 12.3,
            'indexed_pages' => 20,
            'not_indexed_pages' => 5,
            'pages_with_redirect' => 3,
            'raw_payload' => ['issues' => [['label' => 'Page with redirect', 'count' => 3]]],
        ]);

        /** @var array<int, array<string, mixed>> $issues */
        $issues = $this->withHeaders(['Authorization' => 'Bearer test-api-key'])
            ->getJson('/api/issues')
            ->assertOk()
            ->json('issues');

        $issue = collect($issues)->firstWhere('property_slug', 'transportnondrivablecars-com-au');

        $this->assertNotNull($issue);
        $this->assertSame('controlled', $issue['control_state']);
        $this->assertSame('repository_controlled', $issue['execution_surface']);
        $this->assertTrue($issue['fleet_managed']);
        $this->assertSame('transportnondrivablecars-com-au-php', $issue['controller_repo']);
    }
}
