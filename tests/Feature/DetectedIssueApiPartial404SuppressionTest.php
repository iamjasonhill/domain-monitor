<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\DomainSeoBaseline;
use App\Models\PropertyRepository;
use App\Models\SearchConsoleIssueSnapshot;
use App\Models\WebProperty;
use App\Models\WebPropertyDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DetectedIssueApiPartial404SuppressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_issues_endpoint_suppresses_partial_stale_404_rows_with_404_specific_exclusion_metadata(): void
    {
        config()->set('services.domain_monitor.brain_api_key', 'test-api-key');

        $domain = Domain::factory()->create([
            'domain' => 'partial-stale-404s.example.com',
            'expires_at' => null,
            'is_active' => true,
            'platform' => 'WordPress',
            'hosting_provider' => 'DreamIT Host',
        ]);

        $property = WebProperty::factory()->create([
            'slug' => 'partial-stale-404s-site',
            'name' => 'Partial Stale 404s Site',
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
            'repo_name' => 'partial-stale-404s-site',
            'repo_provider' => 'local_only',
            'local_path' => '/Users/jasonhill/Projects/Business/websites/partial-stale-404s-site',
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
            'matomo_site_id' => '406',
            'search_console_property_uri' => 'sc-domain:partial-stale-404s.example.com',
            'search_type' => 'web',
            'date_range_start' => now()->subDays(28)->toDateString(),
            'date_range_end' => now()->toDateString(),
            'import_method' => 'matomo_api',
            'not_found_404' => 19,
            'raw_payload' => [
                'issues' => [
                    ['label' => 'Not found (404)', 'count' => 19],
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
            'source_property' => 'sc-domain:partial-stale-404s.example.com',
            'captured_at' => now()->subMinute(),
            'captured_by' => 'test',
            'affected_url_count' => 3,
            'sample_urls' => [
                'https://partial-stale-404s.example.com/old-page/',
                'https://partial-stale-404s.example.com/author/admin/',
                'https://partial-stale-404s.example.com/legacy/payments',
            ],
            'examples' => [
                ['url' => 'https://partial-stale-404s.example.com/old-page/', 'last_crawled' => now()->subDays(2)->toDateString()],
                ['url' => 'https://partial-stale-404s.example.com/author/admin/', 'last_crawled' => now()->subDays(2)->toDateString()],
                ['url' => 'https://partial-stale-404s.example.com/legacy/payments', 'last_crawled' => now()->subDays(2)->toDateString()],
            ],
            'normalized_payload' => [
                'affected_urls' => [
                    'https://partial-stale-404s.example.com/old-page/',
                    'https://partial-stale-404s.example.com/author/admin/',
                    'https://partial-stale-404s.example.com/legacy/payments',
                ],
            ],
            'raw_payload' => ['source' => 'drilldown'],
        ]);

        SearchConsoleIssueSnapshot::factory()->create([
            'domain_id' => $domain->id,
            'web_property_id' => $property->id,
            'issue_class' => 'not_found_404',
            'source_issue_label' => 'Not found (404)',
            'capture_method' => 'gsc_live_recheck',
            'source_report' => 'search_console_live_url_recheck',
            'source_property' => 'sc-domain:partial-stale-404s.example.com',
            'captured_at' => now(),
            'captured_by' => 'test',
            'affected_url_count' => 3,
            'sample_urls' => [
                'https://partial-stale-404s.example.com/old-page/',
                'https://partial-stale-404s.example.com/author/admin/',
                'https://partial-stale-404s.example.com/legacy/payments',
            ],
            'normalized_payload' => [
                'affected_urls' => [
                    'https://partial-stale-404s.example.com/old-page/',
                    'https://partial-stale-404s.example.com/author/admin/',
                    'https://partial-stale-404s.example.com/legacy/payments',
                ],
                'live_url_checks' => [
                    [
                        'url' => 'https://partial-stale-404s.example.com/old-page/',
                        'status_code' => 301,
                        'final_status' => 200,
                        'final_url' => 'https://partial-stale-404s.example.com/',
                    ],
                    [
                        'url' => 'https://partial-stale-404s.example.com/author/admin/',
                        'status_code' => 301,
                        'final_status' => 200,
                        'final_url' => 'https://partial-stale-404s.example.com/',
                    ],
                    [
                        'url' => 'https://partial-stale-404s.example.com/legacy/payments',
                        'status_code' => 302,
                        'final_status' => 200,
                        'final_url' => 'https://partial-stale-404s.example.com/contact',
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
            return ($issue['property_slug'] ?? null) === 'partial-stale-404s-site'
                && ($issue['issue_class'] ?? null) === 'not_found_404';
        }));

        $identity = app(\App\Services\DetectedIssueIdentityService::class);

        $this->withHeaders([
            'Authorization' => 'Bearer test-api-key',
        ])->getJson('/api/issues/'.urlencode($identity->makeIssueId($domain->id, $property->slug, 'not_found_404')))
            ->assertOk()
            ->assertJsonPath('evidence.expected_exclusion.state', 'resolved_or_retired_404')
            ->assertJsonPath('evidence.expected_exclusion.code', 'resolved_or_retired_404');
    }
}
