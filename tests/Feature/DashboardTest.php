<?php

namespace Tests\Feature;

use App\Livewire\Dashboard;
use App\Models\AnalyticsInstallAudit;
use App\Models\Domain;
use App\Models\DomainAlert;
use App\Models\DomainCheck;
use App\Models\DomainSeoBaseline;
use App\Models\DomainTag;
use App\Models\PropertyAnalyticsSource;
use App\Models\PropertyRepository;
use App\Models\SearchConsoleCoverageStatus;
use App\Models\Subdomain;
use App\Models\User;
use App\Models\WebProperty;
use App\Models\WebPropertyDomain;
use App\Services\DashboardIssueQueueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_shows_must_fix_and_should_fix_domain_queues(): void
    {
        $user = User::factory()->create();

        $mustFixDomain = Domain::factory()->create([
            'domain' => 'must-fix.example.com',
            'is_active' => true,
            'eligibility_valid' => false,
        ]);

        DomainCheck::factory()->create([
            'domain_id' => $mustFixDomain->id,
            'check_type' => 'http',
            'status' => 'fail',
        ]);

        DomainCheck::factory()->create([
            'domain_id' => $mustFixDomain->id,
            'check_type' => 'security_headers',
            'status' => 'warn',
        ]);

        DomainAlert::factory()->create([
            'domain_id' => $mustFixDomain->id,
            'severity' => 'critical',
            'resolved_at' => null,
            'alert_type' => 'ssl_expired',
        ]);

        $shouldFixDomain = Domain::factory()->create([
            'domain' => 'should-fix.example.com',
            'is_active' => true,
            'expires_at' => now()->addDays(14),
        ]);

        DomainCheck::factory()->create([
            'domain_id' => $shouldFixDomain->id,
            'check_type' => 'security_headers',
            'status' => 'warn',
        ]);

        DomainCheck::factory()->create([
            'domain_id' => $shouldFixDomain->id,
            'check_type' => 'email_security',
            'status' => 'warn',
        ]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response
            ->assertOk()
            ->assertSee('Must Fix')
            ->assertSee('Should Fix')
            ->assertSee('Refresh Current Issues')
            ->assertSee('must-fix.example.com')
            ->assertSee('HTTP check is failing')
            ->assertSee('Eligibility or compliance has failed')
            ->assertSee('should-fix.example.com')
            ->assertSee('Security headers need review')
            ->assertSee('Email security needs review')
            ->assertSee('Domain expires in 14 days');
    }

    public function test_dashboard_should_fix_cards_fall_back_to_secondary_reasons_when_primary_reasons_are_empty(): void
    {
        $user = User::factory()->create();

        $this->instance(DashboardIssueQueueService::class, new class extends DashboardIssueQueueService
        {
            public function __construct() {}

            public function snapshot(bool $includeExpectedExclusions = false): array
            {
                return [
                    'stats' => [
                        'must_fix' => 0,
                        'should_fix' => 1,
                    ],
                    'must_fix' => [],
                    'should_fix' => [[
                        'id' => 123,
                        'domain' => 'downgraded-queue.example.com',
                        'hosting_provider' => 'DreamIT Host',
                        'primary_reasons' => [],
                        'secondary_reasons' => ['Broken links need review'],
                        'primary_reason_count' => 0,
                        'secondary_reason_count' => 1,
                        'updated_at_human' => '5 minutes ago',
                    ]],
                ];
            }
        });

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertSee('downgraded-queue.example.com')
            ->assertSee('Broken links need review')
            ->assertSee('1 issue')
            ->assertDontSee('0 issues');
    }

    public function test_dashboard_excludes_domains_marked_as_parked_in_synergy(): void
    {
        $parkedDomain = Domain::factory()->create([
            'domain' => 'parked-domain.example.com',
            'is_active' => true,
            'dns_config_name' => 'Parked',
        ]);

        DomainCheck::factory()->create([
            'domain_id' => $parkedDomain->id,
            'check_type' => 'http',
            'status' => 'fail',
        ]);

        DomainCheck::factory()->create([
            'domain_id' => $parkedDomain->id,
            'check_type' => 'ssl',
            'status' => 'fail',
        ]);

        Livewire::test(Dashboard::class)
            ->assertViewHas('mustFixDomains', function (Collection $items): bool {
                return $items->every(fn (array $item): bool => $item['domain'] !== 'parked-domain.example.com');
            })
            ->assertViewHas('shouldFixDomains', function (Collection $items): bool {
                return $items->every(fn (array $item): bool => $item['domain'] !== 'parked-domain.example.com');
            });
    }

    public function test_dashboard_suppresses_web_facing_failures_for_email_only_domains(): void
    {
        $emailOnlyDomain = Domain::factory()->create([
            'domain' => 'email-only.example.com',
            'is_active' => true,
            'platform' => 'Email Only',
        ]);

        DomainCheck::factory()->create([
            'domain_id' => $emailOnlyDomain->id,
            'check_type' => 'http',
            'status' => 'fail',
        ]);

        DomainCheck::factory()->create([
            'domain_id' => $emailOnlyDomain->id,
            'check_type' => 'ssl',
            'status' => 'fail',
        ]);

        DomainCheck::factory()->create([
            'domain_id' => $emailOnlyDomain->id,
            'check_type' => 'email_security',
            'status' => 'warn',
        ]);

        Livewire::test(Dashboard::class)
            ->assertViewHas('mustFixDomains', function (Collection $items): bool {
                return $items->every(fn (array $item): bool => $item['domain'] !== 'email-only.example.com');
            })
            ->assertViewHas('shouldFixDomains', function (Collection $items): bool {
                $emailOnly = $items->firstWhere('domain', 'email-only.example.com');

                return is_array($emailOnly)
                    && in_array('Email security needs review', $emailOnly['primary_reasons'], true)
                    && ! in_array('HTTP check is failing', $emailOnly['primary_reasons'], true)
                    && ! in_array('SSL is failing', $emailOnly['primary_reasons'], true);
            });
    }

    public function test_dashboard_counts_unresolved_web_subdomains_only(): void
    {
        $domain = Domain::factory()->create([
            'domain' => 'example.com',
            'is_active' => true,
        ]);

        Subdomain::create([
            'domain_id' => $domain->id,
            'subdomain' => 'quotes',
            'full_domain' => 'quotes.example.com',
            'ip_checked_at' => now(),
            'ip_address' => null,
            'is_active' => true,
        ]);

        Subdomain::create([
            'domain_id' => $domain->id,
            'subdomain' => 's1._domainkey',
            'full_domain' => 's1._domainkey.example.com',
            'ip_checked_at' => now(),
            'ip_address' => null,
            'is_active' => true,
        ]);

        Livewire::test(Dashboard::class)
            ->assertViewHas('stats', function (array $stats): bool {
                return $stats['unresolved_web_subdomains'] === 1;
            })
            ->assertViewHas('unresolvedWebSubdomainDomains', function (Collection $items): bool {
                $domain = $items->first();

                return is_array($domain)
                    && $domain['domain'] === 'example.com'
                    && in_array('quotes.example.com', $domain['hosts'], true)
                    && ! in_array('s1._domainkey.example.com', $domain['hosts'], true);
            });
    }

    public function test_dashboard_shows_manual_csv_backlog_summary_and_preview(): void
    {
        $user = User::factory()->create();

        $manualCsvTag = DomainTag::create([
            'name' => 'automation.manual_csv_pending',
            'priority' => 68,
            'color' => '#ca8a04',
        ]);

        $pendingProperty = $this->makeProperty('csv-pending.example.au', 'CSV Pending Site');
        $this->attachRepository($pendingProperty);
        $pendingSource = $this->attachMatomo($pendingProperty, '701');
        $this->attachInstallAudit($pendingProperty, $pendingSource);
        $this->attachCoverage($pendingProperty, $pendingSource, 'domain_property', now()->subDay()->toDateString());
        $this->attachBaseline($pendingProperty, $pendingSource, 'matomo_api');
        $pendingProperty->primaryDomainModel()?->tags()->syncWithoutDetaching([$manualCsvTag->id]);

        $completeProperty = $this->makeProperty('complete.example.au', 'Complete Site');
        $this->attachRepository($completeProperty);
        $completeSource = $this->attachMatomo($completeProperty, '702');
        $this->attachInstallAudit($completeProperty, $completeSource);
        $this->attachCoverage($completeProperty, $completeSource, 'domain_property', now()->subDay()->toDateString());
        $this->attachBaseline($completeProperty, $completeSource, 'matomo_plus_manual_csv');
        $completeProperty->primaryDomainModel()?->tags()->syncWithoutDetaching([$manualCsvTag->id]);

        $this->assertSame('manual_csv_pending', $pendingProperty->fresh()->automationCoverageSummary()['status']);
        $this->assertSame('complete', $completeProperty->fresh()->automationCoverageSummary()['status']);

        $response = $this->actingAs($user)->get('/dashboard');

        $response
            ->assertOk()
            ->assertSee('Manual Search Console CSV Backlog')
            ->assertSee('CSV Pending Site')
            ->assertDontSee('Complete Site');

        Livewire::test(Dashboard::class)
            ->assertViewHas('stats', function (array $stats): bool {
                return $stats['manual_csv_pending'] === 1;
            })
            ->assertViewHas('manualCsvPendingStats', function (array $stats): bool {
                return $stats['pending_properties'] === 1
                    && $stats['pending_domains'] === 1;
            })
            ->assertViewHas('manualCsvPendingItems', function (Collection $items): bool {
                return $items->count() === 1
                    && $items->first()['property']->name === 'CSV Pending Site';
            });
    }

    private function makeProperty(string $domainName, string $name): WebProperty
    {
        $domain = Domain::factory()->create([
            'domain' => $domainName,
            'is_active' => true,
            'platform' => 'Astro',
        ]);

        $property = WebProperty::factory()->create([
            'slug' => str($domainName)->replace('.', '-')->toString(),
            'name' => $name,
            'status' => 'active',
            'property_type' => 'marketing_site',
            'primary_domain_id' => $domain->id,
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $domain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        return $property;
    }

    private function attachRepository(WebProperty $property): void
    {
        PropertyRepository::create([
            'web_property_id' => $property->id,
            'repo_provider' => 'local',
            'repo_name' => $property->slug.'-repo',
            'local_path' => '/tmp/'.$property->slug,
            'is_primary' => true,
            'status' => 'active',
        ]);
    }

    private function attachMatomo(WebProperty $property, string $externalId): PropertyAnalyticsSource
    {
        return PropertyAnalyticsSource::create([
            'web_property_id' => $property->id,
            'provider' => 'matomo',
            'external_id' => $externalId,
            'external_name' => $property->name,
            'is_primary' => true,
            'status' => 'active',
        ]);
    }

    private function attachInstallAudit(WebProperty $property, PropertyAnalyticsSource $source): void
    {
        AnalyticsInstallAudit::create([
            'property_analytics_source_id' => $source->id,
            'web_property_id' => $property->id,
            'provider' => 'matomo',
            'external_id' => $source->external_id,
            'external_name' => $property->name,
            'install_verdict' => 'installed_match',
            'best_url' => 'https://'.$property->primaryDomainName().'/',
            'summary' => 'Tracker matches the linked Matomo site.',
            'checked_at' => now(),
            'raw_payload' => ['verdict' => 'installed_match'],
        ]);
    }

    private function attachCoverage(WebProperty $property, PropertyAnalyticsSource $source, string $mappingState, ?string $latestMetricDate): void
    {
        SearchConsoleCoverageStatus::create([
            'domain_id' => $property->primary_domain_id,
            'web_property_id' => $property->id,
            'property_analytics_source_id' => $source->id,
            'source_provider' => 'matomo',
            'matomo_site_id' => $source->external_id,
            'matomo_site_name' => $property->name,
            'mapping_state' => $mappingState,
            'property_uri' => $mappingState === 'domain_property'
                ? 'sc-domain:'.$property->primaryDomainName()
                : 'https://'.$property->primaryDomainName().'/',
            'property_type' => $mappingState === 'domain_property' ? 'domain' : 'url-prefix',
            'latest_metric_date' => $latestMetricDate,
            'checked_at' => now(),
        ]);
    }

    private function attachBaseline(WebProperty $property, PropertyAnalyticsSource $source, string $importMethod, ?\Illuminate\Support\Carbon $capturedAt = null): void
    {
        DomainSeoBaseline::create([
            'domain_id' => $property->primary_domain_id,
            'web_property_id' => $property->id,
            'property_analytics_source_id' => $source->id,
            'baseline_type' => 'search_console',
            'captured_at' => $capturedAt ?? now(),
            'source_provider' => 'search_console',
            'matomo_site_id' => $source->external_id,
            'search_console_property_uri' => 'sc-domain:'.$property->primaryDomainName(),
            'search_type' => 'web',
            'import_method' => $importMethod,
            'clicks' => 10,
            'impressions' => 100,
            'ctr' => 0.1,
            'average_position' => 12.4,
        ]);
    }
}
