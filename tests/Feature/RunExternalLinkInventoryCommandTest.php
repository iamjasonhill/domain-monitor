<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\DomainCheck;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RunExternalLinkInventoryCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_check_command_supports_external_link_inventory_type(): void
    {
        $domain = Domain::factory()->create([
            'domain' => 'command-check.example.com',
            'is_active' => true,
        ]);

        Http::fake(function (Request $request) {
            return match ($request->url()) {
                'https://command-check.example.com/' => Http::response(
                    '<html><body><a href="https://portal.command-check.example.com/start">Portal</a></body></html>',
                    200,
                    ['Content-Type' => 'text/html']
                ),
                default => Http::response('not found', 404),
            };
        });

        $exitCode = Artisan::call('domains:health-check', [
            '--domain' => 'command-check.example.com',
            '--type' => 'external_links',
        ]);

        $this->assertSame(0, $exitCode);

        $check = DomainCheck::query()
            ->where('domain_id', $domain->id)
            ->where('check_type', 'external_links')
            ->latest('finished_at')
            ->first();

        $this->assertInstanceOf(DomainCheck::class, $check);
        $this->assertSame('ok', $check->status);
        $this->assertSame(1, data_get($check->payload, 'external_links_count'));
        $this->assertSame(
            'https://portal.command-check.example.com/start',
            data_get($check->payload, 'external_links.0.url')
        );
    }

    public function test_all_external_link_inventory_checks_skip_ineligible_domains(): void
    {
        $activeDomain = Domain::factory()->create([
            'domain' => 'active-scan.example.com',
            'is_active' => true,
        ]);
        $parkedDomain = Domain::factory()->create([
            'domain' => 'parked-scan.example.com',
            'is_active' => true,
            'parked_override' => true,
            'parked_override_set_at' => now(),
        ]);
        $emailOnlyDomain = Domain::factory()->create([
            'domain' => 'mail-scan.example.com',
            'is_active' => true,
            'platform' => 'Email Only',
        ]);
        $inactiveDomain = Domain::factory()->create([
            'domain' => 'inactive-scan.example.com',
            'is_active' => false,
        ]);

        Http::fake(function (Request $request) {
            return match ($request->url()) {
                'https://active-scan.example.com/' => Http::response(
                    '<html><body><a href="https://portal.active-scan.example.com/start">Portal</a></body></html>',
                    200,
                    ['Content-Type' => 'text/html']
                ),
                default => Http::response('not found', 404),
            };
        });

        $exitCode = Artisan::call('domains:health-check', [
            '--all' => true,
            '--type' => 'external_links',
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertSame(1, DomainCheck::query()->where('check_type', 'external_links')->count());
        $this->assertDatabaseHas('domain_checks', [
            'domain_id' => $activeDomain->id,
            'check_type' => 'external_links',
        ]);
        $this->assertDatabaseMissing('domain_checks', [
            'domain_id' => $parkedDomain->id,
            'check_type' => 'external_links',
        ]);
        $this->assertDatabaseMissing('domain_checks', [
            'domain_id' => $emailOnlyDomain->id,
            'check_type' => 'external_links',
        ]);
        $this->assertDatabaseMissing('domain_checks', [
            'domain_id' => $inactiveDomain->id,
            'check_type' => 'external_links',
        ]);
    }
}
