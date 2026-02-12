<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\DomainTag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiPaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_domains_index_is_paginated_by_default(): void
    {
        config()->set('services.domain_monitor.brain_api_key', 'test-api-key');

        Domain::factory()
            ->count(55)
            ->sequence(fn ($sequence) => ['domain' => "default-page-{$sequence->index}.example.com"])
            ->create();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer test-api-key',
        ])->getJson('/api/domains');

        $response
            ->assertOk()
            ->assertJsonCount(50, 'data')
            ->assertJsonPath('meta.per_page', 50)
            ->assertJsonPath('meta.total', 55);
    }

    public function test_domains_index_caps_per_page_to_maximum(): void
    {
        config()->set('services.domain_monitor.brain_api_key', 'test-api-key');

        Domain::factory()
            ->count(120)
            ->sequence(fn ($sequence) => ['domain' => "max-page-{$sequence->index}.example.com"])
            ->create();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer test-api-key',
        ])->getJson('/api/domains?per_page=500');

        $response
            ->assertOk()
            ->assertJsonCount(100, 'data')
            ->assertJsonPath('meta.per_page', 100)
            ->assertJsonPath('meta.total', 120);
    }

    public function test_tag_domains_endpoint_is_paginated(): void
    {
        config()->set('services.domain_monitor.brain_api_key', 'test-api-key');

        $tag = DomainTag::create([
            'name' => 'portfolio',
        ]);

        $domains = Domain::factory()
            ->count(60)
            ->sequence(fn ($sequence) => ['domain' => "tag-page-{$sequence->index}.example.com"])
            ->create();
        foreach ($domains as $domain) {
            $domain->tags()->syncWithoutDetaching([$tag->id]);
        }

        $response = $this->withHeaders([
            'Authorization' => 'Bearer test-api-key',
        ])->getJson('/api/tags/'.$tag->id.'/domains?per_page=25');

        $response
            ->assertOk()
            ->assertJsonCount(25, 'data')
            ->assertJsonPath('meta.per_page', 25)
            ->assertJsonPath('meta.total', 60);
    }
}
