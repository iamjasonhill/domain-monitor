<?php

namespace Tests\Feature;

use App\Services\SecurityHeadersHealthCheck;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SecurityHeadersHealthCheckServiceTest extends TestCase
{
    public function test_it_passes_when_critical_security_headers_are_present(): void
    {
        Http::fake([
            'https://example.com' => Http::response('ok', 200, [
                'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains',
                'X-Frame-Options' => 'SAMEORIGIN',
                'X-Content-Type-Options' => 'nosniff',
            ]),
        ]);

        $result = app(SecurityHeadersHealthCheck::class)->check('example.com');

        $this->assertTrue($result['verified']);
        $this->assertTrue($result['is_valid']);
        $this->assertGreaterThan(0, $result['score']);
    }

    public function test_it_fails_when_critical_security_headers_are_missing(): void
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
    }
}
