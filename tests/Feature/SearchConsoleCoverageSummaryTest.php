<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\SearchConsoleCoverageStatus;
use App\Models\WebProperty;
use App\Models\WebPropertyDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchConsoleCoverageSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_exposes_fresh_search_console_coverage_as_ok(): void
    {
        $property = $this->propertyWithDomain('fresh-search-console.example.com');
        $coverage = $this->coverage($property, [
            'latest_metric_date' => now()->subDay()->toDateString(),
            'latest_completed_job_at' => now()->subHours(2),
            'raw_payload' => [
                'coverageStatus' => 'search_console_ready',
            ],
        ]);

        $summary = $property->fresh()->searchConsoleCoverageSummary();

        $this->assertSame('covered', $summary['status']);
        $this->assertSame('ok_fresh', $summary['operational_state']);
        $this->assertSame($coverage->latest_metric_date?->toDateString(), $summary['last_successful_evidence_at']);
        $this->assertSame($coverage->latest_completed_job_at?->toIso8601String(), $summary['last_successful_import_at']);
        $this->assertSame('recent', $summary['freshness_state']);
        $this->assertNull($summary['blocker']);
        $this->assertStringContainsString('No Search Console coverage action is required', $summary['next_action']);
    }

    public function test_it_exposes_stale_search_console_coverage_with_next_refresh_action(): void
    {
        $property = $this->propertyWithDomain('movingagain.com.au');
        $coverage = $this->coverage($property, [
            'property_uri' => 'sc-domain:movingagain.com.au',
            'latest_metric_date' => now()->subDays(11)->toDateString(),
            'latest_completed_job_at' => now()->subDays(10),
            'raw_payload' => [
                'coverageStatus' => 'search_console_ready',
            ],
        ]);

        $summary = $property->fresh()->searchConsoleCoverageSummary();

        $this->assertSame('stale_import', $summary['status']);
        $this->assertSame('stale', $summary['operational_state']);
        $this->assertSame($coverage->latest_metric_date?->toDateString(), $summary['last_successful_evidence_at']);
        $this->assertSame($coverage->latest_completed_job_at?->toIso8601String(), $summary['last_successful_import_at']);
        $this->assertSame('stale', $summary['freshness_state']);
        $this->assertStringContainsString('MM-Google evidence for sc-domain:movingagain.com.au', (string) $summary['blocker']);
        $this->assertStringContainsString('Refresh the Search Console coverage import', $summary['next_action']);
    }

    public function test_it_exposes_blocked_search_console_coverage_with_blocker(): void
    {
        $property = $this->propertyWithDomain('blocked-search-console.example.com');
        $coverage = $this->coverage($property, [
            'latest_metric_date' => null,
            'latest_completed_job_at' => null,
            'raw_payload' => [
                'coverageStatus' => 'search_console_audit_failed',
            ],
        ]);

        $summary = $property->fresh()->searchConsoleCoverageSummary();

        $this->assertSame('blocked', $summary['status']);
        $this->assertSame('blocked_unavailable', $summary['operational_state']);
        $this->assertNull($summary['last_successful_evidence_at']);
        $this->assertNull($summary['last_successful_import_at']);
        $this->assertSame($coverage->checked_at?->toIso8601String(), $summary['checked_at']);
        $this->assertSame('never_imported', $summary['freshness_state']);
        $this->assertStringContainsString('MM-Google evidence for sc-domain:blocked-search-console.example.com', (string) $summary['blocker']);
        $this->assertStringContainsString('Resolve the MM-Google Search Console audit blocker', $summary['next_action']);
    }

    public function test_it_exposes_excluded_search_console_coverage_without_import_action(): void
    {
        $property = $this->propertyWithDomain('parked-search-console.example.com', [
            'property_type' => 'domain_asset',
        ]);

        $summary = $property->fresh()->searchConsoleCoverageSummary();

        $this->assertSame('excluded', $summary['status']);
        $this->assertSame('excluded', $summary['operational_state']);
        $this->assertSame('property is a domain asset', $summary['reason']);
        $this->assertSame('property is a domain asset', $summary['blocker']);
        $this->assertNull($summary['last_successful_evidence_at']);
        $this->assertNull($summary['freshness_state']);
        $this->assertStringContainsString('No Search Console action is required', $summary['next_action']);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function propertyWithDomain(string $domainName, array $attributes = []): WebProperty
    {
        $domain = Domain::factory()->create([
            'domain' => $domainName,
            'is_active' => true,
        ]);

        $property = WebProperty::factory()->create([
            'slug' => str($domainName)->replace('.', '-')->toString(),
            'name' => $domainName,
            'primary_domain_id' => $domain->id,
            'status' => 'active',
            'property_type' => 'website',
            ...$attributes,
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $domain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        return $property;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function coverage(WebProperty $property, array $attributes = []): SearchConsoleCoverageStatus
    {
        return SearchConsoleCoverageStatus::create([
            'domain_id' => $property->primary_domain_id,
            'web_property_id' => $property->id,
            'source_provider' => 'mm-google',
            'matomo_site_id' => $property->siteKey() ?? $property->slug,
            'matomo_site_name' => $property->name,
            'mapping_state' => 'domain_property',
            'property_uri' => 'sc-domain:'.$property->primaryDomainName(),
            'property_type' => 'domain',
            'latest_metric_date' => now()->subDay()->toDateString(),
            'latest_completed_job_at' => now()->subHours(2),
            'checked_at' => now(),
            ...$attributes,
        ]);
    }
}
