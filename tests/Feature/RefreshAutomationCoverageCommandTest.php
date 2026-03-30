<?php

namespace Tests\Feature;

use App\Console\Commands\RefreshAutomationCoverage;
use App\Models\AnalyticsInstallAudit;
use App\Models\Domain;
use App\Models\DomainSeoBaseline;
use App\Models\PropertyAnalyticsSource;
use App\Models\PropertyRepository;
use App\Models\SearchConsoleCoverageStatus;
use App\Models\WebProperty;
use App\Models\WebPropertyDomain;
use Illuminate\Console\OutputStyle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

class RefreshAutomationCoverageCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_refreshes_coverage_and_syncs_baselines_for_ready_domains(): void
    {
        $needsBaseline = $this->makeProperty('baseline.example.au', 'Needs Baseline');
        $this->attachRepository($needsBaseline);
        $needsBaselineSource = $this->attachMatomo($needsBaseline, '35');
        $this->attachInstallAudit($needsBaseline, $needsBaselineSource);
        $this->attachCoverage($needsBaseline, $needsBaselineSource, now()->subDay()->toDateString());

        $complete = $this->makeProperty('complete.example.au', 'Complete Site');
        $this->attachRepository($complete);
        $completeSource = $this->attachMatomo($complete, '36');
        $this->attachInstallAudit($complete, $completeSource);
        $this->attachCoverage($complete, $completeSource, now()->subDay()->toDateString());
        $this->attachBaseline($complete, $completeSource, 'matomo_plus_manual_csv');

        Artisan::shouldReceive('call')
            ->once()
            ->with('analytics:sync-search-console-coverage', [])
            ->andReturn(0);

        Artisan::shouldReceive('call')
            ->once()
            ->with('analytics:sync-search-console-baseline', ['--domain' => 'baseline.example.au'])
            ->andReturn(0);

        Artisan::shouldReceive('call')
            ->once()
            ->with('coverage:sync-tags', [])
            ->andReturn(0);

        Artisan::shouldReceive('call')
            ->once()
            ->with('domains:refresh-should-fix', [])
            ->andReturn(0);

        $command = app(RefreshAutomationCoverage::class);
        $command->setOutput(new OutputStyle(new ArrayInput([]), new BufferedOutput));
        $command->setLaravel($this->app);

        $this->assertSame(0, $command->run(new ArrayInput([]), new BufferedOutput));
    }

    public function test_it_scopes_to_one_domain_and_skips_post_sync_steps_when_requested(): void
    {
        $needsBaseline = $this->makeProperty('scoped.example.au', 'Scoped Site');
        $this->attachRepository($needsBaseline);
        $needsBaselineSource = $this->attachMatomo($needsBaseline, '37');
        $this->attachInstallAudit($needsBaseline, $needsBaselineSource);
        $this->attachCoverage($needsBaseline, $needsBaselineSource, now()->subDay()->toDateString());

        $other = $this->makeProperty('other.example.au', 'Other Site');
        $this->attachRepository($other);
        $otherSource = $this->attachMatomo($other, '38');
        $this->attachInstallAudit($other, $otherSource);
        $this->attachCoverage($other, $otherSource, now()->subDay()->toDateString());

        Artisan::shouldReceive('call')
            ->once()
            ->with('analytics:sync-search-console-coverage', ['--domain' => 'scoped.example.au'])
            ->andReturn(0);

        Artisan::shouldReceive('call')
            ->once()
            ->with('analytics:sync-search-console-baseline', ['--domain' => 'scoped.example.au'])
            ->andReturn(0);

        Artisan::shouldReceive('call')
            ->never()
            ->with('coverage:sync-tags', ['--domain' => 'scoped.example.au']);

        Artisan::shouldReceive('call')
            ->never()
            ->with('domains:refresh-should-fix');

        $command = app(RefreshAutomationCoverage::class);
        $command->setOutput(new OutputStyle(new ArrayInput([]), new BufferedOutput));
        $command->setLaravel($this->app);

        $this->assertSame(0, $command->run(new ArrayInput([
            '--domain' => 'scoped.example.au',
            '--skip-tags' => true,
            '--skip-should-fix' => true,
        ]), new BufferedOutput));
    }

    public function test_it_scopes_all_downstream_steps_when_a_domain_is_provided(): void
    {
        $needsBaseline = $this->makeProperty('scoped-default.example.au', 'Scoped Default');
        $this->attachRepository($needsBaseline);
        $needsBaselineSource = $this->attachMatomo($needsBaseline, '39');
        $this->attachInstallAudit($needsBaseline, $needsBaselineSource);
        $this->attachCoverage($needsBaseline, $needsBaselineSource, now()->subDay()->toDateString());

        Artisan::shouldReceive('call')
            ->once()
            ->with('analytics:sync-search-console-coverage', ['--domain' => 'scoped-default.example.au'])
            ->andReturn(0);

        Artisan::shouldReceive('call')
            ->once()
            ->with('analytics:sync-search-console-baseline', ['--domain' => 'scoped-default.example.au'])
            ->andReturn(0);

        Artisan::shouldReceive('call')
            ->once()
            ->with('coverage:sync-tags', ['--domain' => 'scoped-default.example.au'])
            ->andReturn(0);

        Artisan::shouldReceive('call')
            ->once()
            ->with('domains:refresh-should-fix', ['--domain' => 'scoped-default.example.au'])
            ->andReturn(0);

        $command = app(RefreshAutomationCoverage::class);
        $command->setOutput(new OutputStyle(new ArrayInput([]), new BufferedOutput));
        $command->setLaravel($this->app);

        $this->assertSame(0, $command->run(new ArrayInput([
            '--domain' => 'scoped-default.example.au',
        ]), new BufferedOutput));
    }

    public function test_it_fails_fast_when_coverage_refresh_fails(): void
    {
        Artisan::shouldReceive('call')
            ->once()
            ->with('analytics:sync-search-console-coverage', [])
            ->andReturn(1);

        Artisan::shouldReceive('call')
            ->never()
            ->with('analytics:sync-search-console-baseline', \Mockery::any());

        Artisan::shouldReceive('call')
            ->never()
            ->with('coverage:sync-tags', \Mockery::any());

        Artisan::shouldReceive('call')
            ->never()
            ->with('domains:refresh-should-fix', \Mockery::any());

        $command = app(RefreshAutomationCoverage::class);
        $command->setOutput(new OutputStyle(new ArrayInput([]), new BufferedOutput));
        $command->setLaravel($this->app);

        $this->assertSame(1, $command->run(new ArrayInput([]), new BufferedOutput));
    }

    public function test_it_returns_failure_when_any_baseline_sync_fails(): void
    {
        $needsBaseline = $this->makeProperty('baseline-fail.example.au', 'Baseline Failure');
        $this->attachRepository($needsBaseline);
        $needsBaselineSource = $this->attachMatomo($needsBaseline, '40');
        $this->attachInstallAudit($needsBaseline, $needsBaselineSource);
        $this->attachCoverage($needsBaseline, $needsBaselineSource, now()->subDay()->toDateString());

        Artisan::shouldReceive('call')
            ->once()
            ->with('analytics:sync-search-console-coverage', [])
            ->andReturn(0);

        Artisan::shouldReceive('call')
            ->once()
            ->with('analytics:sync-search-console-baseline', ['--domain' => 'baseline-fail.example.au'])
            ->andReturn(1);

        Artisan::shouldReceive('call')
            ->once()
            ->with('coverage:sync-tags', [])
            ->andReturn(0);

        Artisan::shouldReceive('call')
            ->once()
            ->with('domains:refresh-should-fix', [])
            ->andReturn(0);

        $command = app(RefreshAutomationCoverage::class);
        $command->setOutput(new OutputStyle(new ArrayInput([]), new BufferedOutput));
        $command->setLaravel($this->app);

        $this->assertSame(1, $command->run(new ArrayInput([]), new BufferedOutput));
    }

    public function test_it_fails_when_tag_sync_fails(): void
    {
        Artisan::shouldReceive('call')
            ->once()
            ->with('analytics:sync-search-console-coverage', [])
            ->andReturn(0);

        Artisan::shouldReceive('call')
            ->once()
            ->with('coverage:sync-tags', [])
            ->andReturn(1);

        Artisan::shouldReceive('call')
            ->never()
            ->with('domains:refresh-should-fix', \Mockery::any());

        $command = app(RefreshAutomationCoverage::class);
        $command->setOutput(new OutputStyle(new ArrayInput([]), new BufferedOutput));
        $command->setLaravel($this->app);

        $this->assertSame(1, $command->run(new ArrayInput([]), new BufferedOutput));
    }

    public function test_it_fails_when_should_fix_refresh_fails(): void
    {
        Artisan::shouldReceive('call')
            ->once()
            ->with('analytics:sync-search-console-coverage', [])
            ->andReturn(0);

        Artisan::shouldReceive('call')
            ->once()
            ->with('coverage:sync-tags', [])
            ->andReturn(0);

        Artisan::shouldReceive('call')
            ->once()
            ->with('domains:refresh-should-fix', [])
            ->andReturn(1);

        $command = app(RefreshAutomationCoverage::class);
        $command->setOutput(new OutputStyle(new ArrayInput([]), new BufferedOutput));
        $command->setLaravel($this->app);

        $this->assertSame(1, $command->run(new ArrayInput([]), new BufferedOutput));
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

    private function attachCoverage(WebProperty $property, PropertyAnalyticsSource $source, ?string $latestMetricDate): void
    {
        SearchConsoleCoverageStatus::create([
            'domain_id' => $property->primary_domain_id,
            'web_property_id' => $property->id,
            'property_analytics_source_id' => $source->id,
            'source_provider' => 'matomo',
            'matomo_site_id' => $source->external_id,
            'matomo_site_name' => $property->name,
            'mapping_state' => 'domain_property',
            'property_uri' => 'sc-domain:'.$property->primaryDomainName(),
            'property_type' => 'domain',
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
            'baseline_type' => 'manual_checkpoint',
            'captured_at' => now()->subDay(),
            'source_provider' => 'matomo',
            'matomo_site_id' => $source->external_id,
            'search_console_property_uri' => 'sc-domain:'.$property->primaryDomainName(),
            'search_type' => 'web',
            'import_method' => $importMethod,
            'clicks' => 10,
            'impressions' => 100,
            'ctr' => 0.1,
            'average_position' => 12.5,
        ]);
    }
}
