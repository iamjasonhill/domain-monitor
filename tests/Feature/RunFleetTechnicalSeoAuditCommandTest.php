<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\FleetTechnicalSeoAuditResult;
use App\Models\FleetTechnicalSeoAuditRun;
use App\Models\MonitoringFinding;
use App\Models\WebProperty;
use App\Models\WebPropertyDomain;
use App\Services\FleetTechnicalSeoBrowserRenderer;
use App\Services\FleetTechnicalSeoLighthouseRunner;
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

        $this->assertSame(29, $run->summary_counts['not_applicable']);
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

    public function test_browser_render_mode_records_bounded_rendered_dom_evidence(): void
    {
        $property = $this->makeProperty('rendered-site', 'rendered.example');
        $this->fakeHealthySite('https://rendered.example');
        $this->app->instance(FleetTechnicalSeoBrowserRenderer::class, new class implements FleetTechnicalSeoBrowserRenderer
        {
            public function render(string $url): array
            {
                return [
                    'available' => true,
                    'url' => $url,
                    'final_url' => $url,
                    'title' => 'Example Site',
                    'text_sample' => 'Example Site About Quote',
                    'body_text_length' => 24,
                    'console_errors' => [],
                    'viewport' => ['width' => 390, 'height' => 844],
                    'content_width' => 390,
                    'h1_count' => 1,
                    'html_lang' => 'en',
                    'main_landmark_count' => 1,
                    'nav_landmark_count' => 1,
                    'link_without_name_count' => 0,
                    'raw_html' => '<html><body>This raw dump must not be stored.</body></html>',
                    'screenshot_path' => '/tmp/raw-render.png',
                ];
            }
        });

        $this->assertSame(0, Artisan::call('monitoring:run-fleet-technical-seo-audit', [
            '--property' => $property->slug,
            '--url-cap' => 10,
        ]));

        $run = FleetTechnicalSeoAuditRun::query()->firstOrFail();
        $mobileResult = FleetTechnicalSeoAuditResult::query()
            ->where('check_id', 'mobile.usability_basic_rendering')
            ->firstOrFail();
        $accessibilityResult = FleetTechnicalSeoAuditResult::query()
            ->where('check_id', 'accessibility.semantic_baseline')
            ->firstOrFail();

        $this->assertContains('browser_render', $run->execution_modes);
        $this->assertSame(FleetTechnicalSeoAuditResult::STATUS_PASS, $mobileResult->result_status);
        $this->assertSame(FleetTechnicalSeoAuditResult::CONFIDENCE_MEDIUM, $mobileResult->evidence_confidence);
        $this->assertSame(390, $mobileResult->evidence['browser_render']['viewport']['width']);
        $this->assertArrayNotHasKey('raw_html', $mobileResult->evidence['browser_render']);
        $this->assertArrayNotHasKey('screenshot_path', $mobileResult->evidence['browser_render']);
        $this->assertSame(FleetTechnicalSeoAuditResult::STATUS_PASS, $accessibilityResult->result_status);
        $this->assertDatabaseCount('monitoring_findings', 0);
    }

    public function test_browser_render_mode_records_review_evidence_without_attention_noise(): void
    {
        $property = $this->makeProperty('broken-render-site', 'broken-render.example');
        $this->fakeHealthySite('https://broken-render.example');
        $this->app->instance(FleetTechnicalSeoBrowserRenderer::class, new class implements FleetTechnicalSeoBrowserRenderer
        {
            public function render(string $url): array
            {
                return [
                    'available' => true,
                    'url' => $url,
                    'final_url' => $url,
                    'title' => 'Not found',
                    'text_sample' => '404 not found',
                    'body_text_length' => 13,
                    'console_errors' => ['ReferenceError: app is not defined'],
                    'viewport' => ['width' => 390, 'height' => 844],
                    'content_width' => 620,
                    'h1_count' => 0,
                    'html_lang' => '',
                    'main_landmark_count' => 0,
                    'nav_landmark_count' => 0,
                    'link_without_name_count' => 2,
                ];
            }
        });

        $this->assertSame(0, Artisan::call('monitoring:run-fleet-technical-seo-audit', [
            '--property' => $property->slug,
            '--url-cap' => 10,
        ]));

        $mobileResult = FleetTechnicalSeoAuditResult::query()
            ->where('check_id', 'mobile.usability_basic_rendering')
            ->firstOrFail();
        $soft404Result = FleetTechnicalSeoAuditResult::query()
            ->where('check_id', 'crawl.unexpected_soft_404_absent')
            ->firstOrFail();

        $this->assertSame(FleetTechnicalSeoAuditResult::STATUS_FAIL, $mobileResult->result_status);
        $this->assertSame(FleetTechnicalSeoAuditResult::CONFIDENCE_MEDIUM, $mobileResult->evidence_confidence);
        $this->assertSame(FleetTechnicalSeoAuditResult::STATUS_MANUAL_REVIEW, $soft404Result->result_status);
        $this->assertDatabaseCount('monitoring_findings', 0);
    }

    public function test_lighthouse_lab_mode_records_bounded_metric_evidence(): void
    {
        $property = $this->makeProperty('lab-site', 'lab.example');
        $this->fakeHealthySite('https://lab.example');
        $this->app->instance(FleetTechnicalSeoLighthouseRunner::class, new class implements FleetTechnicalSeoLighthouseRunner
        {
            public function run(string $url): array
            {
                return [
                    'available' => true,
                    'url' => $url,
                    'final_url' => $url,
                    'scores' => ['performance' => 0.94, 'accessibility' => 0.98, 'best_practices' => 0.92, 'seo' => 1.0],
                    'metrics' => ['lcp_ms' => 1800, 'fcp_ms' => 900, 'cls' => 0.02, 'tbt_ms' => 80],
                    'analytics_blocking_first_paint' => false,
                    'analytics_blocking_resources' => [],
                    'threshold_source' => 'Fleet catalog 2026-05-16',
                    'raw_lighthouse_json' => ['this' => 'must not be stored'],
                ];
            }
        });

        $this->assertSame(0, Artisan::call('monitoring:run-fleet-technical-seo-audit', [
            '--property' => $property->slug,
            '--url-cap' => 10,
        ]));

        $run = FleetTechnicalSeoAuditRun::query()->firstOrFail();
        $coreWebVitals = FleetTechnicalSeoAuditResult::query()
            ->where('check_id', 'performance.core_web_vitals_threshold_reviewed')
            ->firstOrFail();
        $analyticsBlocking = FleetTechnicalSeoAuditResult::query()
            ->where('check_id', 'performance.analytics_not_blocking_first_paint')
            ->firstOrFail();

        $this->assertContains('lighthouse_lab', $run->execution_modes);
        $this->assertSame(FleetTechnicalSeoAuditResult::STATUS_PASS, $coreWebVitals->result_status);
        $this->assertSame(FleetTechnicalSeoAuditResult::CONFIDENCE_LOW, $coreWebVitals->evidence_confidence);
        $this->assertSame(1800, $coreWebVitals->evidence['lighthouse_lab']['metrics']['lcp_ms']);
        $this->assertArrayNotHasKey('raw_lighthouse_json', $coreWebVitals->evidence['lighthouse_lab']);
        $this->assertSame(FleetTechnicalSeoAuditResult::STATUS_PASS, $analyticsBlocking->result_status);
        $this->assertSame(FleetTechnicalSeoAuditResult::CONFIDENCE_MEDIUM, $analyticsBlocking->evidence_confidence);
        $this->assertDatabaseCount('monitoring_findings', 0);
    }

    public function test_lighthouse_lab_mode_records_unknown_when_lab_evidence_is_missing(): void
    {
        $property = $this->makeProperty('missing-lab-site', 'missing-lab.example');
        $this->fakeHealthySite('https://missing-lab.example');

        $this->assertSame(0, Artisan::call('monitoring:run-fleet-technical-seo-audit', [
            '--property' => $property->slug,
            '--url-cap' => 10,
        ]));

        $coreWebVitals = FleetTechnicalSeoAuditResult::query()
            ->where('check_id', 'performance.core_web_vitals_threshold_reviewed')
            ->firstOrFail();
        $analyticsBlocking = FleetTechnicalSeoAuditResult::query()
            ->where('check_id', 'performance.analytics_not_blocking_first_paint')
            ->firstOrFail();

        $this->assertSame(FleetTechnicalSeoAuditResult::STATUS_UNKNOWN, $coreWebVitals->result_status);
        $this->assertSame(FleetTechnicalSeoAuditResult::CONFIDENCE_LOW, $coreWebVitals->evidence_confidence);
        $this->assertSame('No Lighthouse lab evidence was available.', $coreWebVitals->evidence['reason']);
        $this->assertSame(FleetTechnicalSeoAuditResult::STATUS_UNKNOWN, $analyticsBlocking->result_status);
        $this->assertDatabaseCount('monitoring_findings', 0);
    }

    public function test_broad_accessibility_evidence_records_manual_review_without_attention_noise(): void
    {
        $property = $this->makeProperty('a11y-review-site', 'a11y-review.example');
        $this->fakeHealthySite('https://a11y-review.example');
        $this->app->instance(FleetTechnicalSeoBrowserRenderer::class, new class implements FleetTechnicalSeoBrowserRenderer
        {
            public function render(string $url): array
            {
                return [
                    'available' => true,
                    'url' => $url,
                    'final_url' => $url,
                    'title' => 'Accessible review',
                    'text_sample' => 'Accessible review Quote',
                    'body_text_length' => 500,
                    'console_errors' => [],
                    'viewport' => ['width' => 390, 'height' => 844],
                    'content_width' => 390,
                    'h1_count' => 1,
                    'html_lang' => 'en',
                    'main_landmark_count' => 1,
                    'nav_landmark_count' => 1,
                    'link_without_name_count' => 0,
                    'color_contrast_violation_count' => 2,
                    'form_label_missing_count' => 1,
                    'button_without_name_count' => 1,
                    'duplicate_id_count' => 1,
                    'aria_invalid_count' => 1,
                    'heading_order_issue_count' => 1,
                ];
            }
        });

        $this->assertSame(0, Artisan::call('monitoring:run-fleet-technical-seo-audit', [
            '--property' => $property->slug,
            '--url-cap' => 10,
        ]));

        $accessibilityResult = FleetTechnicalSeoAuditResult::query()
            ->where('check_id', 'accessibility.semantic_baseline')
            ->firstOrFail();

        $this->assertSame(FleetTechnicalSeoAuditResult::STATUS_MANUAL_REVIEW, $accessibilityResult->result_status);
        $this->assertSame(FleetTechnicalSeoAuditResult::CONFIDENCE_MEDIUM, $accessibilityResult->evidence_confidence);
        $this->assertContains('color_contrast', $accessibilityResult->evidence['problem_urls'][0]['problems']);
        $this->assertContains('missing_form_labels', $accessibilityResult->evidence['problem_urls'][0]['problems']);
        $this->assertSame('not_attention', $accessibilityResult->evidence['manual_review']['attention_default']);
        $this->assertDatabaseCount('monitoring_findings', 0);
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
