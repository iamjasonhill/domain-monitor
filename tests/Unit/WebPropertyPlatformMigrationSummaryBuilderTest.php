<?php

namespace Tests\Unit;

use App\Models\WebProperty;
use App\Services\WebPropertyPlatformMigrationSummaryBuilder;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class WebPropertyPlatformMigrationSummaryBuilderTest extends TestCase
{
    public function test_it_returns_stable_defaults_when_cutover_is_unknown(): void
    {
        $property = new WebProperty;
        $property->platform = 'WordPress';
        $property->target_platform = 'Astro';
        $property->astro_cutover_at = null;

        $summary = app(WebPropertyPlatformMigrationSummaryBuilder::class)->build($property);

        $this->assertSame([
            'current_platform' => 'WordPress',
            'target_platform' => 'Astro',
            'astro_cutover_at' => null,
        ], $summary);
    }

    public function test_it_serializes_the_astro_cutover_timestamp(): void
    {
        $property = new WebProperty;
        $property->platform = 'Astro';
        $property->target_platform = 'Astro';
        $property->astro_cutover_at = Carbon::parse('2026-04-16T08:00:00Z');

        $summary = app(WebPropertyPlatformMigrationSummaryBuilder::class)->build($property);

        $this->assertSame('Astro', $summary['current_platform']);
        $this->assertSame('Astro', $summary['target_platform']);
        $this->assertSame('2026-04-16T08:00:00+10:00', $summary['astro_cutover_at']);
    }
}
