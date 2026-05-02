<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\WebProperty;
use App\Models\WebPropertyDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LiveSeoVerificationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_live_seo_verification_endpoint_requires_api_authentication(): void
    {
        $this->getJson('/api/web-properties/example/live-seo-verification?url=https://example.com/')
            ->assertUnauthorized();
    }

    public function test_live_seo_verification_endpoint_returns_packet_for_exact_url(): void
    {
        config()->set('services.domain_monitor.brain_api_key', 'test-api-key');

        $property = $this->makeProperty('seo-packet-site', 'SEO Packet Site', 'seo-packet.example.com');

        Http::fake([
            'http://seo-packet.example.com/services/checklist' => Http::response(
                <<<'HTML'
                <html>
                    <head>
                        <title>Services Checklist | SEO Packet Site</title>
                        <link rel="canonical" href="https://seo-packet.example.com/services/checklist" />
                        <meta name="description" content="Live SEO checklist evidence for the packet." />
                        <meta name="robots" content="index,follow" />
                    </head>
                    <body>
                        <a href="/about">About</a>
                        <a href="/missing">Missing</a>
                        <a href="https://broken.example.org/outbound">Broken outbound</a>
                    </body>
                </html>
                HTML,
                200,
                [
                    'Content-Type' => 'text/html; charset=UTF-8',
                    'X-Guzzle-Redirect-History' => ['https://seo-packet.example.com/services/checklist'],
                    'X-Guzzle-Redirect-Status-History' => ['301'],
                    'X-Robots-Tag' => 'all',
                ]
            ),
            'https://seo-packet.example.com/robots.txt' => Http::response(
                "User-agent: *\nAllow: /\nSitemap: https://seo-packet.example.com/sitemap.xml\n",
                200
            ),
            'https://seo-packet.example.com/about' => Http::response('<html><body>About</body></html>', 200, [
                'Content-Type' => 'text/html; charset=UTF-8',
            ]),
            'https://seo-packet.example.com/missing' => Http::response('', 404),
            'https://broken.example.org/outbound' => Http::response('', 404),
        ]);

        $query = http_build_query([
            'measurement_key' => 'mm-google:search-intelligence:moving-again-pilot',
            'evidence_ref' => 'MM-Google/search-intelligence/opportunities/moving-again',
            'site_key' => 'moving-again',
            'target_url' => 'http://seo-packet.example.com/services/checklist',
            'expected_canonical' => 'https://seo-packet.example.com/services/checklist',
            'owning_repo' => 'iamjasonhill/seo-packet-site',
            'reason' => 'Moving Again SEO intelligence pilot URL live-truth check',
            'requested_checks' => 'http,redirects,canonical,robots,indexability,title_meta',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer test-api-key',
        ])->getJson('/api/web-properties/seo-packet-site/live-seo-verification?'.$query);

        $response
            ->assertOk()
            ->assertJsonPath('source_system', 'domain-monitor-live-seo-verification')
            ->assertJsonPath('contract_version', 1)
            ->assertJsonPath('property_slug', 'seo-packet-site')
            ->assertJsonPath('verification_key', 'mm-google:search-intelligence:moving-again-pilot')
            ->assertJsonPath('target.scope', 'exact_url')
            ->assertJsonPath('target.measurement_key', 'mm-google:search-intelligence:moving-again-pilot')
            ->assertJsonPath('target.evidence_ref', 'MM-Google/search-intelligence/opportunities/moving-again')
            ->assertJsonPath('target.site_key', 'moving-again')
            ->assertJsonPath('target.requested_url', 'http://seo-packet.example.com/services/checklist')
            ->assertJsonPath('target.expected_canonical', 'https://seo-packet.example.com/services/checklist')
            ->assertJsonPath('target.owning_repo', 'iamjasonhill/seo-packet-site')
            ->assertJsonPath('target.reason', 'Moving Again SEO intelligence pilot URL live-truth check')
            ->assertJsonPath('target.requested_checks.3', 'robots')
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('verdict', 'passes_live_verification')
            ->assertJsonPath('evidence.page.final_url', 'https://seo-packet.example.com/services/checklist')
            ->assertJsonPath('evidence.page.status_code', 200)
            ->assertJsonPath('evidence.page.redirect_statuses.0', 301)
            ->assertJsonPath('evidence.canonical.url', 'https://seo-packet.example.com/services/checklist')
            ->assertJsonPath('evidence.canonical.matches_expected_origin', true)
            ->assertJsonPath('evidence.canonical.expected_url', 'https://seo-packet.example.com/services/checklist')
            ->assertJsonPath('evidence.canonical.matches_expected_url', true)
            ->assertJsonPath('evidence.basic_meta.title', 'Services Checklist | SEO Packet Site')
            ->assertJsonPath('evidence.basic_meta.meta_description', 'Live SEO checklist evidence for the packet.')
            ->assertJsonPath('evidence.indexability.meta_robots', 'index,follow')
            ->assertJsonPath('evidence.indexability.has_noindex', false)
            ->assertJsonPath('evidence.fetchability.state', 'fetchable')
            ->assertJsonPath('evidence.fetchability.robots_txt.ok', true)
            ->assertJsonPath('evidence.fetchability.robots_txt.sitemap_url', 'https://seo-packet.example.com/sitemap.xml')
            ->assertJsonPath('evidence.links.page_link_count', 3)
            ->assertJsonPath('evidence.links.checked_link_count', 3)
            ->assertJsonPath('evidence.links.broken_links_count', 2)
            ->assertJsonPath('evidence.links.outbound_links_count', 1)
            ->assertJsonPath('evidence.evidence_limits.page_scope', 'single_url_only')
            ->assertJsonPath('evidence.evidence_limits.search_console_included', false);
    }

    public function test_live_seo_verification_endpoint_returns_needs_attention_for_expected_canonical_mismatch(): void
    {
        config()->set('services.domain_monitor.brain_api_key', 'test-api-key');

        $this->makeProperty('mismatch-site', 'Mismatch Site', 'mismatch.example.com');

        Http::fake([
            'https://mismatch.example.com/services/checklist' => Http::response(
                '<html><head><link rel="canonical" href="https://mismatch.example.com/other" /></head><body></body></html>',
                200,
                ['Content-Type' => 'text/html; charset=UTF-8']
            ),
            'https://mismatch.example.com/robots.txt' => Http::response("User-agent: *\nAllow: /\n", 200),
        ]);

        $query = http_build_query([
            'url' => 'https://mismatch.example.com/services/checklist',
            'expected_canonical' => 'https://mismatch.example.com/services/checklist',
        ]);

        $this->withHeaders([
            'Authorization' => 'Bearer test-api-key',
        ])->getJson('/api/web-properties/mismatch-site/live-seo-verification?'.$query)
            ->assertOk()
            ->assertJsonPath('verdict', 'needs_attention')
            ->assertJsonPath('evidence.canonical.matches_expected_url', false);
    }

    public function test_live_seo_verification_endpoint_accepts_url_pattern_with_sample_url(): void
    {
        config()->set('services.domain_monitor.brain_api_key', 'test-api-key');

        $this->makeProperty('pattern-site', 'Pattern Site', 'pattern.example.com');

        Http::fake([
            'https://pattern.example.com/blog/example-post' => Http::response(
                '<html><head><link rel="canonical" href="https://pattern.example.com/blog/example-post" /></head><body></body></html>',
                200,
                ['Content-Type' => 'text/html; charset=UTF-8']
            ),
            'https://pattern.example.com/robots.txt' => Http::response("User-agent: *\nAllow: /\n", 200),
        ]);

        $this->withHeaders([
            'Authorization' => 'Bearer test-api-key',
        ])->getJson('/api/web-properties/pattern-site/live-seo-verification?url_pattern=/blog/*&sample_url=https://pattern.example.com/blog/example-post')
            ->assertOk()
            ->assertJsonPath('target.scope', 'url_pattern')
            ->assertJsonPath('target.url_pattern', '/blog/*')
            ->assertJsonPath('target.sample_url', 'https://pattern.example.com/blog/example-post')
            ->assertJsonPath('evidence.evidence_limits.scope_mode', 'sample_url_only');
    }

    private function makeProperty(string $slug, string $name, string $domainName): WebProperty
    {
        $domain = Domain::factory()->create([
            'domain' => $domainName,
            'is_active' => true,
            'platform' => 'Astro',
        ]);

        $property = WebProperty::factory()->create([
            'slug' => $slug,
            'name' => $name,
            'property_type' => 'marketing_site',
            'status' => 'active',
            'primary_domain_id' => $domain->id,
            'production_url' => 'https://'.$domainName.'/',
            'canonical_origin_scheme' => 'https',
            'canonical_origin_host' => $domainName,
            'canonical_origin_policy' => 'known',
            'canonical_origin_enforcement_eligible' => true,
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $domain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        return $property;
    }
}
