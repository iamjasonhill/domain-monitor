<?php

namespace Tests\Feature;

use App\Console\Commands\AutoRenewDomains;
use App\Models\Domain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutoRenewDomainsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_rounds_future_day_counts_up_for_display(): void
    {
        $command = new AutoRenewDomains;
        $reflection = new \ReflectionMethod($command, 'displayDaysUntilExpiry');
        $reflection->setAccessible(true);

        $this->assertSame(31, $reflection->invoke($command, 30.01));
        $this->assertSame(31, $reflection->invoke($command, 30.54));
        $this->assertSame(32, $reflection->invoke($command, 31.56));
    }

    public function test_it_rounds_past_day_counts_down_for_display(): void
    {
        $command = new AutoRenewDomains;
        $reflection = new \ReflectionMethod($command, 'displayDaysUntilExpiry');
        $reflection->setAccessible(true);

        $this->assertSame(-1, $reflection->invoke($command, -0.25));
        $this->assertSame(-2, $reflection->invoke($command, -1.25));
    }

    public function test_it_selects_auto_renew_domains_already_inside_thirty_day_window(): void
    {
        $now = now();
        $targetDate = $now->copy()->addDays(30);

        $eligible = Domain::factory()->create([
            'domain' => 'example.com.au',
            'is_active' => true,
            'auto_renew' => true,
            'expires_at' => $now->copy()->addDays(10),
        ]);

        Domain::factory()->create([
            'domain' => 'outside-window.com.au',
            'is_active' => true,
            'auto_renew' => true,
            'expires_at' => $now->copy()->addDays(45),
        ]);

        Domain::factory()->create([
            'domain' => 'expired.com.au',
            'is_active' => true,
            'auto_renew' => true,
            'expires_at' => $now->copy()->subDay(),
        ]);

        $domains = Domain::where('is_active', true)
            ->where('auto_renew', true)
            ->whereNotNull('expires_at')
            ->where('expires_at', '>', $now)
            ->where('expires_at', '<=', $targetDate->copy()->addDay()->endOfDay())
            ->get();

        $this->assertCount(1, $domains);
        $this->assertTrue($domains->first()->is($eligible));
    }
}
