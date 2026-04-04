<?php

namespace Tests\Feature;

use App\Models\DetectedIssueVerification;
use App\Models\Domain;
use App\Models\DomainCheck;
use App\Models\DomainSeoBaseline;
use App\Models\PropertyRepository;
use App\Models\SearchConsoleIssueSnapshot;
use App\Models\WebProperty;
use App\Models\WebPropertyDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DetectedIssueVerificationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_mark_an_issue_as_verified_fixed_pending_recrawl_and_hide_it_from_active_feeds(): void
    {
        config()->set('services.domain_monitor.brain_api_key', 'test-api-key');
        config()->set('services.domain_monitor.fleet_control_api_key', 'fleet-token');

        $property = $this->makeSearchConsoleProperty('pending-recrawl.example.com', now()->subHour(), 3);

        /** @var array<int, array<string, mixed>> $issues */
        $issues = $this->withHeaders([
            'Authorization' => 'Bearer test-api-key',
        ])->getJson('/api/issues')
            ->assertOk()
            ->json('issues');

        $issue = collect($issues)->firstWhere('property_slug', $property->slug);

        $this->assertNotNull($issue);

        $this->withHeaders([
            'Authorization' => 'Bearer fleet-token',
        ])->postJson('/api/issues/'.urlencode($issue['issue_id']).'/verification', [
            'status' => 'verified_fixed_pending_recrawl',
            'verification_notes' => [
                'Live redirect now resolves correctly',
                'Legacy URL no longer present in sitemap',
            ],
        ])->assertCreated()
            ->assertJsonPath('issue_id', $issue['issue_id'])
            ->assertJsonPath('status', 'verified_fixed_pending_recrawl')
            ->assertJsonPath('verification_source', 'fleet-control')
            ->assertJsonPath('verification_notes.0', 'Live redirect now resolves correctly');

        $this->assertDatabaseCount('detected_issue_verifications', 1);

        $issuesAfterVerification = $this->withHeaders([
            'Authorization' => 'Bearer test-api-key',
        ])->getJson('/api/issues')
            ->assertOk()
            ->json('issues');

        $this->assertFalse(collect($issuesAfterVerification)->contains(
            fn (array $candidateIssue): bool => $candidateIssue['issue_id'] === $issue['issue_id']
        ));

        $queueResponse = $this->withHeaders([
            'Authorization' => 'Bearer test-api-key',
        ])->getJson('/api/dashboard/priority-queue')
            ->assertOk();

        /** @var array<int, array<string, mixed>> $mustFixPayload */
        $mustFixPayload = $queueResponse->json('must_fix') ?? [];
        /** @var array<int, array<string, mixed>> $shouldFixPayload */
        $shouldFixPayload = $queueResponse->json('should_fix') ?? [];

        $this->assertFalse(collect($mustFixPayload)->contains(
            fn (array $queueItem): bool => ($queueItem['domain'] ?? null) === 'pending-recrawl.example.com'
        ));
        $this->assertFalse(collect($shouldFixPayload)->contains(
            fn (array $queueItem): bool => ($queueItem['domain'] ?? null) === 'pending-recrawl.example.com'
        ));

        $this->withHeaders([
            'Authorization' => 'Bearer test-api-key',
        ])->getJson('/api/issues/'.urlencode($issue['issue_id']))
            ->assertOk()
            ->assertJsonPath('issue_id', $issue['issue_id'])
            ->assertJsonPath('status', 'verified_fixed_pending_recrawl')
            ->assertJsonPath('verification.verification_source', 'fleet-control');
    }

    public function test_suppressed_broken_link_issue_detail_keeps_source_page_evidence(): void
    {
        config()->set('services.domain_monitor.brain_api_key', 'test-api-key');
        config()->set('services.domain_monitor.fleet_control_api_key', 'fleet-token');

        $domain = Domain::factory()->create([
            'domain' => 'suppressed-broken-links.example.com',
            'expires_at' => null,
            'is_active' => true,
            'platform' => 'Astro',
            'hosting_provider' => 'Vercel',
        ]);

        $property = WebProperty::factory()->create([
            'slug' => 'suppressed-broken-links-site',
            'name' => 'Suppressed Broken Links Site',
            'property_type' => 'marketing_site',
            'status' => 'active',
            'primary_domain_id' => $domain->id,
            'target_moveroo_subdomain_url' => 'https://suppressed-broken-links.moveroo.com.au',
            'target_contact_us_page_url' => 'https://suppressed-broken-links.example.com/contact-us',
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $domain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        PropertyRepository::create([
            'web_property_id' => $property->id,
            'repo_name' => 'moveroo/suppressed-broken-links-site',
            'repo_provider' => 'github',
            'repo_url' => 'https://github.com/moveroo/suppressed-broken-links-site',
            'local_path' => '/Users/jasonhill/Projects/websites/suppressed-broken-links-site',
            'framework' => 'Astro',
            'is_primary' => true,
            'is_controller' => true,
            'deployment_provider' => 'vercel',
            'deployment_project_name' => 'suppressed-broken-links-site',
            'deployment_project_id' => 'prj_suppressedbrokenlinks123',
        ]);

        DomainCheck::factory()->create([
            'domain_id' => $domain->id,
            'check_type' => 'broken_links',
            'status' => 'warn',
            'payload' => [
                'broken_links_count' => 1,
                'pages_scanned' => 3,
                'broken_links' => [
                    [
                        'url' => 'https://suppressed-broken-links.example.com/missing/?token=abc',
                        'status' => 404,
                        'found_on' => 'https://suppressed-broken-links.example.com/start-here/?preview=1',
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

        $issue = collect($issues)->firstWhere('issue_class', 'seo.broken_links');

        $this->assertNotNull($issue);

        $this->withHeaders([
            'Authorization' => 'Bearer fleet-token',
        ])->postJson('/api/issues/'.urlencode($issue['issue_id']).'/verification', [
            'status' => 'verified_fixed_pending_recrawl',
            'verification_notes' => ['Verified and awaiting recrawl'],
        ])->assertCreated();

        $this->withHeaders([
            'Authorization' => 'Bearer test-api-key',
        ])->getJson('/api/issues')
            ->assertOk()
            ->assertJsonCount(0, 'issues');

        $this->withHeaders([
            'Authorization' => 'Bearer test-api-key',
        ])->getJson('/api/issues/'.urlencode($issue['issue_id']))
            ->assertOk()
            ->assertJsonPath('status', 'verified_fixed_pending_recrawl')
            ->assertJsonPath('conversion_links.target.moveroo_subdomain', 'https://suppressed-broken-links.moveroo.com.au')
            ->assertJsonPath('conversion_links.target.contact_us_page', 'https://suppressed-broken-links.example.com/contact-us')
            ->assertJsonPath('evidence.broken_links.0.url', 'https://suppressed-broken-links.example.com/missing/')
            ->assertJsonPath('evidence.broken_links.0.found_on', 'https://suppressed-broken-links.example.com/start-here/');
    }

    public function test_verified_pending_recrawl_issue_resurfaces_when_newer_search_console_capture_exists(): void
    {
        config()->set('services.domain_monitor.brain_api_key', 'test-api-key');

        $property = $this->makeSearchConsoleProperty('recrawl-fresh.example.com', now(), 2);

        /** @var array<int, array<string, mixed>> $issues */
        $issues = $this->withHeaders([
            'Authorization' => 'Bearer test-api-key',
        ])->getJson('/api/issues')
            ->assertOk()
            ->json('issues');

        $issue = collect($issues)->firstWhere('property_slug', $property->slug);

        $this->assertNotNull($issue);

        DetectedIssueVerification::create([
            'issue_id' => $issue['issue_id'],
            'property_slug' => $property->slug,
            'domain' => $property->primaryDomainName(),
            'issue_class' => 'page_with_redirect_in_sitemap',
            'status' => 'verified_fixed_pending_recrawl',
            'hidden_until' => now()->addDays(14),
            'verification_source' => 'fleet-control',
            'verification_notes' => ['Waiting for recrawl'],
            'verified_at' => now()->subDay(),
        ]);

        $issuesAfterVerification = $this->withHeaders([
            'Authorization' => 'Bearer test-api-key',
        ])->getJson('/api/issues')
            ->assertOk()
            ->json('issues');

        /** @var array<string, mixed>|null $resurfacedIssue */
        $resurfacedIssue = collect($issuesAfterVerification)->firstWhere('issue_id', $issue['issue_id']);

        $this->assertIsArray($resurfacedIssue);
        $this->assertSame('open', $resurfacedIssue['status']);
        $this->assertIsArray($resurfacedIssue['verification'] ?? null);

        $this->withHeaders([
            'Authorization' => 'Bearer test-api-key',
        ])->getJson('/api/dashboard/priority-queue')
            ->assertOk()
            ->assertJsonPath('stats.must_fix', 1)
            ->assertJsonPath('must_fix.0.domain', 'recrawl-fresh.example.com');
    }

    public function test_verification_writeback_requires_fleet_control_authentication(): void
    {
        config()->set('services.domain_monitor.brain_api_key', 'test-api-key');
        config()->set('services.domain_monitor.fleet_control_api_key', 'fleet-token');

        $property = $this->makeSearchConsoleProperty('fleet-auth.example.com', now()->subHour(), 1);
        /** @var array<int, array<string, mixed>> $issues */
        $issues = $this->withHeaders([
            'Authorization' => 'Bearer test-api-key',
        ])->getJson('/api/issues')
            ->assertOk()
            ->json('issues');
        $issue = collect($issues)->firstWhere('property_slug', $property->slug);

        $this->assertNotNull($issue);

        $this->withHeaders([
            'Authorization' => 'Bearer test-api-key',
        ])->postJson('/api/issues/'.urlencode($issue['issue_id']).'/verification', [
            'status' => 'verified_fixed_pending_recrawl',
        ])->assertForbidden();
    }

    public function test_hidden_until_cannot_exceed_fourteen_day_recrawl_window(): void
    {
        config()->set('services.domain_monitor.brain_api_key', 'test-api-key');
        config()->set('services.domain_monitor.fleet_control_api_key', 'fleet-token');

        $property = $this->makeSearchConsoleProperty('grace-window.example.com', now()->subHour(), 1);
        /** @var array<int, array<string, mixed>> $issues */
        $issues = $this->withHeaders([
            'Authorization' => 'Bearer test-api-key',
        ])->getJson('/api/issues')
            ->assertOk()
            ->json('issues');
        $issue = collect($issues)->firstWhere('property_slug', $property->slug);

        $this->assertNotNull($issue);

        $this->withHeaders([
            'Authorization' => 'Bearer fleet-token',
        ])->postJson('/api/issues/'.urlencode($issue['issue_id']).'/verification', [
            'status' => 'verified_fixed_pending_recrawl',
            'hidden_until' => now()->addDays(21)->toIso8601String(),
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['hidden_until']);
    }

    public function test_verified_primary_issue_downgrades_queue_item_to_should_fix_when_secondary_reason_remains(): void
    {
        config()->set('services.domain_monitor.brain_api_key', 'test-api-key');
        config()->set('services.domain_monitor.fleet_control_api_key', 'fleet-token');

        $property = $this->makeSearchConsoleProperty('downgrade-queue.example.com', now()->subHour(), 2);

        DomainCheck::factory()->create([
            'domain_id' => $property->primary_domain_id,
            'check_type' => 'broken_links',
            'status' => 'warn',
        ]);

        /** @var array<int, array<string, mixed>> $issues */
        $issues = $this->withHeaders([
            'Authorization' => 'Bearer test-api-key',
        ])->getJson('/api/issues')
            ->assertOk()
            ->json('issues');
        $issue = collect($issues)->firstWhere('property_slug', $property->slug);

        $this->assertNotNull($issue);

        $this->withHeaders([
            'Authorization' => 'Bearer fleet-token',
        ])->postJson('/api/issues/'.urlencode($issue['issue_id']).'/verification', [
            'status' => 'verified_fixed_pending_recrawl',
        ])->assertCreated();

        $queueResponse = $this->withHeaders([
            'Authorization' => 'Bearer test-api-key',
        ])->getJson('/api/dashboard/priority-queue')
            ->assertOk();

        /** @var array<int, array<string, mixed>> $shouldFixPayload */
        $shouldFixPayload = $queueResponse->json('should_fix') ?? [];
        /** @var array<string, mixed>|null $queueItem */
        $queueItem = collect($shouldFixPayload)->firstWhere('domain', 'downgrade-queue.example.com');

        $this->assertIsArray($queueItem);
        $this->assertSame(0, $queueItem['primary_reason_count']);
        $this->assertGreaterThanOrEqual(1, $queueItem['secondary_reason_count']);
        $this->assertContains('Broken links need review', $queueItem['secondary_reasons']);
    }

    public function test_dashboard_priority_queue_hides_suppressed_search_console_reasons_using_issue_capture_timestamps(): void
    {
        config()->set('services.domain_monitor.brain_api_key', 'test-api-key');

        $property = $this->makeSearchConsoleProperty('queue-sync.example.com', now(), 6);

        DomainSeoBaseline::query()
            ->where('web_property_id', $property->id)
            ->update([
                'blocked_by_robots' => 1,
                'raw_payload' => [
                    'issues' => [
                        ['label' => 'Page with redirect', 'count' => 6],
                        ['label' => 'Blocked by robots.txt', 'count' => 1],
                    ],
                ],
            ]);

        DomainCheck::factory()->create([
            'domain_id' => $property->primary_domain_id,
            'check_type' => 'security_headers',
            'status' => 'warn',
        ]);

        SearchConsoleIssueSnapshot::factory()->create([
            'domain_id' => $property->primary_domain_id,
            'web_property_id' => $property->id,
            'issue_class' => 'page_with_redirect_in_sitemap',
            'source_issue_label' => 'Page with redirect',
            'capture_method' => 'gsc_drilldown_zip',
            'source_report' => 'search_console_page_indexing_drilldown',
            'source_property' => 'sc-domain:queue-sync.example.com',
            'captured_at' => now()->subDays(2),
            'captured_by' => 'test',
            'affected_url_count' => 6,
            'sample_urls' => [
                'https://queue-sync.example.com/',
            ],
            'examples' => [
                ['url' => 'https://queue-sync.example.com/', 'last_crawled' => now()->subDays(3)->toDateString()],
            ],
            'normalized_payload' => [
                'affected_urls' => [
                    'https://queue-sync.example.com/',
                ],
            ],
            'raw_payload' => ['source' => 'drilldown'],
        ]);

        SearchConsoleIssueSnapshot::factory()->create([
            'domain_id' => $property->primary_domain_id,
            'web_property_id' => $property->id,
            'issue_class' => 'blocked_by_robots_in_indexing',
            'source_issue_label' => 'Blocked by robots.txt',
            'capture_method' => 'gsc_drilldown_zip',
            'source_report' => 'search_console_page_indexing_drilldown',
            'source_property' => 'sc-domain:queue-sync.example.com',
            'captured_at' => now(),
            'captured_by' => 'test',
            'affected_url_count' => 1,
            'sample_urls' => [
                'https://queue-sync.example.com/wp-admin/',
            ],
            'examples' => [
                ['url' => 'https://queue-sync.example.com/wp-admin/', 'last_crawled' => now()->toDateString()],
            ],
            'normalized_payload' => [
                'affected_urls' => [
                    'https://queue-sync.example.com/wp-admin/',
                ],
            ],
            'raw_payload' => ['source' => 'drilldown'],
        ]);

        $issueId = app(\App\Services\DetectedIssueIdentityService::class)
            ->makeIssueId($property->primary_domain_id, $property->slug, 'page_with_redirect_in_sitemap');

        DetectedIssueVerification::create([
            'issue_id' => $issueId,
            'property_slug' => $property->slug,
            'domain' => $property->primaryDomainName(),
            'issue_class' => 'page_with_redirect_in_sitemap',
            'status' => 'verified_fixed_pending_recrawl',
            'hidden_until' => now()->addDays(14),
            'verification_source' => 'fleet-control',
            'verification_notes' => ['Verified live'],
            'verified_at' => now()->subDay(),
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer test-api-key',
        ])->getJson('/api/dashboard/priority-queue')
            ->assertOk();

        /** @var array<int, array<string, mixed>> $mustFixPayload */
        $mustFixPayload = $response->json('must_fix') ?? [];
        /** @var array<string, mixed>|null $queueItem */
        $queueItem = collect($mustFixPayload)
            ->first(fn (array $item): bool => ($item['domain'] ?? null) === 'queue-sync.example.com');

        $this->assertIsArray($queueItem);
        $this->assertSame(['Search Console reports blocked by robots.txt (1 URLs)'], $queueItem['primary_reasons']);
        $this->assertContains('Security headers need review', $queueItem['secondary_reasons']);
    }

    public function test_suppressed_supplemental_issue_is_hidden_from_collection_feed_but_still_available_by_issue_id(): void
    {
        config()->set('services.domain_monitor.brain_api_key', 'test-api-key');
        config()->set('services.domain_monitor.fleet_control_api_key', 'fleet-token');

        $property = $this->makeSearchConsoleProperty('supplemental-hide.example.com', now()->subHour(), 0);

        DomainCheck::factory()->create([
            'domain_id' => $property->primary_domain_id,
            'check_type' => 'broken_links',
            'status' => 'warn',
        ]);

        SearchConsoleIssueSnapshot::factory()->create([
            'domain_id' => $property->primary_domain_id,
            'web_property_id' => $property->id,
            'issue_class' => 'google_chose_different_canonical',
            'source_issue_label' => 'Google chose different canonical than user',
            'capture_method' => 'gsc_drilldown_zip',
            'source_report' => 'search_console_page_indexing_drilldown',
            'source_property' => 'sc-domain:supplemental-hide.example.com',
            'captured_at' => now(),
            'captured_by' => 'test',
            'affected_url_count' => 2,
            'sample_urls' => [
                'https://supplemental-hide.example.com/example-page/',
            ],
            'examples' => [
                ['url' => 'https://supplemental-hide.example.com/example-page/', 'last_crawled' => now()->subDay()->toDateString()],
            ],
            'normalized_payload' => [
                'affected_urls' => [
                    'https://supplemental-hide.example.com/example-page/',
                ],
            ],
            'raw_payload' => ['source' => 'drilldown'],
        ]);

        /** @var array<int, array<string, mixed>> $issues */
        $issues = $this->withHeaders([
            'Authorization' => 'Bearer test-api-key',
        ])->getJson('/api/issues')
            ->assertOk()
            ->json('issues');

        $supplementalIssue = collect($issues)->firstWhere('issue_class', 'google_chose_different_canonical');

        $this->assertNotNull($supplementalIssue);
        $this->assertSame($property->slug, $supplementalIssue['property_slug']);

        $this->withHeaders([
            'Authorization' => 'Bearer fleet-token',
        ])->postJson('/api/issues/'.urlencode($supplementalIssue['issue_id']).'/verification', [
            'status' => 'verified_fixed_pending_recrawl',
            'verification_notes' => [
                'Fleet verified live canonical behavior is fixed',
            ],
        ])->assertCreated();

        /** @var array<int, array<string, mixed>> $issuesAfterVerification */
        $issuesAfterVerification = $this->withHeaders([
            'Authorization' => 'Bearer test-api-key',
        ])->getJson('/api/issues')
            ->assertOk()
            ->json('issues');

        $this->assertFalse(collect($issuesAfterVerification)->contains(
            fn (array $issue): bool => $issue['issue_id'] === $supplementalIssue['issue_id']
        ));

        $this->withHeaders([
            'Authorization' => 'Bearer test-api-key',
        ])->getJson('/api/issues/'.urlencode($supplementalIssue['issue_id']))
            ->assertOk()
            ->assertJsonPath('issue_id', $supplementalIssue['issue_id'])
            ->assertJsonPath('status', 'verified_fixed_pending_recrawl')
            ->assertJsonPath('verification.is_currently_suppressed', true);
    }

    private function makeSearchConsoleProperty(string $domainName, \Illuminate\Support\Carbon $capturedAt, int $pagesWithRedirect): WebProperty
    {
        $domain = Domain::factory()->create([
            'domain' => $domainName,
            'expires_at' => null,
            'is_active' => true,
            'platform' => 'WordPress',
            'hosting_provider' => 'Synergy Wholesale PTY',
        ]);

        $property = WebProperty::factory()->create([
            'slug' => str($domainName)->replace('.', '-')->toString(),
            'name' => $domainName,
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
            'local_path' => '/Users/jasonhill/Projects/websites/_wp-house',
            'framework' => 'WordPress',
            'is_primary' => true,
            'is_controller' => true,
        ]);

        DomainSeoBaseline::create([
            'domain_id' => $domain->id,
            'web_property_id' => $property->id,
            'baseline_type' => 'search_console',
            'captured_at' => $capturedAt,
            'source_provider' => 'matomo',
            'matomo_site_id' => '999',
            'search_console_property_uri' => 'sc-domain:'.$domainName,
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
            'pages_with_redirect' => $pagesWithRedirect,
            'raw_payload' => ['issues' => [['label' => 'Page with redirect', 'count' => $pagesWithRedirect]]],
        ]);

        return $property;
    }
}
