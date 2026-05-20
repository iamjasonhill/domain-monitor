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

    public function test_it_extracts_prominent_brand_tokens_from_same_origin_stylesheets(): void
    {
        Http::fake([
            'https://supercheapcartransport.com.au' => Http::response(<<<'HTML'
                <html>
                    <head>
                        <title>Super Cheap Car Transport</title>
                        <meta name="theme-color" content="#c43211">
                        <meta name="description" content="Cheap interstate car transport across Australia.">
                        <link rel="stylesheet" href="/assets/site.css">
                    </head>
                    <body class="scct-theme-body">
                        <h1>Super cheap car transport across Australia.</h1>
                    </body>
                </html>
                HTML, 200, ['Content-Type' => 'text/html']),
            'https://supercheapcartransport.com.au/assets/site.css' => Http::response(<<<'CSS'
                .scct-theme-body {
                    --scct-bg: #f6f0e6;
                    --scct-paper: #fffaf2;
                    --scct-ink: #1e2426;
                    --scct-soft: rgba(30, 36, 38, .72);
                    --scct-red: #c43211;
                    --scct-red-deep: #a6290c;
                    --scct-charcoal: #20282d;
                    --scct-line: rgba(30, 36, 38, .12);
                    font-family: "Manrope Variable", Manrope, sans-serif;
                    color: var(--scct-ink);
                    background: linear-gradient(180deg, #fbf7f0, var(--scct-bg));
                }
                .scct-primary {
                    background: linear-gradient(180deg, #e0522c, var(--scct-red));
                    color: #fff;
                }
                .scct-secondary {
                    background: var(--scct-charcoal);
                    color: #fff;
                }
                .scct-card {
                    background: var(--scct-paper);
                    border: 1px solid var(--scct-line);
                }
                CSS, 200, ['Content-Type' => 'text/css']),
        ]);

        $this->assertSame(0, Artisan::call('brand-surfaces:extract-style-draft', [
            'hostname' => 'portal.supercheapcartransport.com.au',
        ]));

        $draft = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('#c43211', data_get($draft, 'candidate.theme.colors.accent'));
        $this->assertSame('#a6290c', data_get($draft, 'candidate.theme.colors.accent_strong'));
        $this->assertSame('#f6f0e6', data_get($draft, 'candidate.theme.colors.background'));
        $this->assertSame('#fffaf2', data_get($draft, 'candidate.theme.colors.surface'));
        $this->assertSame('#1e2426', data_get($draft, 'candidate.theme.colors.text'));
        $this->assertSame('rgba(30, 36, 38, .72)', data_get($draft, 'candidate.theme.colors.muted_text'));
        $this->assertSame('Manrope', data_get($draft, 'candidate.theme.fonts.body_family'));
        $this->assertSame('#c43211', data_get($draft, 'candidate.theme.exact_tokens.primary_cta.background'));
        $this->assertSame('#ffffff', data_get($draft, 'candidate.theme.exact_tokens.primary_cta.text'));
        $this->assertSame('#20282d', data_get($draft, 'candidate.theme.exact_tokens.secondary_cta.background'));
        $this->assertSame('#20282d', data_get($draft, 'candidate.visual_identity.colors.primary'));
        $this->assertSame('bold_budget_vehicle', data_get($draft, 'candidate.visual_identity.style.visual_profile'));
        $this->assertSame('marketing_site_stylesheet_extraction', data_get($draft, 'evidence.0.source_type'));
        $this->assertContains('theme.colors.surface', collect(data_get($draft, 'evidence'))->pluck('field')->all());
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
