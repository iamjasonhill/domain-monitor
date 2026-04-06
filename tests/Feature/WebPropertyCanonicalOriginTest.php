<?php

namespace Tests\Feature;

use App\Livewire\WebPropertyDetail;
use App\Models\Domain;
use App\Models\User;
use App\Models\WebProperty;
use App\Models\WebPropertyDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class WebPropertyCanonicalOriginTest extends TestCase
{
    use RefreshDatabase;

    public function test_property_detail_can_save_canonical_origin_policy(): void
    {
        $user = User::factory()->create();
        $primaryDomain = Domain::factory()->create([
            'domain' => 'movingagain.com.au',
            'is_active' => true,
        ]);
        $subdomain = Domain::factory()->create([
            'domain' => 'quotes.movingagain.com.au',
            'is_active' => true,
        ]);

        $property = WebProperty::factory()->create([
            'slug' => 'movingagain-site',
            'name' => 'Moving Again',
            'primary_domain_id' => $primaryDomain->id,
            'production_url' => 'https://movingagain.com.au',
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $primaryDomain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $subdomain->id,
            'usage_type' => 'subdomain',
            'is_canonical' => false,
        ]);

        Livewire::actingAs($user)
            ->test(WebPropertyDetail::class, ['propertySlug' => 'movingagain-site'])
            ->set('canonicalOriginPolicy', 'known')
            ->set('canonicalOriginScheme', 'https')
            ->set('canonicalOriginHost', 'movingagain.com.au')
            ->set('canonicalOriginEnforcementEligible', true)
            ->set('canonicalOriginExcludedSubdomainsText', "cartransport.movingagain.com.au\nquotesbookings.movingagain.com.au")
            ->set('canonicalOriginSitemapPolicyKnown', true)
            ->call('saveCanonicalOriginPolicy')
            ->assertHasNoErrors()
            ->assertSee('property_only')
            ->assertSee('quotes.movingagain.com.au');

        $property->refresh();

        $this->assertSame('https', $property->canonical_origin_scheme);
        $this->assertSame('movingagain.com.au', $property->canonical_origin_host);
        $this->assertSame('known', $property->canonical_origin_policy);
        $this->assertTrue($property->canonical_origin_enforcement_eligible);
        $this->assertSame(
            ['cartransport.movingagain.com.au', 'quotesbookings.movingagain.com.au'],
            $property->canonical_origin_excluded_subdomains
        );
        $this->assertTrue($property->canonical_origin_sitemap_policy_known);
    }

    public function test_web_property_show_exposes_safe_fallback_canonical_origin_without_enabling_enforcement(): void
    {
        config()->set('services.domain_monitor.brain_api_key', 'test-api-key');

        $primaryDomain = Domain::factory()->create([
            'domain' => 'movingagain.com.au',
            'is_active' => true,
        ]);
        $subdomain = Domain::factory()->create([
            'domain' => 'cartransport.movingagain.com.au',
            'is_active' => true,
        ]);

        $property = WebProperty::factory()->create([
            'slug' => 'movingagain-site',
            'name' => 'Moving Again',
            'primary_domain_id' => $primaryDomain->id,
            'production_url' => 'https://movingagain.com.au/services',
            'canonical_origin_policy' => 'unknown',
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $primaryDomain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $subdomain->id,
            'usage_type' => 'subdomain',
            'is_canonical' => false,
        ]);

        $this->withHeaders([
            'Authorization' => 'Bearer test-api-key',
        ])->getJson('/api/web-properties/movingagain-site')
            ->assertOk()
            ->assertJsonPath('data.canonical_origin.scheme', 'https')
            ->assertJsonPath('data.canonical_origin.host', 'movingagain.com.au')
            ->assertJsonPath('data.canonical_origin.base_url', 'https://movingagain.com.au')
            ->assertJsonPath('data.canonical_origin.policy', 'unknown')
            ->assertJsonPath('data.canonical_origin.scope', 'property_only')
            ->assertJsonPath('data.canonical_origin.enforcement_eligible', false)
            ->assertJsonPath('data.canonical_origin.owned_subdomains.0', 'cartransport.movingagain.com.au')
            ->assertJsonPath('data.canonical_origin.excluded_subdomains', [])
            ->assertJsonPath('data.canonical_origin.sitemap_policy_known', false);
    }

    public function test_property_detail_rejects_unrelated_canonical_hosts_and_invalid_excluded_subdomains(): void
    {
        $user = User::factory()->create();
        $primaryDomain = Domain::factory()->create([
            'domain' => 'movingagain.com.au',
            'is_active' => true,
        ]);

        $property = WebProperty::factory()->create([
            'slug' => 'movingagain-site',
            'name' => 'Moving Again',
            'primary_domain_id' => $primaryDomain->id,
            'production_url' => 'https://movingagain.com.au',
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $primaryDomain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        Livewire::actingAs($user)
            ->test(WebPropertyDetail::class, ['propertySlug' => 'movingagain-site'])
            ->set('canonicalOriginPolicy', 'known')
            ->set('canonicalOriginScheme', 'https')
            ->set('canonicalOriginHost', 'unrelated.example.com')
            ->set('canonicalOriginEnforcementEligible', true)
            ->set('canonicalOriginExcludedSubdomainsText', "quotes.movingagain.com.au\noutside.example.com")
            ->call('saveCanonicalOriginPolicy')
            ->assertHasErrors([
                'canonicalOriginHost',
                'canonicalOriginExcludedSubdomains',
            ]);

        $property->refresh();

        $this->assertNull($property->canonical_origin_scheme);
        $this->assertNull($property->canonical_origin_host);
        $this->assertSame('unknown', $property->canonical_origin_policy);
        $this->assertFalse($property->canonical_origin_enforcement_eligible);
    }

    public function test_property_detail_rejects_excluded_subdomains_without_a_canonical_host(): void
    {
        $user = User::factory()->create();
        $primaryDomain = Domain::factory()->create([
            'domain' => 'movingagain.com.au',
            'is_active' => true,
        ]);

        $property = WebProperty::factory()->create([
            'slug' => 'movingagain-site',
            'name' => 'Moving Again',
            'primary_domain_id' => $primaryDomain->id,
            'production_url' => 'https://movingagain.com.au',
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $primaryDomain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        Livewire::actingAs($user)
            ->test(WebPropertyDetail::class, ['propertySlug' => 'movingagain-site'])
            ->set('canonicalOriginPolicy', 'unknown')
            ->set('canonicalOriginScheme', null)
            ->set('canonicalOriginHost', null)
            ->set('canonicalOriginExcludedSubdomainsText', 'quotes.movingagain.com.au')
            ->call('saveCanonicalOriginPolicy')
            ->assertHasErrors(['canonicalOriginExcludedSubdomains']);

        $property->refresh();

        $this->assertNull($property->canonical_origin_host);
        $this->assertNull($property->canonical_origin_excluded_subdomains);
    }

    public function test_property_detail_can_link_an_owned_subdomain(): void
    {
        $user = User::factory()->create();
        $primaryDomain = Domain::factory()->create([
            'domain' => 'movingagain.com.au',
            'is_active' => true,
        ]);

        $property = WebProperty::factory()->create([
            'slug' => 'movingagain-site',
            'name' => 'Moving Again',
            'primary_domain_id' => $primaryDomain->id,
            'production_url' => 'https://movingagain.com.au',
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $primaryDomain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        Livewire::actingAs($user)
            ->test(WebPropertyDetail::class, ['propertySlug' => 'movingagain-site'])
            ->set('linkedSubdomainHost', 'quoting.movingagain.com.au')
            ->set('linkedSubdomainNotes', 'Separate quoting surface')
            ->call('saveLinkedSubdomain')
            ->assertHasNoErrors()
            ->assertSee('quoting.movingagain.com.au');

        $linkedDomain = Domain::query()->where('domain', 'quoting.movingagain.com.au')->first();

        $this->assertNotNull($linkedDomain);
        $this->assertDatabaseHas('web_property_domains', [
            'web_property_id' => $property->id,
            'domain_id' => $linkedDomain->id,
            'usage_type' => 'subdomain',
            'is_canonical' => false,
            'notes' => 'Separate quoting surface',
        ]);
    }

    public function test_property_detail_rejects_owned_subdomain_outside_property_surface(): void
    {
        $user = User::factory()->create();
        $primaryDomain = Domain::factory()->create([
            'domain' => 'movingagain.com.au',
            'is_active' => true,
        ]);

        $property = WebProperty::factory()->create([
            'slug' => 'movingagain-site',
            'name' => 'Moving Again',
            'primary_domain_id' => $primaryDomain->id,
            'production_url' => 'https://movingagain.com.au',
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $primaryDomain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        Livewire::actingAs($user)
            ->test(WebPropertyDetail::class, ['propertySlug' => 'movingagain-site'])
            ->set('linkedSubdomainHost', 'quoting.otherbrand.com.au')
            ->call('saveLinkedSubdomain')
            ->assertHasErrors(['linkedSubdomainHost']);

        $this->assertDatabaseMissing('domains', [
            'domain' => 'quoting.otherbrand.com.au',
        ]);
    }

    public function test_property_detail_reuses_existing_domain_when_linking_owned_subdomain(): void
    {
        $user = User::factory()->create();
        $primaryDomain = Domain::factory()->create([
            'domain' => 'movingagain.com.au',
            'is_active' => true,
        ]);
        $existingSubdomain = Domain::factory()->create([
            'domain' => 'quoting.movingagain.com.au',
            'is_active' => true,
        ]);

        $property = WebProperty::factory()->create([
            'slug' => 'movingagain-site',
            'name' => 'Moving Again',
            'primary_domain_id' => $primaryDomain->id,
            'production_url' => 'https://movingagain.com.au',
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $primaryDomain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        Livewire::actingAs($user)
            ->test(WebPropertyDetail::class, ['propertySlug' => 'movingagain-site'])
            ->set('linkedSubdomainHost', 'quoting.movingagain.com.au')
            ->call('saveLinkedSubdomain')
            ->assertHasNoErrors();

        $this->assertSame(1, Domain::query()->where('domain', 'quoting.movingagain.com.au')->count());
        $this->assertDatabaseHas('web_property_domains', [
            'web_property_id' => $property->id,
            'domain_id' => $existingSubdomain->id,
            'usage_type' => 'subdomain',
        ]);
    }
}
