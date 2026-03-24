<?php

namespace Tests\Feature;

use App\Services\SecurityHeadersHealthCheck;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SecurityHeadersHealthCheckServiceTest extends TestCase
{
    public function test_it_passes_when_all_headers_meet_the_baseline_standard(): void
    {
        Http::fake([
            'https://example.com' => Http::response('ok', 200, [
                'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains; preload',
                'Content-Security-Policy' => "default-src 'self'; object-src 'none'; base-uri 'self'; frame-ancestors 'none'",
                'X-Frame-Options' => 'SAMEORIGIN',
                'X-Content-Type-Options' => 'nosniff',
                'Referrer-Policy' => 'strict-origin-when-cross-origin',
                'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
            ]),
        ]);

        $result = app(SecurityHeadersHealthCheck::class)->check('example.com');

        $this->assertTrue($result['verified']);
        $this->assertTrue($result['is_valid']);
        $this->assertSame(100, $result['score']);
        $this->assertSame('pass', $result['headers']['content-security-policy']['status']);
    }

    public function test_it_marks_the_result_invalid_when_required_headers_are_missing(): void
    {
        Http::fake([
            'https://example.com' => Http::response('ok', 200, [
                'Referrer-Policy' => 'strict-origin-when-cross-origin',
            ]),
        ]);

        $result = app(SecurityHeadersHealthCheck::class)->check('example.com');

        $this->assertTrue($result['verified']);
        $this->assertFalse($result['is_valid']);
        $this->assertSame('fail', $result['headers']['strict-transport-security']['status']);
        $this->assertSame('fail', $result['headers']['content-security-policy']['status']);
    }

    public function test_it_marks_weak_but_present_headers_as_needing_improvement(): void
    {
        Http::fake([
            'https://example.com' => Http::response('ok', 200, [
                'Strict-Transport-Security' => 'max-age=300',
                'Content-Security-Policy' => "default-src 'self'",
                'X-Frame-Options' => 'SAMEORIGIN',
                'X-Content-Type-Options' => 'nosniff',
                'Referrer-Policy' => 'origin',
            ]),
        ]);

        $result = app(SecurityHeadersHealthCheck::class)->check('example.com');

        $this->assertTrue($result['verified']);
        $this->assertFalse($result['is_valid']);
        $this->assertSame('warn', $result['headers']['strict-transport-security']['status']);
        $this->assertSame('warn', $result['headers']['content-security-policy']['status']);
        $this->assertSame('warn', $result['headers']['referrer-policy']['status']);
        $this->assertSame('warn', $result['headers']['permissions-policy']['status']);
    }

    public function test_it_fails_invalid_protective_header_values(): void
    {
        Http::fake([
            'https://example.com' => Http::response('ok', 200, [
                'Strict-Transport-Security' => 'includeSubDomains',
                'Content-Security-Policy' => "default-src 'self'; object-src 'none'; base-uri 'self'; frame-ancestors 'none'",
                'X-Frame-Options' => 'ALLOWALL',
                'X-Content-Type-Options' => 'sniff',
                'Referrer-Policy' => 'strict-origin-when-cross-origin',
                'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
            ]),
        ]);

        $result = app(SecurityHeadersHealthCheck::class)->check('example.com');

        $this->assertTrue($result['verified']);
        $this->assertFalse($result['is_valid']);
        $this->assertSame('fail', $result['headers']['strict-transport-security']['status']);
        $this->assertSame('fail', $result['headers']['x-frame-options']['status']);
        $this->assertSame('fail', $result['headers']['x-content-type-options']['status']);
    }

    public function test_it_reports_unknown_when_the_headers_cannot_be_fetched(): void
    {
        Http::fake([
            'https://example.com' => function () {
                throw new \RuntimeException('connection refused');
            },
        ]);

        $result = app(SecurityHeadersHealthCheck::class)->check('example.com');

        $this->assertFalse($result['verified']);
        $this->assertFalse($result['is_valid']);
        $this->assertSame([], $result['headers']);
        $this->assertSame(0, $result['score']);
    }
}
