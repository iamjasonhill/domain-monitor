<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\FleetTechnicalSeoAuditResult;
use App\Models\FleetTechnicalSeoAuditRun;
use App\Models\MonitoringFinding;
use App\Models\WebProperty;
use App\Models\WebPropertyDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RunFleetTechnicalSeoAuditCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_runs_deterministic_audit_for_one_property_slug(): void
    {
        $property = $this->makeProperty('example-site', 'example.com', [
            'target_household_quote_url' => 'https://example.com/quote',
        ]);
        $this->fakeHealthySite('https://example.com');

        $this->assertSame(0, Artisan::call('monitoring:run-fleet-technical-seo-audit', [
            '--property' => $property->slug,
            '--url-cap' => 10,
        ]));

        $run = FleetTechnicalSeoAuditRun::query()->firstOrFail();

        $this->assertSame($property->id, $run->web_property_id);
        $this->assertSame('operator_requested', $run->trigger_type);
        $this->assertSame('2026-05-16-executable-runtime-contract', $run->catalog_version);
        $this->assertSame(0, $run->summary_counts['fail']);
        $this->assertDatabaseHas('fleet_technical_seo_audit_results', [
            'fleet_technical_seo_audit_run_id' => $run->id,
            'check_id' => 'crawl.http_status_ok',
            'result_status' => FleetTechnicalSeoAuditResult::STATUS_PASS,
            'evidence_confidence' => FleetTechnicalSeoAuditResult::CONFIDENCE_HIGH,
        ]);
    }

    public function test_command_resolves_a_domain_selector_to_one_property(): void
    {
        $property = $this->makeProperty('domain-selected-site', 'domain-selected.example');
        $this->fakeHealthySite('https://domain-selected.example');

        $this->assertSame(0, Artisan::call('monitoring:run-fleet-technical-seo-audit', [
            '--domain' => 'domain-selected.example',
            '--url-cap' => 10,
        ]));

        $this->assertDatabaseHas('fleet_technical_seo_audit_runs', [
            'web_property_id' => $property->id,
            'trigger_type' => 'operator_requested',
        ]);
    }

    public function test_ineligible_property_records_not_applicable_audit_without_findings(): void
    {
        $property = $this->makeProperty('paused-site', 'paused.example', [
            'status' => 'paused',
        ]);

        $this->assertSame(0, Artisan::call('monitoring:run-fleet-technical-seo-audit', [
            '--property' => $property->slug,
        ]));

        $run = FleetTechnicalSeoAuditRun::query()->firstOrFail();

        $this->assertSame(24, $run->summary_counts['not_applicable']);
        $this->assertSame(0, $run->summary_counts['fail']);
        $this->assertDatabaseCount('monitoring_findings', 0);
    }

    public function test_high_confidence_failure_creates_attention_finding_and_links_result(): void
    {
        $property = $this->makeProperty('robots-fail-site', 'robots-fail.example');
        $this->fakeHealthySite('https://robots-fail.example', [
            'robots_status' => 404,
        ]);

        $this->assertSame(0, Artisan::call('monitoring:run-fleet-technical-seo-audit', [
            '--property' => $property->slug,
            '--url-cap' => 10,
        ]));

        $finding = MonitoringFinding::query()
            ->where('finding_type', 'fleet_technical_seo.robots.present_and_fetchable')
            ->firstOrFail();
        $result = FleetTechnicalSeoAuditResult::query()
            ->where('check_id', 'robots.present_and_fetchable')
            ->firstOrFail();

        $this->assertSame(MonitoringFinding::STATUS_OPEN, $finding->status);
        $this->assertSame('fleet_technical_seo_full_audit', $finding->lane);
        $this->assertSame($finding->id, $result->monitoring_finding_id);
        $this->assertSame(FleetTechnicalSeoAuditResult::STATUS_FAIL, $result->result_status);
    }

    public function test_url_cap_records_skipped_urls_without_treating_them_as_passes(): void
    {
        $property = $this->makeProperty('cap-site', 'cap.example');
        $this->fakeHealthySite('https://cap.example', [
            'sitemap_urls' => [
                'https://cap.example/',
                'https://cap.example/about',
                'https://cap.example/services',
            ],
        ]);

        $this->assertSame(0, Artisan::call('monitoring:run-fleet-technical-seo-audit', [
            '--property' => $property->slug,
            '--url-cap' => 1,
        ]));

        $run = FleetTechnicalSeoAuditRun::query()->firstOrFail();
        $result = FleetTechnicalSeoAuditResult::query()
            ->where('check_id', 'sitemap.indexable_urls_consistent')
            ->firstOrFail();

        $this->assertGreaterThan(0, $run->summary_counts['not_checked_due_to_limit']);
        $this->assertNotEmpty($result->evidence['skipped_urls']);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeProperty(string $slug, string $domainName, array $attributes = []): WebProperty
    {
        $domain = Domain::factory()->create([
            'domain' => $domainName,
            'is_active' => true,
        ]);
        $property = WebProperty::factory()->create(array_merge([
            'slug' => $slug,
            'name' => ucfirst(str_replace('-', ' ', $slug)),
            'status' => 'active',
            'property_type' => 'marketing_site',
            'primary_domain_id' => $domain->id,
            'production_url' => 'https://'.$domainName.'/',
            'canonical_origin_scheme' => 'https',
            'canonical_origin_host' => $domainName,
        ], $attributes));

        WebPropertyDomain::query()->create([
            'web_property_id' => $property->id,
            'domain_id' => $domain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        return $property;
    }

    /**
     * @param  array{
     *   robots_status?: int,
     *   sitemap_urls?: array<int, string>
     * }  $options
     */
    private function fakeHealthySite(string $baseUrl, array $options = []): void
    {
        $robotsStatus = (int) ($options['robots_status'] ?? 200);
        $sitemapUrls = $options['sitemap_urls'] ?? [
            $baseUrl.'/',
            $baseUrl.'/about',
        ];
        $sitemapXml = '<urlset>'.collect($sitemapUrls)
            ->map(fn (string $url): string => '<url><loc>'.$url.'</loc></url>')
            ->implode('').'</urlset>';
        $html = $this->healthyHtml($baseUrl);

        Http::fake([
            $baseUrl.'/' => Http::response($html, 200, ['Content-Type' => 'text/html']),
            $baseUrl.'/robots.txt' => Http::response("User-agent: *\nAllow: /\nSitemap: {$baseUrl}/sitemap.xml\n", $robotsStatus),
            $baseUrl.'/sitemap.xml' => Http::response($sitemapXml, 200, ['Content-Type' => 'application/xml']),
            $baseUrl.'/sitemap_index.xml' => Http::response('', 404),
            $baseUrl.'/llms.txt' => Http::response('# '.$baseUrl, 200, ['Content-Type' => 'text/plain']),
            $baseUrl.'/about' => Http::response($html, 200, ['Content-Type' => 'text/html']),
            $baseUrl.'/services' => Http::response($html, 200, ['Content-Type' => 'text/html']),
            $baseUrl.'/quote' => Http::response($html, 200, ['Content-Type' => 'text/html']),
        ]);
    }

    private function healthyHtml(string $baseUrl): string
    {
        return <<<HTML
            <!doctype html>
            <html lang="en">
            <head>
                <title>Example Site</title>
                <meta name="description" content="A useful example site.">
                <meta property="og:title" content="Example Site">
                <meta property="og:description" content="A useful example site.">
                <meta property="og:image" content="{$baseUrl}/og.jpg">
                <link rel="canonical" href="{$baseUrl}/">
                <script type="application/ld+json">{"@context":"https://schema.org","@type":"Organization","name":"Example"}</script>
            </head>
            <body>
                <h1>Example Site</h1>
                <a href="{$baseUrl}/about">About</a>
                <a href="{$baseUrl}/quote">Quote</a>
                <img src="{$baseUrl}/logo.png" alt="Example logo" width="120" height="60">
            </body>
            </html>
        HTML;
    }
}
