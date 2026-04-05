<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\DomainCheck;
use App\Models\WebProperty;
use App\Models\WebPropertyDomain;
use App\Services\IntentionalSearchConsoleExclusionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntentionalSearchConsoleExclusionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_classifies_wordpress_admin_robots_exclusions_when_evidence_is_complete(): void
    {
        $property = $this->makePropertyWithSeoRobotsRule('service-intentional-admin.example.com');

        $classification = app(IntentionalSearchConsoleExclusionService::class)->classify(
            $property,
            'blocked_by_robots_in_indexing',
            [
                'affected_urls' => ['https://service-intentional-admin.example.com/wp-admin/'],
                'affected_url_count' => 1,
                'exact_example_count' => 1,
                'examples' => [
                    ['url' => 'https://service-intentional-admin.example.com/wp-admin/'],
                ],
            ]
        );

        $this->assertIsArray($classification);
        $this->assertSame('expected_robots_exclusion', $classification['state']);
    }

    public function test_it_does_not_classify_wp_login_as_a_robots_exclusion(): void
    {
        $property = $this->makePropertyWithSeoRobotsRule('service-intentional-login.example.com');

        $classification = app(IntentionalSearchConsoleExclusionService::class)->classify(
            $property,
            'blocked_by_robots_in_indexing',
            [
                'affected_urls' => ['https://service-intentional-login.example.com/wp-login.php?redirect_to=/wp-admin/'],
                'affected_url_count' => 1,
                'exact_example_count' => 1,
                'examples' => [
                    ['url' => 'https://service-intentional-login.example.com/wp-login.php?redirect_to=/wp-admin/'],
                ],
            ]
        );

        $this->assertNull($classification);
    }

    public function test_it_does_not_classify_when_unique_candidate_urls_do_not_cover_the_reported_count(): void
    {
        $property = $this->makePropertyWithSeoRobotsRule('service-incomplete-admin.example.com');

        $classification = app(IntentionalSearchConsoleExclusionService::class)->classify(
            $property,
            'blocked_by_robots_in_indexing',
            [
                'affected_urls' => ['https://service-incomplete-admin.example.com/wp-admin/'],
                'affected_url_count' => 2,
                'exact_example_count' => 1,
                'examples' => [
                    ['url' => 'https://service-incomplete-admin.example.com/wp-admin/'],
                ],
            ]
        );

        $this->assertNull($classification);
    }

    public function test_it_uses_the_latest_seo_check_when_multiple_records_exist(): void
    {
        $domainName = 'service-latest-admin.example.com';
        $domain = Domain::factory()->create([
            'domain' => $domainName,
            'is_active' => true,
            'platform' => 'WordPress',
            'hosting_provider' => 'DreamIT Host',
        ]);

        $property = WebProperty::factory()->create([
            'slug' => str($domainName)->replace('.', '-')->toString(),
            'name' => 'Intentional Search Console Exclusion Test',
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
            'started_at' => now()->subMinutes(10),
            'finished_at' => now()->subMinutes(9),
            'duration_ms' => 100,
            'payload' => [
                'results' => [
                    'robots' => [
                        'url' => sprintf('https://%s/robots.txt', $domainName),
                        'has_standard_wordpress_admin_rule' => false,
                        'allow_admin_ajax' => false,
                    ],
                ],
            ],
            'retry_count' => 0,
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
                        'url' => sprintf('https://%s/robots.txt', $domainName),
                        'has_standard_wordpress_admin_rule' => true,
                        'allow_admin_ajax' => true,
                    ],
                ],
            ],
            'retry_count' => 0,
        ]);

        $property = $property->fresh(['primaryDomain.latestSeoCheck', 'propertyDomains.domain.latestSeoCheck']);

        $classification = app(IntentionalSearchConsoleExclusionService::class)->classify(
            $property,
            'blocked_by_robots_in_indexing',
            [
                'affected_urls' => [sprintf('https://%s/wp-admin/', $domainName)],
                'affected_url_count' => 1,
                'exact_example_count' => 1,
                'examples' => [
                    ['url' => sprintf('https://%s/wp-admin/', $domainName)],
                ],
            ]
        );

        $this->assertIsArray($classification);
        $this->assertSame('expected_robots_exclusion', $classification['state']);
    }

    private function makePropertyWithSeoRobotsRule(string $domainName): WebProperty
    {
        $domain = Domain::factory()->create([
            'domain' => $domainName,
            'is_active' => true,
            'platform' => 'WordPress',
            'hosting_provider' => 'DreamIT Host',
            'expires_at' => null,
        ]);

        $property = WebProperty::factory()->create([
            'slug' => str($domainName)->replace('.', '-')->toString(),
            'name' => 'Intentional Search Console Exclusion Test',
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
                        'url' => sprintf('https://%s/robots.txt', $domainName),
                        'has_standard_wordpress_admin_rule' => true,
                        'allow_admin_ajax' => true,
                    ],
                ],
            ],
            'retry_count' => 0,
        ]);

        return $property->fresh(['primaryDomain.latestSeoCheck', 'propertyDomains.domain.latestSeoCheck']);
    }
}
