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

    public function test_integration_meta_endpoint_accepts_dedicated_moveroo_removals_api_key(): void
    {
        config()->set('services.domain_monitor.moveroo_removals_api_key', 'moveroo-runtime-token');

        $this->withHeaders([
            'Authorization' => 'Bearer moveroo-runtime-token',
        ])->getJson('/api/meta/integrations')
            ->assertOk()
            ->assertJsonPath('service', 'domain-monitor')
            ->assertJsonPath('auth.scheme', 'Bearer')
            ->assertJsonPath('auth.accepted_tokens.3', 'MOVEROO_REMOVALS_API_KEY')
            ->assertJsonPath('feeds.0.path', '/api/web-properties-summary')
            ->assertJsonPath('feeds.1.path', '/api/runtime/analytics-contexts')
            ->assertJsonPath('feeds.2.path', '/api/published-brand-surfaces')
            ->assertJsonPath('feeds.2.query_parameters.hostname', 'optional hostname filter; still constrained by the pilot host allowlist')
            ->assertJsonPath('feeds.3.path', '/api/issues')
            ->assertJsonPath('feeds.3.contract_version', 2)
            ->assertJsonPath('feeds.3.query_parameters.fleet_focus', 'optional boolean filter for Fleet-focused properties only')
            ->assertJsonPath('feeds.5.path', '/api/web-properties/{slug}/astro-cutover')
            ->assertJsonPath('feeds.6.path', '/api/web-properties/{slug}/live-seo-verification')
            ->assertJsonPath('feeds.6.query_parameters.measurement_key', 'optional MM-Google or Search Intelligence measurement key; reused as verification_key when present')
            ->assertJsonPath('feeds.6.query_parameters.expected_canonical', 'optional absolute canonical URL expected on the live page')
            ->assertJsonPath('feeds.6.verdicts.0', 'passes_live_verification')
            ->assertJsonPath('feeds.6.verdicts.2', 'inconclusive')
            ->assertJsonPath('feeds.7.path', '/api/issues/{issue_id}/verification')
            ->assertJsonPath('feeds.8.contract_version', 2);
    }
}
