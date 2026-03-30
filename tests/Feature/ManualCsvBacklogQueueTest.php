<?php

namespace Tests\Feature;

use App\Livewire\ManualCsvBacklogQueue;
use App\Models\AnalyticsInstallAudit;
use App\Models\Domain;
use App\Models\DomainSeoBaseline;
use App\Models\DomainTag;
use App\Models\PropertyAnalyticsSource;
use App\Models\PropertyRepository;
use App\Models\SearchConsoleCoverageStatus;
use App\Models\User;
use App\Models\WebProperty;
use App\Models\WebPropertyDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Livewire\Livewire;
use Tests\TestCase;

class ManualCsvBacklogQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_manual_csv_backlog_queue(): void
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

        $sharedComplete = $this->makeProperty('shared.example.au', 'Shared Complete');
        $this->attachRepository($sharedComplete);
        $sharedCompleteSource = $this->attachMatomo($sharedComplete, '703');
        $this->attachInstallAudit($sharedComplete, $sharedCompleteSource);
        $this->attachCoverage($sharedComplete, $sharedCompleteSource, 'domain_property', now()->subDay()->toDateString());
        $this->attachBaseline($sharedComplete, $sharedCompleteSource, 'matomo_plus_manual_csv');

        $sharedPending = WebProperty::factory()->create([
            'slug' => 'shared-pending-site',
            'name' => 'Shared Pending',
            'status' => 'active',
            'property_type' => 'website',
            'primary_domain_id' => $sharedComplete->primary_domain_id,
        ]);
        WebPropertyDomain::create([
            'web_property_id' => $sharedPending->id,
            'domain_id' => $sharedComplete->primary_domain_id,
            'usage_type' => 'primary',
            'is_canonical' => false,
        ]);
        $this->attachRepository($sharedPending);
        $sharedPendingSource = $this->attachMatomo($sharedPending, '704');
        $this->attachInstallAudit($sharedPending, $sharedPendingSource);
        $this->attachCoverage($sharedPending, $sharedPendingSource, 'domain_property', now()->subDay()->toDateString());
        $this->attachBaseline($sharedPending, $sharedPendingSource, 'matomo_api');
        $sharedPending->primaryDomainModel()?->tags()->syncWithoutDetaching([$manualCsvTag->id]);

        $response = $this->actingAs($user)->get('/manual-csv-backlog');

        $response->assertOk();
        $response->assertSee('Manual Search Console CSV Backlog');
        $response->assertSee('CSV Pending Site');
        $response->assertSee('Shared Pending');
        $response->assertDontSee('Complete Site');
        $response->assertDontSee('Shared Complete');

        Livewire::test(ManualCsvBacklogQueue::class)
            ->assertViewHas('stats', function (array $stats): bool {
                return $stats['pending_properties'] === 2
                    && $stats['pending_domains'] === 2;
            })
            ->assertViewHas('pendingItems', function (Collection $items): bool {
                $names = $items->map(fn (array $item): string => $item['property']->name)->all();

                sort($names);

                return $names === ['CSV Pending Site', 'Shared Pending'];
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

    private function attachBaseline(WebProperty $property, PropertyAnalyticsSource $source, string $importMethod): void
    {
        DomainSeoBaseline::create([
            'domain_id' => $property->primary_domain_id,
            'web_property_id' => $property->id,
            'property_analytics_source_id' => $source->id,
            'baseline_type' => 'search_console',
            'captured_at' => now(),
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
