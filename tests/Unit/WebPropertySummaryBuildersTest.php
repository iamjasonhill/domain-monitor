<?php

namespace Tests\Unit;

use App\Models\Domain;
use App\Models\DomainSeoBaseline;
use App\Models\WebProperty;
use App\Models\WebPropertyDomain;
use App\Services\WebPropertyCanonicalOriginSummaryBuilder;
use App\Services\WebPropertyGscEvidenceSummaryBuilder;
use App\Services\WebPropertySeoBaselineSummaryBuilder;
use App\Services\WebPropertySiteIdentitySummaryBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use PHPUnit\Framework\TestCase;

class WebPropertySummaryBuildersTest extends TestCase
{
    public function test_canonical_origin_builder_uses_safe_fallbacks_and_normalizes_subdomains(): void
    {
        $property = new WebProperty([
            'production_url' => 'https://movingagain.com.au/services',
            'canonical_origin_policy' => 'unknown',
            'canonical_origin_enforcement_eligible' => false,
            'canonical_origin_excluded_subdomains' => [
                ' https://portal.movingagain.com.au/login ',
                'quotes.movingagain.com.au.',
                'quotes.movingagain.com.au',
                '',
            ],
            'canonical_origin_sitemap_policy_known' => false,
        ]);

        $property->setRelation('propertyDomains', collect([
            $this->domainLink('movingagain.com.au', 'primary', true),
            $this->domainLink('quoting.movingagain.com.au', 'subdomain'),
            $this->domainLink('www.movingagain.com.au', 'redirect'),
            $this->domainLink('example.com', 'subdomain'),
        ]));

        $summary = (new WebPropertyCanonicalOriginSummaryBuilder)->build($property);

        $this->assertSame('https', $summary['scheme']);
        $this->assertSame('movingagain.com.au', $summary['host']);
        $this->assertSame('https://movingagain.com.au', $summary['base_url']);
        $this->assertSame('unknown', $summary['policy']);
        $this->assertSame('property_only', $summary['scope']);
        $this->assertFalse($summary['enforcement_eligible']);
        $this->assertSame(
            ['quoting.movingagain.com.au', 'www.movingagain.com.au'],
            $summary['owned_subdomains']
        );
        $this->assertSame(
            ['portal.movingagain.com.au', 'quotes.movingagain.com.au'],
            $summary['excluded_subdomains']
        );
        $this->assertFalse($summary['sitemap_policy_known']);
    }

    public function test_canonical_origin_builder_requires_explicit_known_origin_for_enforcement(): void
    {
        $property = new WebProperty([
            'production_url' => 'https://backloading-au.com.au',
            'canonical_origin_policy' => 'known',
            'canonical_origin_enforcement_eligible' => true,
            'canonical_origin_scheme' => null,
            'canonical_origin_host' => null,
        ]);
        $property->setRelation('propertyDomains', collect());

        $summary = (new WebPropertyCanonicalOriginSummaryBuilder)->build($property);

        $this->assertSame('https', $summary['scheme']);
        $this->assertSame('backloading-au.com.au', $summary['host']);
        $this->assertSame('https://backloading-au.com.au', $summary['base_url']);
        $this->assertSame('known', $summary['policy']);
        $this->assertFalse($summary['enforcement_eligible']);
    }

    public function test_gsc_evidence_builder_normalizes_string_and_datetime_timestamps(): void
    {
        $property = new WebProperty;
        $property->forceFill([
            'has_gsc_issue_detail' => true,
            'gsc_issue_detail_snapshot_count' => 2,
            'gsc_issue_detail_last_captured_at' => '2026-04-09 09:14:03',
            'has_gsc_api_enrichment' => true,
            'gsc_api_snapshot_count' => 3,
            'gsc_api_last_captured_at' => CarbonImmutable::parse('2026-04-09T01:02:03+10:00'),
        ]);

        $summary = (new WebPropertyGscEvidenceSummaryBuilder)->build($property);

        $this->assertTrue($summary['has_issue_detail']);
        $this->assertSame(2, $summary['issue_detail_snapshot_count']);
        $this->assertSame('2026-04-09T09:14:03+00:00', $summary['latest_issue_detail_captured_at']);
        $this->assertTrue($summary['has_api_enrichment']);
        $this->assertSame(3, $summary['api_snapshot_count']);
        $this->assertSame('2026-04-09T01:02:03+10:00', $summary['latest_api_captured_at']);
    }

    public function test_gsc_evidence_builder_returns_stable_null_defaults(): void
    {
        $summary = (new WebPropertyGscEvidenceSummaryBuilder)->build(new WebProperty);

        $this->assertSame([
            'has_issue_detail' => false,
            'issue_detail_snapshot_count' => 0,
            'latest_issue_detail_captured_at' => null,
            'has_api_enrichment' => false,
            'api_snapshot_count' => 0,
            'latest_api_captured_at' => null,
        ], $summary);
    }

    public function test_site_identity_builder_uses_explicit_fields_and_aligned_targets(): void
    {
        $property = new WebProperty([
            'name' => 'WeMove Website',
            'site_identity_site_name' => 'WeMove',
            'site_identity_legal_name' => 'WeMove Australia',
            'production_url' => 'https://wemove.com.au/services',
            'canonical_origin_scheme' => 'https',
            'canonical_origin_host' => 'wemove.com.au',
            'canonical_origin_policy' => 'known',
            'target_moveroo_subdomain_url' => 'https://quotes.wemove.com.au',
            'target_contact_us_page_url' => 'https://quotes.wemove.com.au/contact?from=footer',
        ]);
        $property->setRelation('propertyDomains', collect([
            $this->domainLink('wemove.com.au', 'primary', true),
        ]));

        $summary = (new WebPropertySiteIdentitySummaryBuilder)->build($property);

        $this->assertSame([
            'site_name' => 'WeMove',
            'legal_name' => 'WeMove Australia',
            'primary_domain' => 'https://wemove.com.au/',
            'quote_portal' => 'https://quotes.wemove.com.au/',
            'contact_page' => 'https://quotes.wemove.com.au/contact',
        ], $summary);
    }

    public function test_site_identity_builder_returns_stable_nulls_and_infers_site_name(): void
    {
        $property = new WebProperty([
            'name' => 'Moveroo Website',
        ]);
        $property->setRelation('propertyDomains', collect());

        $summary = (new WebPropertySiteIdentitySummaryBuilder)->build($property);

        $this->assertSame([
            'site_name' => 'Moveroo',
            'legal_name' => null,
            'primary_domain' => null,
            'quote_portal' => null,
            'contact_page' => null,
        ], $summary);
    }

    public function test_seo_baseline_builder_returns_stable_defaults_without_checkpoints(): void
    {
        $property = new WebProperty;
        $property->setRelation('seoBaselines', new EloquentCollection);

        $summary = (new WebPropertySeoBaselineSummaryBuilder)->build($property);

        $this->assertSame([
            'has_baseline' => false,
            'latest' => [
                'captured_at' => null,
                'baseline_type' => null,
                'indexed_pages' => null,
                'not_indexed_pages' => null,
                'clicks' => null,
                'impressions' => null,
                'ctr' => null,
                'average_position' => null,
            ],
            'trend' => [
                'window' => 'last_12_checkpoints',
                'point_count' => 0,
                'indexed_pages_delta' => null,
                'not_indexed_pages_delta' => null,
                'points' => [],
            ],
        ], $summary);
    }

    public function test_seo_baseline_builder_exposes_latest_snapshot_and_trend_deltas(): void
    {
        $property = new WebProperty;
        $property->setRelation('seoBaselines', new EloquentCollection([
            $this->seoBaseline('2026-04-12T00:00:00+00:00', 'weekly_checkpoint', 18, 182, 21, 1200),
            $this->seoBaseline('2026-04-05T00:00:00+00:00', 'weekly_checkpoint', 12, 205, 14, 950),
            $this->seoBaseline('2026-03-29T00:00:00+00:00', 'pre_rebuild', 9, 214, 10, 880),
        ]));

        $summary = (new WebPropertySeoBaselineSummaryBuilder)->build($property);

        $this->assertTrue($summary['has_baseline']);
        $this->assertSame('2026-04-12T00:00:00+00:00', $summary['latest']['captured_at']);
        $this->assertSame('weekly_checkpoint', $summary['latest']['baseline_type']);
        $this->assertSame(18, $summary['latest']['indexed_pages']);
        $this->assertSame(182, $summary['latest']['not_indexed_pages']);
        $this->assertSame(21.0, $summary['latest']['clicks']);
        $this->assertSame(1200.0, $summary['latest']['impressions']);
        $this->assertSame(9, $summary['trend']['indexed_pages_delta']);
        $this->assertSame(-32, $summary['trend']['not_indexed_pages_delta']);
        $this->assertSame(3, $summary['trend']['point_count']);
        $this->assertSame('2026-03-29T00:00:00+00:00', $summary['trend']['points'][0]['captured_at']);
        $this->assertSame(9, $summary['trend']['points'][0]['indexed_pages']);
        $this->assertSame(18, $summary['trend']['points'][2]['indexed_pages']);
    }

    private function domainLink(string $domainName, string $usageType, bool $isCanonical = false): WebPropertyDomain
    {
        $domain = new Domain([
            'domain' => $domainName,
        ]);

        $link = new WebPropertyDomain([
            'usage_type' => $usageType,
            'is_canonical' => $isCanonical,
        ]);
        $link->setRelation('domain', $domain);

        return $link;
    }

    private function seoBaseline(
        string $capturedAt,
        string $baselineType,
        int $indexedPages,
        int $notIndexedPages,
        int $clicks,
        int $impressions,
    ): DomainSeoBaseline {
        $baseline = new DomainSeoBaseline;
        $baseline->setRawAttributes([
            'captured_at' => CarbonImmutable::parse($capturedAt)->toDateTimeString(),
            'baseline_type' => $baselineType,
            'indexed_pages' => $indexedPages,
            'not_indexed_pages' => $notIndexedPages,
            'clicks' => $clicks,
            'impressions' => $impressions,
            'ctr' => 0.01,
            'average_position' => 11.4,
        ], true);

        return $baseline;
    }
}
