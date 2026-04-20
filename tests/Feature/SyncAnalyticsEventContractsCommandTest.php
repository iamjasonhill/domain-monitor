<?php

namespace Tests\Feature;

use App\Models\AnalyticsEventContract;
use App\Models\Domain;
use App\Models\PropertyAnalyticsSource;
use App\Models\WebProperty;
use App\Models\WebPropertyDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SyncAnalyticsEventContractsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_syncs_default_and_exception_event_contracts_for_ga4_properties(): void
    {
        $movingagainDomain = Domain::factory()->create([
            'domain' => 'movingagain.com.au',
            'is_active' => true,
        ]);

        $movingagain = WebProperty::factory()->create([
            'slug' => 'movingagain-com-au',
            'name' => 'movingagain.com.au',
            'primary_domain_id' => $movingagainDomain->id,
            'production_url' => 'https://movingagain.com.au',
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $movingagain->id,
            'domain_id' => $movingagainDomain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        PropertyAnalyticsSource::create([
            'web_property_id' => $movingagain->id,
            'provider' => 'ga4',
            'external_id' => 'G-K6VBFJGYYK',
            'external_name' => 'movingagain.com.au',
            'is_primary' => true,
            'status' => 'active',
        ]);

        $moverooDomain = Domain::factory()->create([
            'domain' => 'moveroo.com.au',
            'is_active' => true,
        ]);

        $moveroo = WebProperty::factory()->create([
            'slug' => 'moveroo-com-au',
            'name' => 'moveroo.com.au',
            'primary_domain_id' => $moverooDomain->id,
            'production_url' => 'https://moveroo.com.au',
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $moveroo->id,
            'domain_id' => $moverooDomain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        PropertyAnalyticsSource::create([
            'web_property_id' => $moveroo->id,
            'provider' => 'ga4',
            'external_id' => 'G-9F3Y80LEQL',
            'external_name' => 'Moveroo',
            'is_primary' => true,
            'status' => 'active',
        ]);

        $this->assertSame(0, Artisan::call('analytics:sync-event-contracts'));

        $this->assertDatabaseHas('analytics_event_contracts', [
            'key' => 'shared-ga4-baseline-v1',
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('analytics_event_contracts', [
            'key' => 'moveroo-full-funnel-v1',
            'status' => 'active',
        ]);

        $sharedContract = AnalyticsEventContract::query()->where('key', 'shared-ga4-baseline-v1')->firstOrFail();
        $moverooContract = AnalyticsEventContract::query()->where('key', 'moveroo-full-funnel-v1')->firstOrFail();

        $this->assertDatabaseHas('web_property_event_contracts', [
            'web_property_id' => $movingagain->id,
            'analytics_event_contract_id' => $sharedContract->id,
            'rollout_status' => 'defined',
            'is_primary' => true,
        ]);

        $this->assertDatabaseMissing('web_property_event_contracts', [
            'web_property_id' => $movingagain->id,
            'analytics_event_contract_id' => $moverooContract->id,
        ]);

        $this->assertDatabaseHas('web_property_event_contracts', [
            'web_property_id' => $moveroo->id,
            'analytics_event_contract_id' => $moverooContract->id,
            'rollout_status' => 'defined',
            'is_primary' => true,
        ]);

        $this->assertDatabaseMissing('web_property_event_contracts', [
            'web_property_id' => $moveroo->id,
            'analytics_event_contract_id' => $sharedContract->id,
        ]);
    }
}
