<?php

namespace Tests\Feature;

use App\Services\ExternalLinkInventoryHealthCheck;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ExternalLinkInventoryHealthCheckServiceTest extends TestCase
{
    public function test_it_collects_external_links_including_subdomains(): void
    {
        Http::fake(function (Request $request) {
            return match ($request->url()) {
                'https://example.com/' => Http::response(
                    <<<'HTML'
                    <html>
                        <body>
                            <a href="/about">About</a>
                            <a href="https://blog.example.com/posts">Blog</a>
                            <a href="https://partner.example.org/offer?src=nav#hero">Partner</a>
                            <a href="mailto:hello@example.com">Email</a>
                        </body>
                    </html>
                    HTML,
                    200,
                    ['Content-Type' => 'text/html']
                ),
                'https://example.com/about' => Http::response(
                    <<<'HTML'
                    <html>
                        <body>
                            <a href="../contact">Contact</a>
                            <a href="https://partner.example.org/offer?src=nav">Partner Again</a>
                            <a href="https://www.example.com/help">WWW Help</a>
                            <a href="javascript:void(0)">Ignore</a>
                        </body>
                    </html>
                    HTML,
                    200,
                    ['Content-Type' => 'text/html']
                ),
                'https://example.com/contact' => Http::response(
                    '<html><body><p>Contact</p></body></html>',
                    200,
                    ['Content-Type' => 'text/html']
                ),
                default => Http::response('not found', 404),
            };
        });

        $result = app(ExternalLinkInventoryHealthCheck::class)->check('example.com');

        $this->assertTrue($result['verified']);
        $this->assertTrue($result['is_valid']);
        $this->assertSame(3, $result['pages_scanned']);
        $this->assertSame(3, $result['external_links_count']);
        $this->assertSame(
            [
                'https://blog.example.com/posts',
                'https://partner.example.org/offer',
                'https://www.example.com/help',
            ],
            array_column($result['external_links'], 'url')
        );
        $this->assertSame(
            'subdomain',
            collect($result['external_links'])->firstWhere('url', 'https://blog.example.com/posts')['relationship']
        );
        $this->assertSame(
            ['https://example.com/about', 'https://example.com/'],
            collect($result['external_links'])->firstWhere('url', 'https://partner.example.org/offer')['found_on_pages']
        );
    }

    public function test_it_marks_external_link_inventory_as_unverified_when_crawl_fails(): void
    {
        Http::fake(fn () => throw new \RuntimeException('Connection failed'));

        $result = app(ExternalLinkInventoryHealthCheck::class)->check('example.com');

        $this->assertFalse($result['verified']);
        $this->assertFalse($result['is_valid']);
        $this->assertStringContainsString('Connection failed', $result['error_message']);
    }

    public function test_it_keeps_partial_inventory_when_one_internal_page_fails(): void
    {
        Http::fake(function (Request $request) {
            return match ($request->url()) {
                'https://example.com/' => Http::response(
                    '<html><body><a href="/about">About</a><a href="https://blog.example.com/posts">Blog</a></body></html>',
                    200,
                    ['Content-Type' => 'text/html']
                ),
                'https://example.com/about' => throw new \RuntimeException('Timeout'),
                default => Http::response('not found', 404),
            };
        });

        $result = app(ExternalLinkInventoryHealthCheck::class)->check('example.com');

        $this->assertTrue($result['verified']);
        $this->assertFalse($result['is_valid']);
        $this->assertSame(1, $result['pages_scanned']);
        $this->assertSame(1, $result['external_links_count']);
        $this->assertSame(1, data_get($result, 'payload.page_failures_count'));
        $this->assertSame('https://blog.example.com/posts', $result['external_links'][0]['url']);
    }
}
