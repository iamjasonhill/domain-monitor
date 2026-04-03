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
            ->assertJsonPath('stats.open', 5)
            ->assertJsonPath('stats.must_fix', 2)
            ->assertJsonPath('stats.should_fix', 3);

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
            ->assertJsonPath('evidence.source_domain_id', $redirectDomain->id)
            ->assertJsonPath('evidence.examples.0.url', 'https://redirect-issue.example.com/');
    }

    public function test_issues_endpoint_emits_one_issue_per_issue_family_for_one_property(): void
    {
        config()->set('services.domain_monitor.brain_api_key', 'test-api-key');

        $domain = Domain::factory()->create([
            'domain' => 'multi-issue.example.com',
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

        $response
            ->assertOk()
            ->assertJsonPath('stats.open', 2);

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

    public function test_issues_endpoint_reports_uncontrolled_when_no_controller_path_exists(): void
    {
        config()->set('services.domain_monitor.brain_api_key', 'test-api-key');

        $domain = Domain::factory()->create([
            'domain' => 'uncontrolled.example.com',
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
