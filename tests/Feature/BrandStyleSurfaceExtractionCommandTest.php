<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BrandStyleSurfaceExtractionCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_extracts_a_review_only_discount_backloading_style_draft(): void
    {
        Http::fake([
            'https://discountbackloading.com.au' => Http::response(<<<'HTML'
                <html>
                    <head>
                        <title>Discount Backloading</title>
                        <meta name="description" content="Affordable backloading quotes across Australia.">
                        <meta property="og:site_name" content="Discount Backloading">
                        <style>
                            :root { --brand-orange: #f97316; --brand-brown: #7c2d12; }
                            body { font-family: "Montserrat", sans-serif; }
                        </style>
                    </head>
                    <body>
                        <h1>Save on your interstate move</h1>
                    </body>
                </html>
                HTML, 200, ['Content-Type' => 'text/html']),
        ]);

        $this->assertSame(0, Artisan::call('brand-surfaces:extract-style-draft', [
            'hostname' => 'mymoveportal.discountbackloading.com.au',
        ]));

        $output = Artisan::output();

        $this->assertStringContainsString('"hostname": "mymoveportal.discountbackloading.com.au"', $output);
        $this->assertStringContainsString('"source_marketing_domain": "discountbackloading.com.au"', $output);
        $this->assertStringContainsString('"property_slug": "discountbackloading-com-au"', $output);
        $this->assertStringContainsString('"journey_type": "mixed_quote"', $output);
        $this->assertStringContainsString('"approval_status": "needs_review"', $output);
        $this->assertStringContainsString('"accent": "#f97316"', $output);
        $this->assertStringContainsString('"body_family": "Montserrat"', $output);
        $this->assertStringContainsString('"source_type": "marketing_site_extraction"', $output);
    }

    public function test_written_extraction_drafts_are_exposed_by_draft_api_but_not_published(): void
    {
        Storage::fake('local');
        Http::fake([
            'https://discountbackloading.com.au' => Http::response(<<<'HTML'
                <html>
                    <head>
                        <meta name="description" content="Backloading quote help.">
                        <style>body { font-family: "Montserrat"; color: #f97316; }</style>
                    </head>
                    <body><h1>Discount moving quotes</h1></body>
                </html>
                HTML, 200, ['Content-Type' => 'text/html']),
        ]);
        config()->set('services.domain_monitor.moveroo_removals_api_key', 'moveroo-runtime-token');

        $this->assertSame(0, Artisan::call('brand-surfaces:extract-style-draft', [
            'hostname' => 'mymoveportal.discountbackloading.com.au',
            '--write' => true,
            '--path' => 'brand-style-drafts/discountbackloading.json',
        ]));

        Storage::disk('local')->assertExists('brand-style-drafts/discountbackloading.json');

        $this->withHeaders([
            'Authorization' => 'Bearer moveroo-runtime-token',
        ])->getJson('/api/published-brand-surface-drafts?hostname=mymoveportal.discountbackloading.com.au')
            ->assertOk()
            ->assertJsonCount(1, 'proposals')
            ->assertJsonPath('proposals.0.hostname', 'mymoveportal.discountbackloading.com.au')
            ->assertJsonPath('proposals.0.approval_status', 'needs_review')
            ->assertJsonPath('proposals.0.publish_gate.can_publish', false)
            ->assertJsonPath('proposals.0.candidate.theme.colors.accent', '#f97316')
            ->assertJsonPath('proposals.0.evidence.0.source_type', 'marketing_site_extraction');

        $this->withHeaders([
            'Authorization' => 'Bearer moveroo-runtime-token',
        ])->getJson('/api/published-brand-surfaces?hostname=mymoveportal.discountbackloading.com.au')
            ->assertOk()
            ->assertJsonCount(0, 'surfaces');
    }

    public function test_extraction_requires_a_known_published_app_hostname(): void
    {
        $this->assertSame(1, Artisan::call('brand-surfaces:extract-style-draft', [
            'hostname' => 'unknown.discountbackloading.com.au',
        ]));

        $this->assertStringContainsString(
            'No published brand-surface metadata found for unknown.discountbackloading.com.au.',
            Artisan::output(),
        );
    }
}
