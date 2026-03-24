<?php

namespace Tests\Feature;

use App\Console\Commands\AutoRenewDomains;
use Tests\TestCase;

class AutoRenewDomainsCommandTest extends TestCase
{
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
}
