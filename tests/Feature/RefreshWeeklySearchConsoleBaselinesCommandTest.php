<?php

namespace Tests\Feature;

use App\Console\Commands\RefreshWeeklySearchConsoleBaselines;
use App\Models\Domain;
use App\Models\PropertyAnalyticsSource;
use App\Models\WebProperty;
use App\Models\WebPropertyDomain;
use Illuminate\Console\OutputStyle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

class RefreshWeeklySearchConsoleBaselinesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_refreshes_weekly_baselines_for_active_matomo_mapped_properties(): void
    {
        config()->set('services.matomo.base_url', 'https://stats.redirection.com.au');
        config()->set('services.matomo.token_auth', 'test-token');

        $eligible = $this->makeProperty('weekly.example.au', 'Weekly Site');
        $this->attachMatomo($eligible, '91');

        $inactive = $this->makeProperty('inactive.example.au', 'Inactive Site', 'paused');
        $this->attachMatomo($inactive, '92');

        Artisan::shouldReceive('call')
            ->once()
            ->with('analytics:sync-search-console-baseline', [
                '--domain' => 'weekly.example.au',
                '--baseline-type' => 'weekly_checkpoint',
                '--captured-by' => 'domain-monitor-weekly',
                '--notes' => 'Scheduled weekly Search Console baseline snapshot.',
            ])
            ->andReturn(0);

        $command = app(RefreshWeeklySearchConsoleBaselines::class);
        $command->setOutput(new OutputStyle(new ArrayInput([]), new BufferedOutput));
        $command->setLaravel($this->app);

        $this->assertSame(0, $command->run(new ArrayInput([]), new BufferedOutput));
    }

    public function test_it_supports_dry_run_and_domain_scoping(): void
    {
        config()->set('services.matomo.base_url', 'https://stats.redirection.com.au');
        config()->set('services.matomo.token_auth', 'test-token');

        $eligible = $this->makeProperty('scoped-weekly.example.au', 'Scoped Weekly');
        $this->attachMatomo($eligible, '93');

        $other = $this->makeProperty('other-weekly.example.au', 'Other Weekly');
        $this->attachMatomo($other, '94');

        Artisan::shouldReceive('call')
            ->never()
            ->with('analytics:sync-search-console-baseline', \Mockery::any());

        $command = app(RefreshWeeklySearchConsoleBaselines::class);
        $command->setOutput(new OutputStyle(new ArrayInput([]), new BufferedOutput));
        $command->setLaravel($this->app);

        $this->assertSame(0, $command->run(new ArrayInput([
            '--domain' => 'scoped-weekly.example.au',
            '--dry-run' => true,
        ]), new BufferedOutput));
    }

    public function test_it_fails_when_matomo_credentials_are_missing(): void
    {
        config()->set('services.matomo.base_url', null);
        config()->set('services.matomo.token_auth', null);

        Artisan::shouldReceive('call')
            ->never()
            ->with('analytics:sync-search-console-baseline', \Mockery::any());

        $command = app(RefreshWeeklySearchConsoleBaselines::class);
        $command->setOutput(new OutputStyle(new ArrayInput([]), new BufferedOutput));
        $command->setLaravel($this->app);

        $this->assertSame(1, $command->run(new ArrayInput([]), new BufferedOutput));
    }

    private function makeProperty(string $domainName, string $propertyName, string $status = 'active'): WebProperty
    {
        $domain = Domain::factory()->create([
            'domain' => $domainName,
        ]);

        $property = WebProperty::factory()->create([
            'name' => $propertyName,
            'status' => $status,
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
}
