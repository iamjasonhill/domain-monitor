<?php

namespace Tests\Feature;

use App\Models\AnalyticsInstallAudit;
use App\Models\Domain;
use App\Models\PropertyAnalyticsSource;
use App\Models\WebProperty;
use App\Models\WebPropertyDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RefreshMatomoInstallAuditsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_verifies_and_imports_live_matomo_install_audits(): void
    {
        config()->set('services.matomo.base_url', 'https://stats.redirection.com.au');

        $property = $this->makeProperty('tracked.example.au', 'Tracked Site');

        $source = PropertyAnalyticsSource::create([
            'web_property_id' => $property->id,
            'provider' => 'matomo',
            'external_id' => '8',
            'external_name' => 'Tracked Site',
            'is_primary' => true,
            'status' => 'active',
        ]);

        Http::fake([
            'https://tracked.example.au/' => Http::response(
                <<<'HTML'
                <html>
                    <head>
                        <script>
                            var _paq = window._paq = window._paq || [];
                            _paq.push(['setSiteId', '8']);
                            _paq.push(['setTrackerUrl', 'https://stats.redirection.com.au/matomo.php']);
                        </script>
                        <script src="https://stats.redirection.com.au/matomo.js"></script>
                    </head>
                    <body>Tracked</body>
                </html>
                HTML,
                200
            ),
        ]);

        $exitCode = Artisan::call('analytics:refresh-matomo-install-audits');

        $this->assertSame(0, $exitCode);

        $audit = AnalyticsInstallAudit::query()
            ->where('property_analytics_source_id', $source->id)
            ->firstOrFail();

        $this->assertSame('installed_match', $audit->install_verdict);
        $this->assertSame('https://tracked.example.au/', $audit->best_url);
        $this->assertSame(['8'], $audit->detected_site_ids);
        $this->assertSame(['stats.redirection.com.au'], $audit->detected_tracker_hosts);
    }

    public function test_it_can_scope_verification_to_one_domain(): void
    {
        config()->set('services.matomo.base_url', 'https://stats.redirection.com.au');

        $target = $this->makeProperty('target.example.au', 'Target');
        $other = $this->makeProperty('other.example.au', 'Other');

        PropertyAnalyticsSource::create([
            'web_property_id' => $target->id,
            'provider' => 'matomo',
            'external_id' => '18',
            'external_name' => 'Target',
            'is_primary' => true,
            'status' => 'active',
        ]);

        PropertyAnalyticsSource::create([
            'web_property_id' => $other->id,
            'provider' => 'matomo',
            'external_id' => '28',
            'external_name' => 'Other',
            'is_primary' => true,
            'status' => 'active',
        ]);

        Http::fake([
            'https://target.example.au/' => Http::response(
                "<script>var _paq=[];_paq.push(['setSiteId','18']);</script><script src=\"https://stats.redirection.com.au/matomo.js\"></script>",
                200
            ),
            'https://other.example.au/' => Http::response(
                "<script>var _paq=[];_paq.push(['setSiteId','28']);</script><script src=\"https://stats.redirection.com.au/matomo.js\"></script>",
                200
            ),
        ]);

        $exitCode = Artisan::call('analytics:refresh-matomo-install-audits', [
            '--domain' => 'target.example.au',
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertDatabaseCount('analytics_install_audits', 1);
        $this->assertDatabaseHas('analytics_install_audits', [
            'web_property_id' => $target->id,
            'install_verdict' => 'installed_match',
        ]);
        $this->assertDatabaseMissing('analytics_install_audits', [
            'web_property_id' => $other->id,
        ]);
    }

    public function test_it_fails_when_expected_tracker_host_is_not_configured(): void
    {
        config()->set('services.matomo.base_url', null);

        $property = $this->makeProperty('missing-config.example.au', 'Missing Config');

        PropertyAnalyticsSource::create([
            'web_property_id' => $property->id,
            'provider' => 'matomo',
            'external_id' => '48',
            'external_name' => 'Missing Config',
            'is_primary' => true,
            'status' => 'active',
        ]);

        $this->assertSame(1, Artisan::call('analytics:refresh-matomo-install-audits'));
        $this->assertDatabaseCount('analytics_install_audits', 0);
    }

    public function test_it_detects_same_origin_script_assets_with_variable_tracker_urls(): void
    {
        config()->set('services.matomo.base_url', 'https://stats.redirection.com.au');

        $property = $this->makeProperty('bundled.example.au', 'Bundled Site');

        $source = PropertyAnalyticsSource::create([
            'web_property_id' => $property->id,
            'provider' => 'matomo',
            'external_id' => '58',
            'external_name' => 'Bundled Site',
            'is_primary' => true,
            'status' => 'active',
        ]);

        Http::fake([
            'https://bundled.example.au/' => Http::response(
                <<<'HTML'
                <html>
                    <head>
                        <script src="/assets/app.js"></script>
                    </head>
                    <body>Bundled</body>
                </html>
                HTML,
                200
            ),
            'https://bundled.example.au/assets/app.js' => Http::response(
                <<<'JS'
                const trackerBase = '//stats.redirection.com.au/';
                var _paq = window._paq = window._paq || [];
                _paq.push(['setSiteId', '58']);
                _paq.push(['setTrackerUrl', trackerBase + 'matomo.php']);
                JS,
                200
            ),
        ]);

        $this->assertSame(0, Artisan::call('analytics:refresh-matomo-install-audits'));

        $audit = AnalyticsInstallAudit::query()
            ->where('property_analytics_source_id', $source->id)
            ->firstOrFail();

        $this->assertSame('installed_match', $audit->install_verdict);
        $this->assertSame(['58'], $audit->detected_site_ids);
        $this->assertSame(['stats.redirection.com.au'], $audit->detected_tracker_hosts);
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
            'production_url' => 'https://'.$domainName.'/',
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $domain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        return $property;
    }
}
