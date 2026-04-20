<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntegrationMetaApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_integration_meta_endpoint_requires_api_authentication(): void
    {
        $this->getJson('/api/meta/integrations')
            ->assertUnauthorized();
    }

    public function test_integration_meta_endpoint_accepts_fleet_control_api_key(): void
    {
        config()->set('services.domain_monitor.fleet_control_api_key', 'fleet-token');

        $this->withHeaders([
            'Authorization' => 'Bearer fleet-token',
        ])->getJson('/api/meta/integrations')
            ->assertOk()
            ->assertJsonPath('service', 'domain-monitor')
            ->assertJsonPath('auth.scheme', 'Bearer')
            ->assertJsonPath('auth.accepted_tokens.2', 'FLEET_CONTROL_API_KEY')
            ->assertJsonPath('feeds.0.path', '/api/web-properties-summary')
            ->assertJsonPath('feeds.1.path', '/api/runtime/analytics-contexts')
            ->assertJsonPath('feeds.2.path', '/api/issues')
            ->assertJsonPath('feeds.4.path', '/api/web-properties/{slug}/astro-cutover')
            ->assertJsonPath('feeds.5.path', '/api/issues/{issue_id}/verification')
            ->assertJsonPath('feeds.6.contract_version', 2);
    }
}
