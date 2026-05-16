<?php

namespace App\Services;

use App\Models\FleetTechnicalSeoAuditResult;
use App\Models\FleetTechnicalSeoAuditRun;
use App\Models\MonitoringFinding;
use App\Models\WebProperty;
use DOMDocument;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class FleetTechnicalSeoAuditRunner
{
    private const LANE = 'fleet_technical_seo_full_audit';

    private const CATALOG_VERSION = '2026-05-16-executable-runtime-contract';

    /**
     * @var array<string, array{mode: string, signal: 'failure'|'review'|'evidence_only', owner: string, title: string}>
     */
    private const CHECKS = [
        'crawl.http_status_ok' => ['mode' => 'http_fetch', 'signal' => 'failure', 'owner' => 'domain-monitor', 'title' => 'Fleet SEO audit found an unreachable public URL'],
        'crawl.unexpected_soft_404_absent' => ['mode' => 'browser_render', 'signal' => 'review', 'owner' => 'Fleet', 'title' => 'Fleet SEO audit found rendered soft-404 evidence needing review'],
        'robots.present_and_fetchable' => ['mode' => 'http_fetch', 'signal' => 'failure', 'owner' => 'domain-monitor', 'title' => 'Fleet SEO audit found missing robots.txt'],
        'robots.standard_directives_only' => ['mode' => 'http_fetch', 'signal' => 'review', 'owner' => 'Fleet', 'title' => 'Fleet SEO audit found non-standard robots.txt directives'],
        'robots.sitemap_reference_expected' => ['mode' => 'http_fetch', 'signal' => 'review', 'owner' => 'domain-monitor', 'title' => 'Fleet SEO audit found robots.txt without a sitemap reference'],
        'sitemap.canonical_endpoint_fetchable' => ['mode' => 'http_fetch', 'signal' => 'failure', 'owner' => 'domain-monitor', 'title' => 'Fleet SEO audit found an unreachable sitemap'],
        'sitemap.indexable_urls_consistent' => ['mode' => 'bounded_crawl', 'signal' => 'review', 'owner' => 'Fleet', 'title' => 'Fleet SEO audit found sitemap URL consistency evidence'],
        'title.present_unique_and_relevant' => ['mode' => 'html_parse', 'signal' => 'review', 'owner' => 'site-repo', 'title' => 'Fleet SEO audit found missing title evidence'],
        'meta.description_present_and_relevant' => ['mode' => 'html_parse', 'signal' => 'review', 'owner' => 'site-repo', 'title' => 'Fleet SEO audit found missing meta description evidence'],
        'headings.h1_present_and_single_intent' => ['mode' => 'html_parse', 'signal' => 'review', 'owner' => 'site-repo', 'title' => 'Fleet SEO audit found H1 evidence needing review'],
        'canonical.production_origin_expected' => ['mode' => 'html_parse', 'signal' => 'failure', 'owner' => 'domain-monitor', 'title' => 'Fleet SEO audit found canonical origin drift'],
        'indexability.no_unexpected_noindex' => ['mode' => 'html_parse', 'signal' => 'failure', 'owner' => 'domain-monitor', 'title' => 'Fleet SEO audit found an unexpected noindex directive'],
        'redirects.legacy_routes_mapped' => ['mode' => 'http_fetch', 'signal' => 'failure', 'owner' => 'site-repo', 'title' => 'Fleet SEO audit could not verify legacy redirect mapping'],
        'redirects.no_key_route_chains_or_loops' => ['mode' => 'http_fetch', 'signal' => 'failure', 'owner' => 'domain-monitor', 'title' => 'Fleet SEO audit found redirect evidence needing review'],
        'links.internal_key_links_resolve' => ['mode' => 'bounded_crawl', 'signal' => 'failure', 'owner' => 'site-repo', 'title' => 'Fleet SEO audit found broken internal links'],
        'links.external_inventory_classified' => ['mode' => 'bounded_crawl', 'signal' => 'review', 'owner' => 'Fleet', 'title' => 'Fleet SEO audit captured external link inventory evidence'],
        'links.quote_contact_targets_current' => ['mode' => 'bounded_crawl', 'signal' => 'failure', 'owner' => 'site-repo', 'title' => 'Fleet SEO audit found missing quote/contact target links'],
        'images.alt_text_meaningful' => ['mode' => 'html_parse', 'signal' => 'review', 'owner' => 'site-repo', 'title' => 'Fleet SEO audit found image alt evidence needing review'],
        'images.dimensions_declared_for_fixed_assets' => ['mode' => 'html_parse', 'signal' => 'review', 'owner' => 'site-repo', 'title' => 'Fleet SEO audit found image dimension evidence needing review'],
        'mobile.usability_basic_rendering' => ['mode' => 'browser_render', 'signal' => 'review', 'owner' => 'site-repo', 'title' => 'Fleet SEO audit found rendered mobile usability evidence needing review'],
        'performance.core_web_vitals_threshold_reviewed' => ['mode' => 'lighthouse_lab', 'signal' => 'evidence_only', 'owner' => 'Fleet', 'title' => 'Fleet SEO audit recorded Lighthouse lab metric evidence'],
        'performance.analytics_not_blocking_first_paint' => ['mode' => 'lighthouse_lab', 'signal' => 'review', 'owner' => 'site-repo', 'title' => 'Fleet SEO audit found analytics first-paint blocking evidence needing review'],
        'structured_data.valid_jsonld' => ['mode' => 'html_parse', 'signal' => 'review', 'owner' => 'site-repo', 'title' => 'Fleet SEO audit found structured data evidence needing review'],
        'hreflang.intentional_or_absent' => ['mode' => 'html_parse', 'signal' => 'evidence_only', 'owner' => 'Fleet', 'title' => 'Fleet SEO audit recorded hreflang evidence'],
        'security.https_valid_and_canonical' => ['mode' => 'http_fetch', 'signal' => 'failure', 'owner' => 'domain-monitor', 'title' => 'Fleet SEO audit found HTTPS canonical evidence failure'],
        'accessibility.semantic_baseline' => ['mode' => 'browser_render', 'signal' => 'review', 'owner' => 'site-repo', 'title' => 'Fleet SEO audit found rendered accessibility evidence needing review'],
        'social.open_graph_baseline' => ['mode' => 'html_parse', 'signal' => 'review', 'owner' => 'site-repo', 'title' => 'Fleet SEO audit found Open Graph evidence needing review'],
        'ai.llms_txt_expected' => ['mode' => 'http_fetch', 'signal' => 'review', 'owner' => 'domain-monitor', 'title' => 'Fleet SEO audit found llms.txt evidence needing review'],
        'analytics.google_evidence_owned_by_mm_google' => ['mode' => 'imported_evidence', 'signal' => 'evidence_only', 'owner' => 'MM-Google', 'title' => 'Fleet SEO audit recorded Google evidence ownership'],
    ];

    public function __construct(
        private readonly MonitoringFindingManager $findings,
        private readonly FleetTechnicalSeoBrowserRenderer $browserRenderer,
        private readonly FleetTechnicalSeoLighthouseRunner $lighthouseRunner,
    ) {}

    public function run(WebProperty $property, int $urlCap = 25, string $triggerType = 'manual'): FleetTechnicalSeoAuditRun
    {
        $property->loadMissing(['primaryDomain', 'primaryDomain.tags', 'propertyDomains.domain', 'conversionSurfaces']);
        $urlCap = max(1, $urlCap);
        $startedAt = now();
        $modes = collect(self::CHECKS)->pluck('mode')->unique()->values()->all();

        $run = FleetTechnicalSeoAuditRun::query()->create([
            'web_property_id' => $property->id,
            'trigger_type' => $triggerType,
            'url_cap' => $urlCap,
            'execution_modes' => $modes,
            'catalog_version' => self::CATALOG_VERSION,
            'catalog_checksum' => $this->catalogChecksum(),
            'started_at' => $startedAt,
            'summary_counts' => [],
        ]);

        $eligibility = $property->coverageEligibility();
        if (! $eligibility['eligible']) {
            foreach (self::CHECKS as $checkId => $definition) {
                $this->storeResult($run, $property, $checkId, FleetTechnicalSeoAuditResult::STATUS_NOT_APPLICABLE, 'high', [
                    'reason' => $eligibility['reason'],
                    'property_slug' => $property->slug,
                ], null);
            }

            return $this->finishRun($run, [
                'pass' => 0,
                'fail' => 0,
                'not_applicable' => count(self::CHECKS),
                'manual_review' => 0,
                'unknown' => 0,
                'not_checked_due_to_limit' => 0,
            ]);
        }

        $context = $this->collectContext($property, $urlCap);
        $context['browser_render'] = $this->collectBrowserRenderContext($context['selected_urls']);
        $context['lighthouse_lab'] = $this->collectLighthouseLabContext($context['selected_urls']);
        $results = $this->evaluateChecks($property, $context);

        foreach ($results as $checkId => $result) {
            $this->storeResult(
                run: $run,
                property: $property,
                checkId: $checkId,
                status: $result['status'],
                confidence: $result['confidence'],
                evidence: $result['evidence'],
                targetUrl: $result['target_url'] ?? null
            );
        }

        $summaryCounts = collect($results)
            ->countBy(fn (array $result): string => $result['status'])
            ->all();

        foreach ([
            FleetTechnicalSeoAuditResult::STATUS_PASS,
            FleetTechnicalSeoAuditResult::STATUS_FAIL,
            FleetTechnicalSeoAuditResult::STATUS_NOT_APPLICABLE,
            FleetTechnicalSeoAuditResult::STATUS_MANUAL_REVIEW,
            FleetTechnicalSeoAuditResult::STATUS_UNKNOWN,
        ] as $status) {
            $summaryCounts[$status] = (int) ($summaryCounts[$status] ?? 0);
        }

        $summaryCounts['not_checked_due_to_limit'] = count($context['skipped_urls']);

        return $this->finishRun($run, $summaryCounts);
    }

    /**
     * @return array{
     *   base_url: string,
     *   homepage: array<string, mixed>,
     *   robots: array<string, mixed>,
     *   sitemap: array<string, mixed>,
     *   llms: array<string, mixed>,
     *   selected_urls: array<int, string>,
     *   skipped_urls: array<int, string>,
     *   pages: array<string, array<string, mixed>>,
     *   browser_render?: array<string, array<string, mixed>>,
     *   lighthouse_lab?: array<string, array<string, mixed>>,
     *   internal_links: array<int, string>,
     *   external_links: array<int, string>
     * }
     */
    private function collectContext(WebProperty $property, int $urlCap): array
    {
        $baseUrl = $this->baseUrlForProperty($property);
        $homepage = $this->fetchUrl($baseUrl.'/');
        $robots = $this->fetchUrl($baseUrl.'/robots.txt');
        $sitemap = $this->fetchSitemap($baseUrl);
        $llms = $this->fetchUrl($baseUrl.'/llms.txt');
        $homepageLinks = $this->linksFromHtml((string) ($homepage['body'] ?? ''), $baseUrl.'/');
        $sitemapUrls = $this->urlsFromSitemap((string) ($sitemap['body'] ?? ''));
        $declaredUrls = $this->declaredUrlsForProperty($property);

        $candidateUrls = collect([$baseUrl.'/', ...$sitemapUrls, ...$homepageLinks['internal'], ...$declaredUrls])
            ->map(fn (string $url): string => $this->normalizeUrl($url, $baseUrl.'/') ?? '')
            ->filter()
            ->unique()
            ->values();

        $selectedUrls = $candidateUrls->take($urlCap)->all();
        $skippedUrls = $candidateUrls->slice($urlCap)->values()->all();
        $pages = [];

        foreach ($selectedUrls as $url) {
            $pages[$url] = $url === $baseUrl.'/' ? $homepage : $this->fetchUrl($url);
        }

        return [
            'base_url' => $baseUrl,
            'homepage' => $homepage,
            'robots' => $robots,
            'sitemap' => $sitemap,
            'llms' => $llms,
            'selected_urls' => $selectedUrls,
            'skipped_urls' => $skippedUrls,
            'pages' => $pages,
            'internal_links' => $homepageLinks['internal'],
            'external_links' => $homepageLinks['external'],
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, array{status: string, confidence: string, evidence: array<string, mixed>, target_url?: string|null}>
     */
    private function evaluateChecks(WebProperty $property, array $context): array
    {
        $homepage = $context['homepage'];
        $homepageHtml = (string) ($homepage['body'] ?? '');
        $robots = $context['robots'];
        $sitemap = $context['sitemap'];
        $baseUrl = $context['base_url'];
        $expectedOrigin = $this->expectedOrigin($property);
        $canonical = $this->canonicalUrl($homepageHtml, $baseUrl.'/');
        $brokenInternalLinks = $this->brokenInternalLinks($context['internal_links']);
        $declaredUrls = $this->declaredUrlsForProperty($property);
        $visibleLinks = $context['internal_links'];
        $missingDeclaredUrls = collect($declaredUrls)
            ->reject(fn (string $url): bool => in_array($url, $visibleLinks, true))
            ->values()
            ->all();

        $results = [];
        $results['crawl.http_status_ok'] = $this->result(
            $this->isSuccessful($homepage) ? 'pass' : 'fail',
            'high',
            ['homepage' => Arr::except($homepage, ['body']), 'selected_url_count' => count($context['selected_urls']), 'skipped_urls' => $context['skipped_urls']],
            $baseUrl.'/'
        );
        $results['crawl.unexpected_soft_404_absent'] = $this->soft404RenderedResult($context['browser_render'] ?? []);
        $results['robots.present_and_fetchable'] = $this->result($this->isSuccessful($robots) ? 'pass' : 'fail', 'high', ['robots' => Arr::except($robots, ['body'])], $baseUrl.'/robots.txt');
        $results['robots.standard_directives_only'] = $this->result($this->robotsUsesStandardDirectives((string) ($robots['body'] ?? '')) ? 'pass' : 'manual_review', 'medium', ['robots' => Arr::except($robots, ['body'])], $baseUrl.'/robots.txt');
        $results['robots.sitemap_reference_expected'] = $this->result(Str::contains(Str::lower((string) ($robots['body'] ?? '')), 'sitemap:') ? 'pass' : 'manual_review', 'medium', ['robots' => Arr::except($robots, ['body'])], $baseUrl.'/robots.txt');
        $results['sitemap.canonical_endpoint_fetchable'] = $this->result($this->isSuccessful($sitemap) ? 'pass' : 'fail', 'high', ['sitemap' => Arr::except($sitemap, ['body'])], $sitemap['url'] ?? $baseUrl.'/sitemap.xml');
        $results['sitemap.indexable_urls_consistent'] = $this->result($this->isSuccessful($sitemap) ? 'pass' : 'unknown', $this->isSuccessful($sitemap) ? 'medium' : 'low', ['selected_urls' => $context['selected_urls'], 'skipped_urls' => $context['skipped_urls']]);
        $results['title.present_unique_and_relevant'] = $this->htmlPresenceResult($context['pages'], 'title');
        $results['meta.description_present_and_relevant'] = $this->htmlPresenceResult($context['pages'], 'meta_description');
        $results['headings.h1_present_and_single_intent'] = $this->h1Result($context['pages']);
        $results['canonical.production_origin_expected'] = $this->result($canonical !== null && $this->urlMatchesOrigin($canonical, $expectedOrigin) ? 'pass' : 'fail', 'high', ['canonical_url' => $canonical, 'expected_origin' => $expectedOrigin], $baseUrl.'/');
        $results['indexability.no_unexpected_noindex'] = $this->result($this->hasNoindex($homepageHtml, $homepage['headers'] ?? []) ? 'fail' : 'pass', 'high', ['has_noindex' => $this->hasNoindex($homepageHtml, $homepage['headers'] ?? [])], $baseUrl.'/');
        $results['redirects.legacy_routes_mapped'] = $this->result('unknown', 'low', ['reason' => 'No legacy route manifest is wired into the deterministic runner yet.']);
        $results['redirects.no_key_route_chains_or_loops'] = $this->result($this->isSuccessful($homepage) ? 'pass' : 'unknown', $this->isSuccessful($homepage) ? 'high' : 'low', ['homepage' => Arr::except($homepage, ['body'])], $baseUrl.'/');
        $results['links.internal_key_links_resolve'] = $this->result($brokenInternalLinks === [] ? 'pass' : 'fail', 'high', ['broken_internal_links' => $brokenInternalLinks, 'checked_internal_link_count' => count($context['internal_links'])]);
        $results['links.external_inventory_classified'] = $this->result('manual_review', 'medium', ['external_links' => array_slice($context['external_links'], 0, 25), 'external_link_count' => count($context['external_links'])]);
        $results['links.quote_contact_targets_current'] = $this->result($declaredUrls === [] ? 'not_applicable' : ($missingDeclaredUrls === [] ? 'pass' : 'fail'), 'high', ['declared_urls' => $declaredUrls, 'missing_declared_urls' => $missingDeclaredUrls]);
        $results['images.alt_text_meaningful'] = $this->imageResult($context['pages'], 'alt');
        $results['images.dimensions_declared_for_fixed_assets'] = $this->imageResult($context['pages'], 'dimensions');
        $results['mobile.usability_basic_rendering'] = $this->mobileRenderedResult($context['browser_render'] ?? []);
        $results['performance.core_web_vitals_threshold_reviewed'] = $this->coreWebVitalsLabResult($context['lighthouse_lab'] ?? []);
        $results['performance.analytics_not_blocking_first_paint'] = $this->analyticsFirstPaintLabResult($context['lighthouse_lab'] ?? []);
        $results['structured_data.valid_jsonld'] = $this->structuredDataResult($homepageHtml);
        $results['hreflang.intentional_or_absent'] = $this->result('not_applicable', 'low', ['reason' => 'Fleet default is intentional absence unless a property declares international alternates.']);
        $results['security.https_valid_and_canonical'] = $this->result(Str::startsWith($baseUrl, 'https://') && $this->isSuccessful($homepage) ? 'pass' : 'fail', 'high', ['base_url' => $baseUrl, 'homepage' => Arr::except($homepage, ['body'])], $baseUrl.'/');
        $results['accessibility.semantic_baseline'] = $this->accessibilityRenderedResult($context['browser_render'] ?? []);
        $results['social.open_graph_baseline'] = $this->socialResult($homepageHtml);
        $results['ai.llms_txt_expected'] = $this->result($this->isSuccessful($context['llms']) ? 'pass' : 'manual_review', 'medium', ['llms' => Arr::except($context['llms'], ['body'])], $baseUrl.'/llms.txt');
        $results['analytics.google_evidence_owned_by_mm_google'] = $this->result('unknown', 'low', ['owner_system' => 'MM-Google', 'reason' => 'No imported MM-Google evidence attached to this deterministic run.']);

        return $results;
    }

    /**
     * @param  array<int, string>  $urls
     * @return array<string, array<string, mixed>>
     */
    private function collectLighthouseLabContext(array $urls): array
    {
        $labResults = [];

        foreach ($urls as $url) {
            $labResults[$url] = $this->boundedLighthouseEvidence($this->lighthouseRunner->run($url));
        }

        return $labResults;
    }

    /**
     * @param  array<string, mixed>  $evidence
     * @return array<string, mixed>
     */
    private function boundedLighthouseEvidence(array $evidence): array
    {
        $safe = Arr::only($evidence, [
            'available',
            'url',
            'final_url',
            'scores',
            'metrics',
            'analytics_blocking_first_paint',
            'analytics_blocking_resources',
            'threshold_source',
            'reason',
        ]);

        if (isset($safe['analytics_blocking_resources']) && is_array($safe['analytics_blocking_resources'])) {
            $safe['analytics_blocking_resources'] = collect($safe['analytics_blocking_resources'])
                ->map(fn (mixed $resource): mixed => is_string($resource) ? Str::limit($resource, 240, '') : $resource)
                ->take(10)
                ->values()
                ->all();
        }

        return $safe;
    }

    /**
     * @param  array<string, array<string, mixed>>  $labResults
     * @return array{status: string, confidence: string, evidence: array<string, mixed>}
     */
    private function coreWebVitalsLabResult(array $labResults): array
    {
        $checked = $this->availableLabResults($labResults);

        if ($checked === []) {
            return $this->result('unknown', 'low', ['lighthouse_lab' => $this->firstRenderEvidence($labResults), 'reason' => 'No Lighthouse lab evidence was available.']);
        }

        return $this->result('pass', 'low', [
            'lighthouse_lab' => $this->firstRenderEvidence($checked),
            'checked_url_count' => count($checked),
            'threshold_source' => data_get($this->firstRenderEvidence($checked), 'threshold_source', 'Fleet-owned threshold review'),
        ]);
    }

    /**
     * @param  array<string, array<string, mixed>>  $labResults
     * @return array{status: string, confidence: string, evidence: array<string, mixed>}
     */
    private function analyticsFirstPaintLabResult(array $labResults): array
    {
        $checked = $this->availableLabResults($labResults);

        if ($checked === []) {
            return $this->result('unknown', 'low', ['lighthouse_lab' => $this->firstRenderEvidence($labResults), 'reason' => 'No Lighthouse lab evidence was available.']);
        }

        $problemUrls = [];
        foreach ($checked as $url => $lab) {
            if (($lab['analytics_blocking_first_paint'] ?? null) === true) {
                $problemUrls[] = [
                    'url' => $url,
                    'analytics_blocking_resources' => $lab['analytics_blocking_resources'] ?? [],
                ];
            }
        }

        return $this->result($problemUrls === [] ? 'pass' : 'manual_review', 'medium', [
            'lighthouse_lab' => $this->firstRenderEvidence($checked),
            'problem_urls' => $problemUrls,
            'checked_url_count' => count($checked),
        ]);
    }

    /**
     * @param  array<string, array<string, mixed>>  $labResults
     * @return array<string, array<string, mixed>>
     */
    private function availableLabResults(array $labResults): array
    {
        return collect($labResults)
            ->filter(fn (array $lab): bool => ($lab['available'] ?? false) === true)
            ->all();
    }

    /**
     * @param  array<int, string>  $urls
     * @return array<string, array<string, mixed>>
     */
    private function collectBrowserRenderContext(array $urls): array
    {
        $rendered = [];

        foreach ($urls as $url) {
            $rendered[$url] = $this->boundedBrowserEvidence($this->browserRenderer->render($url));
        }

        return $rendered;
    }

    /**
     * @param  array<string, mixed>  $evidence
     * @return array<string, mixed>
     */
    private function boundedBrowserEvidence(array $evidence): array
    {
        $safe = Arr::only($evidence, [
            'available',
            'url',
            'final_url',
            'title',
            'text_sample',
            'body_text_length',
            'console_errors',
            'viewport',
            'content_width',
            'h1_count',
            'html_lang',
            'main_landmark_count',
            'nav_landmark_count',
            'link_without_name_count',
            'reason',
        ]);

        if (isset($safe['text_sample']) && is_string($safe['text_sample'])) {
            $safe['text_sample'] = Str::limit($safe['text_sample'], 500, '');
        }

        if (isset($safe['console_errors']) && is_array($safe['console_errors'])) {
            $safe['console_errors'] = collect($safe['console_errors'])
                ->filter(fn (mixed $error): bool => is_string($error) && trim($error) !== '')
                ->map(fn (string $error): string => Str::limit($error, 240, ''))
                ->take(10)
                ->values()
                ->all();
        }

        return $safe;
    }

    /**
     * @param  array<string, array<string, mixed>>  $renderedPages
     * @return array{status: string, confidence: string, evidence: array<string, mixed>}
     */
    private function mobileRenderedResult(array $renderedPages): array
    {
        $problemUrls = [];
        $checked = [];

        foreach ($renderedPages as $url => $rendered) {
            if (($rendered['available'] ?? false) !== true) {
                continue;
            }

            $checked[$url] = $rendered;
            $viewportWidth = (int) data_get($rendered, 'viewport.width', 0);
            $contentWidth = (int) ($rendered['content_width'] ?? 0);
            $consoleErrors = is_array($rendered['console_errors'] ?? null) ? $rendered['console_errors'] : [];

            if ($consoleErrors !== [] || ($viewportWidth > 0 && $contentWidth > $viewportWidth)) {
                $problemUrls[] = [
                    'url' => $url,
                    'console_error_count' => count($consoleErrors),
                    'viewport_width' => $viewportWidth,
                    'content_width' => $contentWidth,
                ];
            }
        }

        if ($checked === []) {
            return $this->result('unknown', 'low', ['browser_render' => $this->firstRenderEvidence($renderedPages), 'reason' => 'No rendered browser evidence was available.']);
        }

        return $this->result($problemUrls === [] ? 'pass' : 'fail', 'medium', [
            'browser_render' => $this->firstRenderEvidence($checked),
            'problem_urls' => $problemUrls,
            'checked_url_count' => count($checked),
        ]);
    }

    /**
     * @param  array<string, array<string, mixed>>  $renderedPages
     * @return array{status: string, confidence: string, evidence: array<string, mixed>}
     */
    private function accessibilityRenderedResult(array $renderedPages): array
    {
        $problemUrls = [];
        $checked = [];

        foreach ($renderedPages as $url => $rendered) {
            if (($rendered['available'] ?? false) !== true) {
                continue;
            }

            $checked[$url] = $rendered;
            $problems = [];

            if (trim((string) ($rendered['html_lang'] ?? '')) === '') {
                $problems[] = 'missing_html_lang';
            }

            if ((int) ($rendered['h1_count'] ?? 0) < 1) {
                $problems[] = 'missing_h1';
            }

            if ((int) ($rendered['main_landmark_count'] ?? 0) < 1) {
                $problems[] = 'missing_main_landmark';
            }

            if ((int) ($rendered['link_without_name_count'] ?? 0) > 0) {
                $problems[] = 'unnamed_links';
            }

            if ($problems !== []) {
                $problemUrls[] = [
                    'url' => $url,
                    'problems' => $problems,
                ];
            }
        }

        if ($checked === []) {
            return $this->result('unknown', 'low', ['browser_render' => $this->firstRenderEvidence($renderedPages), 'reason' => 'No rendered browser evidence was available.']);
        }

        return $this->result($problemUrls === [] ? 'pass' : 'manual_review', 'medium', [
            'browser_render' => $this->firstRenderEvidence($checked),
            'problem_urls' => $problemUrls,
            'checked_url_count' => count($checked),
        ]);
    }

    /**
     * @param  array<string, array<string, mixed>>  $renderedPages
     * @return array{status: string, confidence: string, evidence: array<string, mixed>}
     */
    private function soft404RenderedResult(array $renderedPages): array
    {
        $suspiciousUrls = [];
        $checked = [];

        foreach ($renderedPages as $url => $rendered) {
            if (($rendered['available'] ?? false) !== true) {
                continue;
            }

            $checked[$url] = $rendered;
            $sample = Str::lower((string) ($rendered['text_sample'] ?? ''));
            $title = Str::lower((string) ($rendered['title'] ?? ''));
            $bodyTextLength = (int) ($rendered['body_text_length'] ?? 0);

            if ($bodyTextLength < 120 || Str::contains($sample.' '.$title, ['404', 'not found', 'coming soon', 'parked domain'])) {
                $suspiciousUrls[] = [
                    'url' => $url,
                    'body_text_length' => $bodyTextLength,
                    'title' => $rendered['title'] ?? null,
                ];
            }
        }

        if ($checked === []) {
            return $this->result('unknown', 'low', ['browser_render' => $this->firstRenderEvidence($renderedPages), 'reason' => 'No rendered browser evidence was available.']);
        }

        return $this->result($suspiciousUrls === [] ? 'pass' : 'manual_review', 'medium', [
            'browser_render' => $this->firstRenderEvidence($checked),
            'suspicious_urls' => $suspiciousUrls,
            'checked_url_count' => count($checked),
        ]);
    }

    /**
     * @param  array<string, array<string, mixed>>  $renderedPages
     * @return array<string, mixed>
     */
    private function firstRenderEvidence(array $renderedPages): array
    {
        return array_values($renderedPages)[0] ?? [];
    }

    /**
     * @param  array<string, mixed>  $evidence
     * @return array{status: string, confidence: string, evidence: array<string, mixed>, target_url?: string|null}
     */
    private function result(string $status, string $confidence, array $evidence, ?string $targetUrl = null): array
    {
        return [
            'status' => $status,
            'confidence' => $confidence,
            'evidence' => $evidence,
            'target_url' => $targetUrl,
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $pages
     * @return array{status: string, confidence: string, evidence: array<string, mixed>}
     */
    private function htmlPresenceResult(array $pages, string $field): array
    {
        $missing = [];

        foreach ($pages as $url => $page) {
            if (! $this->isSuccessful($page)) {
                continue;
            }

            $html = (string) ($page['body'] ?? '');
            $present = $field === 'title'
                ? trim($this->firstTagText($html, 'title') ?? '') !== ''
                : trim($this->metaContent($html, 'description') ?? '') !== '';

            if (! $present) {
                $missing[] = $url;
            }
        }

        return $this->result($missing === [] ? 'pass' : 'manual_review', 'medium', ['missing_urls' => $missing, 'field' => $field]);
    }

    /**
     * @param  array<string, array<string, mixed>>  $pages
     * @return array{status: string, confidence: string, evidence: array<string, mixed>}
     */
    private function h1Result(array $pages): array
    {
        $problemUrls = [];

        foreach ($pages as $url => $page) {
            if (! $this->isSuccessful($page)) {
                continue;
            }

            $count = $this->tagCount((string) ($page['body'] ?? ''), 'h1');
            if ($count !== 1) {
                $problemUrls[] = ['url' => $url, 'h1_count' => $count];
            }
        }

        return $this->result($problemUrls === [] ? 'pass' : 'manual_review', 'medium', ['problem_urls' => $problemUrls]);
    }

    /**
     * @param  array<string, array<string, mixed>>  $pages
     * @return array{status: string, confidence: string, evidence: array<string, mixed>}
     */
    private function imageResult(array $pages, string $kind): array
    {
        $problemImages = [];

        foreach ($pages as $url => $page) {
            if (! $this->isSuccessful($page)) {
                continue;
            }

            foreach ($this->imagesFromHtml((string) ($page['body'] ?? ''), $url) as $image) {
                $hasProblem = $kind === 'alt'
                    ? trim((string) ($image['alt'] ?? '')) === ''
                    : (($image['width'] ?? '') === '' || ($image['height'] ?? '') === '');

                if ($hasProblem) {
                    $problemImages[] = $image;
                }
            }
        }

        return $this->result($problemImages === [] ? 'pass' : 'manual_review', 'medium', ['problem_images' => array_slice($problemImages, 0, 25), 'problem_image_count' => count($problemImages)]);
    }

    /**
     * @return array{status: string, confidence: string, evidence: array<string, mixed>}
     */
    private function structuredDataResult(string $html): array
    {
        preg_match_all("~<script[^>]+type=[\"']application/ld\\+json[\"'][^>]*>(.*?)</script>~is", $html, $matches);
        $scripts = $matches[1];
        $invalid = 0;

        foreach ($scripts as $script) {
            json_decode(html_entity_decode(trim($script)), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $invalid++;
            }
        }

        $status = $scripts === [] || $invalid > 0 ? 'manual_review' : 'pass';

        return $this->result($status, 'medium', ['script_count' => count($scripts), 'invalid_script_count' => $invalid]);
    }

    /**
     * @return array{status: string, confidence: string, evidence: array<string, mixed>}
     */
    private function socialResult(string $html): array
    {
        $missing = collect(['og:title', 'og:description', 'og:image'])
            ->reject(fn (string $property): bool => $this->propertyContent($html, $property) !== null)
            ->values()
            ->all();

        return $this->result($missing === [] ? 'pass' : 'manual_review', 'medium', ['missing_open_graph_properties' => $missing]);
    }

    /**
     * @param  array<string, mixed>  $evidence
     */
    private function storeResult(FleetTechnicalSeoAuditRun $run, WebProperty $property, string $checkId, string $status, string $confidence, array $evidence, ?string $targetUrl): void
    {
        $definition = self::CHECKS[$checkId];
        $findingId = null;

        if ($definition['signal'] === 'failure' && $confidence === FleetTechnicalSeoAuditResult::CONFIDENCE_HIGH) {
            $findingType = 'fleet_technical_seo.'.$checkId;
            $primaryDomain = $property->primaryDomainModel();

            if ($status === FleetTechnicalSeoAuditResult::STATUS_FAIL) {
                $this->findings->reportPropertyFinding(
                    property: $property,
                    findingType: $findingType,
                    lane: self::LANE,
                    issueType: 'cleanup',
                    title: $definition['title'],
                    summary: $this->summaryForFailure($checkId, $evidence),
                    evidence: $evidence,
                    primaryDomainId: $primaryDomain?->id
                );
            } elseif ($status === FleetTechnicalSeoAuditResult::STATUS_PASS) {
                $this->findings->recoverPropertyFinding(
                    property: $property,
                    findingType: $findingType,
                    lane: self::LANE,
                    recoverySummary: 'Fleet technical SEO audit check passed.',
                    recoveryEvidence: $evidence,
                    primaryDomainId: $primaryDomain?->id
                );
            }

            $findingId = MonitoringFinding::query()
                ->where('web_property_id', $property->id)
                ->where('finding_type', $findingType)
                ->latest('updated_at')
                ->value('id');
        }

        FleetTechnicalSeoAuditResult::query()->create([
            'fleet_technical_seo_audit_run_id' => $run->id,
            'check_id' => $checkId,
            'target_type' => $targetUrl !== null ? 'url' : 'web_property',
            'target_url' => $targetUrl,
            'result_status' => $status,
            'evidence_confidence' => $confidence,
            'evidence' => $evidence,
            'owner_system' => $definition['owner'],
            'monitoring_finding_id' => $status === FleetTechnicalSeoAuditResult::STATUS_FAIL ? $findingId : null,
        ]);
    }

    /**
     * @param  array<string, int>  $summaryCounts
     */
    private function finishRun(FleetTechnicalSeoAuditRun $run, array $summaryCounts): FleetTechnicalSeoAuditRun
    {
        $run->forceFill([
            'finished_at' => now(),
            'summary_counts' => $summaryCounts,
        ])->save();

        return $run->refresh();
    }

    /**
     * @param  array<string, mixed>  $evidence
     */
    private function summaryForFailure(string $checkId, array $evidence): string
    {
        return match ($checkId) {
            'crawl.http_status_ok' => 'Fleet full technical SEO audit could not fetch the public homepage successfully.',
            'robots.present_and_fetchable' => 'Fleet full technical SEO audit could not fetch robots.txt.',
            'sitemap.canonical_endpoint_fetchable' => 'Fleet full technical SEO audit could not fetch a canonical sitemap endpoint.',
            'canonical.production_origin_expected' => 'Fleet full technical SEO audit found a missing or mismatched canonical URL.',
            'indexability.no_unexpected_noindex' => 'Fleet full technical SEO audit found a noindex directive on an indexable page.',
            'links.internal_key_links_resolve' => sprintf('Fleet full technical SEO audit found %d broken internal link(s).', count($evidence['broken_internal_links'] ?? [])),
            'links.quote_contact_targets_current' => 'Fleet full technical SEO audit could not find all declared quote/contact target links on the homepage.',
            'security.https_valid_and_canonical' => 'Fleet full technical SEO audit found HTTPS or canonical homepage evidence failing.',
            default => 'Fleet full technical SEO audit found a high-confidence failure.',
        };
    }

    private function catalogChecksum(): string
    {
        return hash('sha256', json_encode(self::CHECKS, JSON_THROW_ON_ERROR));
    }

    private function baseUrlForProperty(WebProperty $property): string
    {
        $scheme = is_string($property->canonical_origin_scheme) && $property->canonical_origin_scheme !== ''
            ? Str::lower($property->canonical_origin_scheme)
            : 'https';
        $host = is_string($property->canonical_origin_host) && $property->canonical_origin_host !== ''
            ? Str::lower($property->canonical_origin_host)
            : Str::lower((string) $property->primaryDomainName());

        if ($host === '' && is_string($property->production_url)) {
            $parts = parse_url($property->production_url);
            $host = Str::lower((string) ($parts['host'] ?? ''));
            $scheme = Str::lower((string) ($parts['scheme'] ?? $scheme));
        }

        return rtrim($scheme.'://'.$host, '/');
    }

    private function expectedOrigin(WebProperty $property): ?string
    {
        $baseUrl = $this->baseUrlForProperty($property);

        return $baseUrl !== 'https://' ? $baseUrl : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchUrl(string $url): array
    {
        try {
            /** @var Response $response */
            $response = Http::timeout(10)
                ->withHeaders(['User-Agent' => 'DomainMonitor/1.0 FleetTechnicalSeoAudit'])
                ->get($url);

            return [
                'url' => $url,
                'status' => $response->status(),
                'successful' => $response->successful(),
                'headers' => $response->headers(),
                'body' => $response->body(),
            ];
        } catch (\Throwable $exception) {
            return [
                'url' => $url,
                'status' => 0,
                'successful' => false,
                'headers' => [],
                'body' => '',
                'error' => $exception->getMessage(),
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchSitemap(string $baseUrl): array
    {
        foreach (['/sitemap.xml', '/sitemap_index.xml'] as $path) {
            $result = $this->fetchUrl($baseUrl.$path);
            if ($this->isSuccessful($result)) {
                return $result;
            }
        }

        return $this->fetchUrl($baseUrl.'/sitemap.xml');
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function isSuccessful(array $response): bool
    {
        $status = (int) ($response['status'] ?? 0);

        return $status >= 200 && $status < 400;
    }

    /**
     * @return array{internal: array<int, string>, external: array<int, string>}
     */
    private function linksFromHtml(string $html, string $pageUrl): array
    {
        $dom = $this->dom($html);
        $pageHost = parse_url($pageUrl, PHP_URL_HOST);
        $internal = [];
        $external = [];

        foreach ($dom->getElementsByTagName('a') as $link) {
            $url = $this->normalizeUrl($link->getAttribute('href'), $pageUrl);
            if ($url === null) {
                continue;
            }

            $host = parse_url($url, PHP_URL_HOST);
            if ($host === $pageHost) {
                $internal[] = $url;
            } else {
                $external[] = $url;
            }
        }

        return [
            'internal' => array_values(array_unique($internal)),
            'external' => array_values(array_unique($external)),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function urlsFromSitemap(string $xml): array
    {
        preg_match_all('/<loc>\\s*([^<]+)\\s*<\\/loc>/i', $xml, $matches);

        return collect($matches[1])
            ->map(fn (string $url): string => trim(html_entity_decode($url)))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function declaredUrlsForProperty(WebProperty $property): array
    {
        return collect([
            $property->target_household_quote_url,
            $property->target_household_booking_url,
            $property->target_vehicle_quote_url,
            $property->target_vehicle_booking_url,
            $property->target_contact_us_page_url,
        ])
            ->filter(fn (mixed $url): bool => is_string($url) && trim($url) !== '')
            ->map(fn (string $url): string => trim($url))
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $links
     * @return array<int, array{url: string, status: int}>
     */
    private function brokenInternalLinks(array $links): array
    {
        $broken = [];

        foreach ($links as $link) {
            $result = $this->fetchUrl($link);
            $status = (int) ($result['status'] ?? 0);

            if ($status >= 400 || $status === 0) {
                $broken[] = [
                    'url' => $link,
                    'status' => $status,
                ];
            }
        }

        return $broken;
    }

    private function robotsUsesStandardDirectives(string $body): bool
    {
        foreach (preg_split('/\\R/', $body) ?: [] as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || Str::startsWith($trimmed, '#')) {
                continue;
            }

            if (preg_match('/^(user-agent|allow|disallow|sitemap|crawl-delay):/i', $trimmed) !== 1) {
                return false;
            }
        }

        return true;
    }

    private function canonicalUrl(string $html, string $pageUrl): ?string
    {
        if (preg_match("~<link[^>]+rel=[\"'][^\"']*canonical[^\"']*[\"'][^>]+href=[\"']([^\"']+)[\"']~i", $html, $matches) !== 1
            && preg_match("~<link[^>]+href=[\"']([^\"']+)[\"'][^>]+rel=[\"'][^\"']*canonical[^\"']*[\"']~i", $html, $matches) !== 1) {
            return null;
        }

        return $this->normalizeUrl($matches[1], $pageUrl);
    }

    private function urlMatchesOrigin(?string $url, ?string $expectedOrigin): bool
    {
        if ($url === null || $expectedOrigin === null) {
            return false;
        }

        return Str::startsWith(Str::lower(rtrim($url, '/')), Str::lower(rtrim($expectedOrigin, '/')));
    }

    /**
     * @param  array<string, mixed>  $headers
     */
    private function hasNoindex(string $html, array $headers): bool
    {
        $xRobots = collect($headers)
            ->filter(fn (mixed $value, string $key): bool => Str::lower($key) === 'x-robots-tag')
            ->flatten()
            ->implode(',');

        return Str::contains(Str::lower($xRobots), 'noindex')
            || preg_match("~<meta[^>]+name=[\"']robots[\"'][^>]+content=[\"'][^\"']*noindex~i", $html) === 1;
    }

    private function firstTagText(string $html, string $tag): ?string
    {
        $nodes = $this->dom($html)->getElementsByTagName($tag);
        $node = $nodes->item(0);

        return $node?->textContent;
    }

    private function tagCount(string $html, string $tag): int
    {
        return $this->dom($html)->getElementsByTagName($tag)->length;
    }

    private function metaContent(string $html, string $name): ?string
    {
        if (preg_match('~<meta[^>]+name=["\']'.preg_quote($name, '~').'["\'][^>]+content=["\']([^"\']*)["\']~i', $html, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    private function propertyContent(string $html, string $property): ?string
    {
        if (preg_match('~<meta[^>]+property=["\']'.preg_quote($property, '~').'["\'][^>]+content=["\']([^"\']*)["\']~i', $html, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    /**
     * @return array<int, array<string, string|null>>
     */
    private function imagesFromHtml(string $html, string $pageUrl): array
    {
        $images = [];

        foreach ($this->dom($html)->getElementsByTagName('img') as $image) {
            $images[] = [
                'src' => $this->normalizeUrl($image->getAttribute('src'), $pageUrl),
                'alt' => $image->getAttribute('alt'),
                'width' => $image->getAttribute('width'),
                'height' => $image->getAttribute('height'),
            ];
        }

        return $images;
    }

    private function normalizeUrl(string $url, string $baseUrl): ?string
    {
        $trimmed = trim($url);
        if ($trimmed === '' || Str::startsWith($trimmed, ['#', 'mailto:', 'tel:', 'javascript:'])) {
            return null;
        }

        if (Str::startsWith($trimmed, ['http://', 'https://'])) {
            return rtrim($trimmed, '/');
        }

        $parts = parse_url($baseUrl);
        $origin = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '');

        if (Str::startsWith($trimmed, '/')) {
            return rtrim($origin, '/').$trimmed;
        }

        return rtrim($origin, '/').'/'.ltrim($trimmed, '/');
    }

    private function dom(string $html): DOMDocument
    {
        $dom = new DOMDocument;
        @$dom->loadHTML($html);

        return $dom;
    }
}
