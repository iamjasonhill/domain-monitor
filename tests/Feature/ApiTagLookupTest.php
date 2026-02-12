<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\DomainTag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiTagLookupTest extends TestCase
{
    use RefreshDatabase;

    public function test_add_tag_endpoint_does_not_treat_wildcards_as_pattern(): void
    {
        config()->set('services.domain_monitor.brain_api_key', 'test-api-key');

        $domain = Domain::factory()->create();
        DomainTag::create(['name' => 'alpha']);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer test-api-key',
        ])->postJson('/api/domains/'.$domain->id.'/tags/'.urlencode('a%'), []);

        $response->assertNotFound();
    }

    public function test_tag_domains_endpoint_uses_exact_name_lookup(): void
    {
        config()->set('services.domain_monitor.brain_api_key', 'test-api-key');

        $domain = Domain::factory()->create();
        $exactTag = DomainTag::create(['name' => 'alpha']);
        $otherTag = DomainTag::create(['name' => 'alphabet']);

        $domain->tags()->sync([$otherTag->id]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer test-api-key',
        ])->getJson('/api/tags/'.urlencode('alpha%').'/domains');

        $response->assertNotFound();

        $exactResponse = $this->withHeaders([
            'Authorization' => 'Bearer test-api-key',
        ])->getJson('/api/tags/'.$exactTag->name.'/domains');

        $exactResponse->assertOk()->assertJsonCount(0, 'data');
    }
}
