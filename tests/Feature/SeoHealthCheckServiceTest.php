<?php

namespace Tests\Feature;

use App\Services\SeoHealthCheck;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SeoHealthCheckServiceTest extends TestCase
{
    public function test_it_marks_seo_as_valid_when_robots_exists(): void
    {
        Http::fake([
            'https://example.com/robots.txt' => Http::response("User-agent: *\nDisallow:", 200),
            'https://example.com/sitemap.xml' => Http::response('', 404),
            'https://example.com/sitemap_index.xml' => Http::response('', 404),
        ]);

        $result = app(SeoHealthCheck::class)->check('example.com');

        $this->assertTrue($result['verified']);
        $this->assertTrue($result['is_valid']);
        $this->assertTrue($result['results']['robots']['exists']);
        $this->assertFalse($result['results']['sitemap']['exists']);
    }

    public function test_it_marks_seo_as_unverified_when_requests_fail(): void
    {
        Http::fake(fn () => throw new \RuntimeException('Connection failed'));

        $result = app(SeoHealthCheck::class)->check('example.com');

        $this->assertFalse($result['verified']);
        $this->assertFalse($result['is_valid']);
        $this->assertFalse($result['results']['robots']['verified']);
    }
}
