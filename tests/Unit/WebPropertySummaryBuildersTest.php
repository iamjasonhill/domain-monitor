<?php

namespace Tests\Unit;

use App\Models\Domain;
use App\Models\WebProperty;
use App\Models\WebPropertyDomain;
use App\Services\WebPropertyCanonicalOriginSummaryBuilder;
use App\Services\WebPropertyGscEvidenceSummaryBuilder;
use Carbon\CarbonImmutable;
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
}
