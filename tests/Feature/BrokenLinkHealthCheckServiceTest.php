<?php

namespace Tests\Feature;

use App\Services\BrokenLinkHealthCheck;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BrokenLinkHealthCheckServiceTest extends TestCase
{
    public function test_it_reports_broken_internal_links(): void
    {
        Http::fake(function (Request $request) {
            return match ($request->url()) {
                'https://example.com' => Http::response(
                    '<html><body><a href="/good">Good</a><a href="/bad">Bad</a></body></html>',
                    200,
                    ['Content-Type' => 'text/html']
                ),
                'https://example.com/good' => Http::response(
                    '<html><body>No more links</body></html>',
                    200,
                    ['Content-Type' => 'text/html']
                ),
                'https://example.com/bad' => Http::response('missing', 404),
                default => Http::response('not found', 404),
            };
        });

        $result = app(BrokenLinkHealthCheck::class)->check('example.com');

        $this->assertTrue($result['verified']);
        $this->assertFalse($result['is_valid']);
        $this->assertSame(1, $result['broken_links_count']);
        $this->assertSame('https://example.com/bad', $result['broken_links'][0]['url']);
        $this->assertSame('https://example.com', $result['broken_links'][0]['found_on']);
    }

    public function test_it_marks_broken_link_check_as_unverified_when_crawl_fails(): void
    {
        Http::fake(fn () => throw new \RuntimeException('Connection failed'));

        $result = app(BrokenLinkHealthCheck::class)->check('example.com');

        $this->assertFalse($result['verified']);
        $this->assertFalse($result['is_valid']);
        $this->assertStringContainsString('Connection failed', $result['error_message']);
    }

    public function test_it_treats_rate_limited_external_links_as_non_broken(): void
    {
        Http::fake(function (Request $request) {
            if ($request->url() === 'https://example.com') {
                return Http::response(
                    '<html><body><a href="https://quote.example.com/quote/vehicle">Quote</a></body></html>',
                    200,
                    ['Content-Type' => 'text/html']
                );
            }

            if ($request->method() === 'HEAD' && $request->url() === 'https://quote.example.com/quote/vehicle') {
                return Http::response('rate limited', 429);
            }

            return Http::response('ok', 200, ['Content-Type' => 'text/html']);
        });

        $result = app(BrokenLinkHealthCheck::class)->check('example.com');

        $this->assertTrue($result['verified']);
        $this->assertTrue($result['is_valid']);
        $this->assertSame(0, $result['broken_links_count']);
        $this->assertSame(1, $result['rate_limited_links_count']);
        $this->assertSame('https://quote.example.com/quote/vehicle', $result['rate_limited_links'][0]['url']);
        $this->assertSame(429, $result['rate_limited_links'][0]['status']);
        $this->assertSame('https://example.com', $result['rate_limited_links'][0]['found_on']);
    }

    public function test_it_treats_rate_limited_internal_links_as_non_broken(): void
    {
        Http::fake(function (Request $request) {
            return match ($request->url()) {
                'https://example.com' => Http::response(
                    '<html><body><a href="/quote/vehicle">Quote</a></body></html>',
                    200,
                    ['Content-Type' => 'text/html']
                ),
                'https://example.com/quote/vehicle' => Http::response('rate limited', 429),
                default => Http::response('not found', 404),
            };
        });

        $result = app(BrokenLinkHealthCheck::class)->check('example.com');

        $this->assertTrue($result['verified']);
        $this->assertTrue($result['is_valid']);
        $this->assertSame(0, $result['broken_links_count']);
        $this->assertSame(1, $result['rate_limited_links_count']);
        $this->assertSame('https://example.com/quote/vehicle', $result['rate_limited_links'][0]['url']);
        $this->assertSame(429, $result['rate_limited_links'][0]['status']);
        $this->assertSame('https://example.com', $result['rate_limited_links'][0]['found_on']);
    }
}
