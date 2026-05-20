<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\FleetTechnicalSeoAuditRun;
use App\Models\WebProperty;
use App\Models\WebPropertyDomain;
use App\Services\FleetTechnicalSeoAuditRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class RunFleetTechnicalSeoEstateAuditCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_lists_limited_eligible_properties_without_creating_audit_runs(): void
    {
        $first = $this->makeProperty('alpha-site', 'alpha.example');
        $second = $this->makeProperty('bravo-site', 'bravo.example');
        $this->makeProperty('paused-site', 'paused.example', ['status' => 'paused']);

        $exitCode = Artisan::call('monitoring:run-fleet-technical-seo-estate-audit', [
            '--dry-run' => true,
            '--limit' => 1,
        ]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('[dry-run] alpha-site', $output);
        $this->assertStringNotContainsString($second->slug, $output);
        $this->assertStringContainsString('Selected 1 eligible web property', $output);
        $this->assertDatabaseCount('fleet_technical_seo_audit_runs', 0);
        $this->assertTrue($first->exists);
    }

    public function test_command_filters_ineligible_properties_and_runs_selected_properties_sequentially(): void
    {
        $eligible = $this->makeProperty('eligible-site', 'eligible.example');
        $this->makeProperty('domain-asset-site', 'asset.example', ['property_type' => 'domain_asset']);
        $runner = new FleetTechnicalSeoEstateAuditRunnerFake;
        $this->app->instance(FleetTechnicalSeoAuditRunner::class, $runner);

        $exitCode = Artisan::call('monitoring:run-fleet-technical-seo-estate-audit', [
            '--limit' => 10,
            '--url-cap' => 7,
        ]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Running Fleet technical SEO audit for [eligible-site]', $output);
        $this->assertStringNotContainsString('domain-asset-site', $output);
        $this->assertSame([
            ['slug' => $eligible->slug, 'url_cap' => 7, 'trigger_type' => 'operator_requested_estate', 'promote_findings' => false],
        ], $runner->calls);
        $this->assertDatabaseCount('fleet_technical_seo_audit_runs', 1);
    }

    public function test_profile_defaults_set_url_cap_and_trigger_type(): void
    {
        $property = $this->makeProperty('smoke-site', 'smoke.example');
        $runner = new FleetTechnicalSeoEstateAuditRunnerFake;
        $this->app->instance(FleetTechnicalSeoAuditRunner::class, $runner);

        $exitCode = Artisan::call('monitoring:run-fleet-technical-seo-estate-audit', [
            '--profile' => 'fleet_technical_seo_smoke',
            '--limit' => 1,
        ]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('profile [fleet_technical_seo_smoke]', $output);
        $this->assertSame([
            ['slug' => $property->slug, 'url_cap' => 3, 'trigger_type' => 'fleet_technical_seo_smoke', 'promote_findings' => false],
        ], $runner->calls);
    }

    public function test_profile_url_cap_can_be_overridden(): void
    {
        $property = $this->makeProperty('deep-site', 'deep.example');
        $runner = new FleetTechnicalSeoEstateAuditRunnerFake;
        $this->app->instance(FleetTechnicalSeoAuditRunner::class, $runner);

        $exitCode = Artisan::call('monitoring:run-fleet-technical-seo-estate-audit', [
            '--profile' => 'fleet_technical_seo_deep',
            '--limit' => 1,
            '--url-cap' => 9,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertSame([
            ['slug' => $property->slug, 'url_cap' => 9, 'trigger_type' => 'fleet_technical_seo_deep', 'promote_findings' => false],
        ], $runner->calls);
    }

    public function test_promote_findings_option_is_passed_to_estate_runner(): void
    {
        $property = $this->makeProperty('promoted-site', 'promoted.example');
        $runner = new FleetTechnicalSeoEstateAuditRunnerFake;
        $this->app->instance(FleetTechnicalSeoAuditRunner::class, $runner);

        $exitCode = Artisan::call('monitoring:run-fleet-technical-seo-estate-audit', [
            '--property' => [$property->slug],
            '--promote-findings' => true,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertSame([
            ['slug' => $property->slug, 'url_cap' => 25, 'trigger_type' => 'operator_requested_estate', 'promote_findings' => true],
        ], $runner->calls);
    }

    public function test_profile_batches_select_most_stale_eligible_properties_first(): void
    {
        $fresh = $this->makeProperty('fresh-site', 'fresh.example');
        $neverAudited = $this->makeProperty('never-audited-site', 'never-audited.example');
        $stale = $this->makeProperty('stale-site', 'stale.example');
        $otherProfileOnly = $this->makeProperty('other-profile-site', 'other-profile.example');

        FleetTechnicalSeoAuditRun::factory()->create([
            'web_property_id' => $fresh->id,
            'trigger_type' => 'fleet_technical_seo_smoke',
            'started_at' => now()->subHour(),
            'finished_at' => now()->subHour()->addMinute(),
        ]);
        FleetTechnicalSeoAuditRun::factory()->create([
            'web_property_id' => $stale->id,
            'trigger_type' => 'fleet_technical_seo_smoke',
            'started_at' => now()->subDays(6),
            'finished_at' => now()->subDays(6)->addMinute(),
        ]);
        FleetTechnicalSeoAuditRun::factory()->create([
            'web_property_id' => $otherProfileOnly->id,
            'trigger_type' => 'fleet_technical_seo_deep',
            'started_at' => now()->subMinutes(30),
            'finished_at' => now()->subMinutes(29),
        ]);

        $runner = new FleetTechnicalSeoEstateAuditRunnerFake;
        $this->app->instance(FleetTechnicalSeoAuditRunner::class, $runner);

        $exitCode = Artisan::call('monitoring:run-fleet-technical-seo-estate-audit', [
            '--profile' => 'fleet_technical_seo_smoke',
            '--limit' => 3,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertSame([
            ['slug' => $neverAudited->slug, 'url_cap' => 3, 'trigger_type' => 'fleet_technical_seo_smoke', 'promote_findings' => false],
            ['slug' => $otherProfileOnly->slug, 'url_cap' => 3, 'trigger_type' => 'fleet_technical_seo_smoke', 'promote_findings' => false],
            ['slug' => $stale->slug, 'url_cap' => 3, 'trigger_type' => 'fleet_technical_seo_smoke', 'promote_findings' => false],
        ], $runner->calls);
    }

    public function test_explicit_selectors_do_not_use_freshness_rotation(): void
    {
        $target = $this->makeProperty('target-site', 'target.example');
        $stale = $this->makeProperty('stale-site', 'stale.example');

        FleetTechnicalSeoAuditRun::factory()->create([
            'web_property_id' => $target->id,
            'trigger_type' => 'fleet_technical_seo_smoke',
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        $runner = new FleetTechnicalSeoEstateAuditRunnerFake;
        $this->app->instance(FleetTechnicalSeoAuditRunner::class, $runner);

        $exitCode = Artisan::call('monitoring:run-fleet-technical-seo-estate-audit', [
            '--profile' => 'fleet_technical_seo_smoke',
            '--property' => [$target->slug, $stale->slug],
            '--limit' => 10,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertSame([
            ['slug' => $stale->slug, 'url_cap' => 3, 'trigger_type' => 'fleet_technical_seo_smoke', 'promote_findings' => false],
            ['slug' => $target->slug, 'url_cap' => 3, 'trigger_type' => 'fleet_technical_seo_smoke', 'promote_findings' => false],
        ], $runner->calls);
    }

    public function test_unknown_profile_fails_before_auditing(): void
    {
        $this->makeProperty('profile-site', 'profile.example');
        $runner = new FleetTechnicalSeoEstateAuditRunnerFake;
        $this->app->instance(FleetTechnicalSeoAuditRunner::class, $runner);

        $exitCode = Artisan::call('monitoring:run-fleet-technical-seo-estate-audit', [
            '--profile' => 'fleet_technical_seo_nope',
        ]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Unknown Fleet technical SEO audit profile', Artisan::output());
        $this->assertSame([], $runner->calls);
    }

    public function test_command_can_scope_to_a_domain_selector(): void
    {
        $target = $this->makeProperty('target-site', 'target.example');
        $this->makeProperty('other-site', 'other.example');
        $runner = new FleetTechnicalSeoEstateAuditRunnerFake;
        $this->app->instance(FleetTechnicalSeoAuditRunner::class, $runner);

        $exitCode = Artisan::call('monitoring:run-fleet-technical-seo-estate-audit', [
            '--domain' => ['target.example'],
            '--limit' => 10,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('target-site', Artisan::output());
        $this->assertSame([
            ['slug' => $target->slug, 'url_cap' => 25, 'trigger_type' => 'operator_requested_estate', 'promote_findings' => false],
        ], $runner->calls);
        $this->assertDatabaseCount('fleet_technical_seo_audit_runs', 1);
    }

    public function test_continue_on_failure_runs_remaining_properties_and_reports_failure_exit(): void
    {
        $first = $this->makeProperty('first-site', 'first.example');
        $second = $this->makeProperty('second-site', 'second.example');
        $runner = new FleetTechnicalSeoEstateAuditRunnerFake;
        $runner->failures[$first->slug] = 'fixture failure';
        $this->app->instance(FleetTechnicalSeoAuditRunner::class, $runner);

        $exitCode = Artisan::call('monitoring:run-fleet-technical-seo-estate-audit', [
            '--limit' => 10,
            '--continue-on-failure' => true,
        ]);
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Failed [first-site]: fixture failure', $output);
        $this->assertStringContainsString('Completed [second-site]', $output);
        $this->assertSame([
            ['slug' => $first->slug, 'url_cap' => 25, 'trigger_type' => 'operator_requested_estate', 'promote_findings' => false],
            ['slug' => $second->slug, 'url_cap' => 25, 'trigger_type' => 'operator_requested_estate', 'promote_findings' => false],
        ], $runner->calls);
        $this->assertDatabaseCount('fleet_technical_seo_audit_runs', 1);
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
}

class FleetTechnicalSeoEstateAuditRunnerFake extends FleetTechnicalSeoAuditRunner
{
    /**
     * @var list<array{slug: string, url_cap: int, trigger_type: string, promote_findings: bool}>
     */
    public array $calls = [];

    /**
     * @var array<string, string>
     */
    public array $failures = [];

    public function __construct() {}

    public function run(WebProperty $property, int $urlCap = 25, string $triggerType = 'manual', bool $promoteFindings = false): FleetTechnicalSeoAuditRun
    {
        $this->calls[] = [
            'slug' => $property->slug,
            'url_cap' => $urlCap,
            'trigger_type' => $triggerType,
            'promote_findings' => $promoteFindings,
        ];

        if (isset($this->failures[$property->slug])) {
            throw new \RuntimeException($this->failures[$property->slug]);
        }

        return FleetTechnicalSeoAuditRun::factory()->create([
            'web_property_id' => $property->id,
            'trigger_type' => $triggerType,
            'url_cap' => $urlCap,
        ]);
    }
}
