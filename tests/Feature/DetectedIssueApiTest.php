<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\DomainCheck;
use App\Models\DomainSeoBaseline;
use App\Models\PropertyRepository;
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
            'raw_payload' => ['issues' => [['label' => 'Page with redirect', 'count' => 7]]],
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
            'repo_name' => 'headers-issue-site',
            'repo_provider' => 'local_only',
            'local_path' => '/Users/jasonhill/Projects/websites/headers-issue-site',
            'framework' => 'Astro',
            'is_primary' => true,
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
            ->assertJsonPath('contract_version', 1);

        $issueClassCounts = $response->json('stats.issue_class_counts');
        $this->assertSame(1, $issueClassCounts['page_with_redirect_in_sitemap'] ?? null);
        $this->assertSame(1, $issueClassCounts['security.headers_baseline'] ?? null);
        $this->assertGreaterThanOrEqual(2, (int) $response->json('stats.open'));

        /** @var array<int, array<string, mixed>> $payloadIssues */
        $payloadIssues = $response->json('issues');
        $issues = collect($payloadIssues);

        $redirectIssue = $issues->firstWhere('property_slug', 'redirect-issue-site');
        $headersIssue = $issues->firstWhere('property_slug', 'headers-issue-site');

        $this->assertNotNull($redirectIssue);
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
        $this->assertSame(['Search Console reports page with redirect (7 URLs)'], $redirectIssue['evidence']['primary_reasons']);
        $this->assertSame('security.headers_baseline', $headersIssue['issue_class']);
        $this->assertSame('controlled', $headersIssue['control_state']);
        $this->assertSame('astro_repo_controlled', $headersIssue['execution_surface']);
        $this->assertTrue($headersIssue['fleet_managed']);
        $this->assertSame('headers-issue-site', $headersIssue['controller_repo']);

        $detailResponse = $this->withHeaders([
            'Authorization' => 'Bearer test-api-key',
        ])->getJson('/api/issues/'.urlencode($redirectIssue['issue_id']));

        $detailResponse
            ->assertOk()
            ->assertJsonPath('issue_id', $redirectIssue['issue_id'])
            ->assertJsonPath('issue_class', 'page_with_redirect_in_sitemap')
            ->assertJsonPath('evidence.source_domain_id', $redirectDomain->id);
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
    }
}
