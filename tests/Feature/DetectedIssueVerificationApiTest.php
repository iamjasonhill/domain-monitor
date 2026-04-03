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

        $this->assertCount(0, $issuesAfterVerification);

        $this->withHeaders([
            'Authorization' => 'Bearer test-api-key',
        ])->getJson('/api/dashboard/priority-queue')
            ->assertOk()
            ->assertJsonPath('stats.must_fix', 0)
            ->assertJsonPath('stats.should_fix', 0);

        $this->withHeaders([
            'Authorization' => 'Bearer test-api-key',
        ])->getJson('/api/issues/'.urlencode($issue['issue_id']))
            ->assertOk()
            ->assertJsonPath('issue_id', $issue['issue_id'])
            ->assertJsonPath('status', 'verified_fixed_pending_recrawl')
            ->assertJsonPath('verification.verification_source', 'fleet-control');
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

        $this->assertCount(1, $issuesAfterVerification);
        $this->assertSame($issue['issue_id'], $issuesAfterVerification[0]['issue_id']);
        $this->assertSame('open', $issuesAfterVerification[0]['status']);
        $this->assertIsArray($issuesAfterVerification[0]['verification'] ?? null);

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

        $this->withHeaders([
            'Authorization' => 'Bearer test-api-key',
        ])->getJson('/api/dashboard/priority-queue')
            ->assertOk()
            ->assertJsonPath('stats.must_fix', 0)
            ->assertJsonPath('stats.should_fix', 1)
            ->assertJsonPath('should_fix.0.domain', 'downgrade-queue.example.com')
            ->assertJsonPath('should_fix.0.primary_reason_count', 0)
            ->assertJsonPath('should_fix.0.secondary_reason_count', 1)
            ->assertJsonPath('should_fix.0.secondary_reasons.0', 'Broken links need review');
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
