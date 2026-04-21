<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\SearchConsoleIssueSnapshot;
use App\Models\WebProperty;
use App\Models\WebPropertyDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class RefreshSearchConsoleLiveRechecksCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_refreshes_live_sitemap_rechecks_for_eligible_properties(): void
    {
        config()->set('services.domain_monitor.brain_api_key', 'test-api-key');

        $property = $this->makeProperty('discountbackloading-site', 'discountbackloading.example.au');
        $domain = $property->primaryDomainModel();

        SearchConsoleIssueSnapshot::factory()->create([
            'domain_id' => $domain?->id,
            'web_property_id' => $property->id,
            'issue_class' => 'page_with_redirect_in_sitemap',
            'source_issue_label' => 'Page with redirect',
            'capture_method' => 'gsc_drilldown_zip',
            'source_report' => 'search_console_page_indexing_drilldown',
            'source_property' => 'sc-domain:discountbackloading.example.au',
            'captured_at' => now()->subDay(),
            'captured_by' => 'test',
            'affected_url_count' => 2,
            'sample_urls' => [
                'http://www.discountbackloading.example.au/',
                'https://discountbackloading.example.au/author/admin/',
            ],
            'examples' => [
                ['url' => 'http://www.discountbackloading.example.au/', 'last_crawled' => now()->subDays(2)->toDateString()],
                ['url' => 'https://discountbackloading.example.au/author/admin/', 'last_crawled' => now()->subDays(2)->toDateString()],
            ],
            'normalized_payload' => [
                'affected_urls' => [
                    'http://www.discountbackloading.example.au/',
                    'https://discountbackloading.example.au/author/admin/',
                ],
            ],
        ]);

        Http::fake([
            'https://discountbackloading.example.au/sitemap.xml' => Http::response('', 404),
            'https://discountbackloading.example.au/sitemaps.xml' => Http::response(<<<'XML'
                <?xml version="1.0" encoding="UTF-8"?>
                <sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
                    <sitemap><loc>https://discountbackloading.example.au/post-sitemap.xml</loc></sitemap>
                    <sitemap><loc>https://discountbackloading.example.au/page-sitemap.xml</loc></sitemap>
                </sitemapindex>
            XML, 200),
            'https://discountbackloading.example.au/sitemap_index.xml' => Http::response(<<<'XML'
                <?xml version="1.0" encoding="UTF-8"?>
                <sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
                    <sitemap><loc>https://discountbackloading.example.au/post-sitemap.xml</loc></sitemap>
                    <sitemap><loc>https://discountbackloading.example.au/page-sitemap.xml</loc></sitemap>
                </sitemapindex>
            XML, 200),
            'https://discountbackloading.example.au/post-sitemap.xml' => Http::response(<<<'XML'
                <?xml version="1.0" encoding="UTF-8"?>
                <urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
                    <url><loc>https://discountbackloading.example.au/</loc></url>
                </urlset>
            XML, 200),
            'https://discountbackloading.example.au/page-sitemap.xml' => Http::response(<<<'XML'
                <?xml version="1.0" encoding="UTF-8"?>
                <urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
                    <url><loc>https://discountbackloading.example.au/contact/</loc></url>
                </urlset>
            XML, 200),
        ]);

        $exitCode = Artisan::call('analytics:refresh-search-console-live-rechecks');

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Refreshed discountbackloading-site (2 URLs checked)', Artisan::output());

        $liveSnapshot = SearchConsoleIssueSnapshot::query()
            ->where('web_property_id', $property->id)
            ->where('issue_class', 'page_with_redirect_in_sitemap')
            ->where('capture_method', 'gsc_live_recheck')
            ->latest('captured_at')
            ->first();

        $this->assertInstanceOf(SearchConsoleIssueSnapshot::class, $liveSnapshot);
        $this->assertSame(false, data_get($liveSnapshot->issueEvidence(), 'live_sitemap_checks.0.present_in_current_sitemap'));
        $this->assertSame(false, data_get($liveSnapshot->issueEvidence(), 'live_sitemap_checks.1.present_in_current_sitemap'));

        $response = $this->withHeaders([
            'Authorization' => 'Bearer test-api-key',
        ])->getJson('/api/issues');

        $response->assertOk()
            ->assertJsonMissingPath('stats.issue_class_counts.page_with_redirect_in_sitemap');

        /** @var array<int, array<string, mixed>> $issues */
        $issues = $response->json('issues') ?? [];
        $matchingIssue = null;

        foreach ($issues as $issue) {
            if (($issue['property_slug'] ?? null) === 'discountbackloading-site'
                && ($issue['issue_class'] ?? null) === 'page_with_redirect_in_sitemap') {
                $matchingIssue = $issue;
                break;
            }
        }

        $this->assertNull($matchingIssue);
    }

    public function test_it_supports_dry_run_without_hitting_live_sitemaps(): void
    {
        $property = $this->makeProperty('dry-run-live-rechecks-site', 'dry-run-live-rechecks.example.au');
        $domain = $property->primaryDomainModel();

        SearchConsoleIssueSnapshot::factory()->create([
            'domain_id' => $domain?->id,
            'web_property_id' => $property->id,
            'issue_class' => 'page_with_redirect_in_sitemap',
            'capture_method' => 'gsc_drilldown_zip',
            'affected_url_count' => 1,
            'sample_urls' => ['https://dry-run-live-rechecks.example.au/old-page/'],
            'normalized_payload' => [
                'affected_urls' => ['https://dry-run-live-rechecks.example.au/old-page/'],
            ],
        ]);

        Http::fake();

        $exitCode = Artisan::call('analytics:refresh-search-console-live-rechecks', [
            '--dry-run' => true,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Listed 1 of 1 candidate properties', Artisan::output());
        $this->assertSame(0, SearchConsoleIssueSnapshot::query()->where('capture_method', 'gsc_live_recheck')->count());
        Http::assertNothingSent();
    }

    public function test_it_does_not_mark_query_variant_present_when_only_base_url_exists_in_sitemap(): void
    {
        $property = $this->makeProperty('query-variant-live-rechecks-site', 'query-variant.example.au');
        $domain = $property->primaryDomainModel();

        SearchConsoleIssueSnapshot::factory()->create([
            'domain_id' => $domain?->id,
            'web_property_id' => $property->id,
            'issue_class' => 'page_with_redirect_in_sitemap',
            'source_issue_label' => 'Page with redirect',
            'capture_method' => 'gsc_drilldown_zip',
            'source_report' => 'search_console_page_indexing_drilldown',
            'source_property' => 'sc-domain:query-variant.example.au',
            'captured_at' => now()->subDay(),
            'captured_by' => 'test',
            'affected_url_count' => 1,
            'sample_urls' => [
                'https://query-variant.example.au/?elementor_library=default-kit',
            ],
            'normalized_payload' => [
                'affected_urls' => [
                    'https://query-variant.example.au/?elementor_library=default-kit',
                ],
            ],
        ]);

        Http::fake([
            'https://query-variant.example.au/sitemap.xml' => Http::response('', 404),
            'https://query-variant.example.au/sitemaps.xml' => Http::response('', 404),
            'https://query-variant.example.au/sitemap_index.xml' => Http::response(<<<'XML'
                <?xml version="1.0" encoding="UTF-8"?>
                <sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
                    <sitemap><loc>https://query-variant.example.au/page-sitemap.xml</loc></sitemap>
                </sitemapindex>
            XML, 200),
            'https://query-variant.example.au/page-sitemap.xml' => Http::response(<<<'XML'
                <?xml version="1.0" encoding="UTF-8"?>
                <urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
                    <url><loc>https://query-variant.example.au/</loc></url>
                </urlset>
            XML, 200),
        ]);

        $exitCode = Artisan::call('analytics:refresh-search-console-live-rechecks', [
            '--property' => $property->slug,
        ]);

        $this->assertSame(0, $exitCode);

        $liveSnapshot = SearchConsoleIssueSnapshot::query()
            ->where('web_property_id', $property->id)
            ->where('issue_class', 'page_with_redirect_in_sitemap')
            ->where('capture_method', 'gsc_live_recheck')
            ->latest('captured_at')
            ->first();

        $this->assertInstanceOf(SearchConsoleIssueSnapshot::class, $liveSnapshot);
        $this->assertSame(
            false,
            data_get($liveSnapshot->issueEvidence(), 'live_sitemap_checks.0.present_in_current_sitemap')
        );
        $this->assertNull(data_get($liveSnapshot->issueEvidence(), 'live_sitemap_checks.0.matched_sitemap'));
    }

    private function makeProperty(string $slug, string $domainName): WebProperty
    {
        $domain = Domain::factory()->create([
            'domain' => $domainName,
            'is_active' => true,
        ]);

        $property = WebProperty::factory()->create([
            'slug' => $slug,
            'name' => Str::of($slug)->replace('-', ' ')->title()->toString(),
            'status' => 'active',
            'property_type' => 'website',
            'primary_domain_id' => $domain->id,
            'production_url' => 'https://'.$domainName,
            'canonical_origin_scheme' => 'https',
            'canonical_origin_host' => $domainName,
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
