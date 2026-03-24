<?php

namespace Tests\Feature;

use App\Services\HttpHealthCheck;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HttpHealthCheckServiceTest extends TestCase
{
    public function test_it_reports_a_successful_http_response(): void
    {
        Http::fake([
            'https://example.com' => Http::response('ok', 200, [
                'X-Test' => 'value',
            ]),
        ]);

        $result = app(HttpHealthCheck::class)->check('example.com');

        $this->assertTrue($result['is_up']);
        $this->assertSame(200, $result['status_code']);
        $this->assertNull($result['error_message']);
        $this->assertSame('https://example.com', $result['payload']['url']);
        $this->assertSame('value', $result['payload']['headers']['X-Test']);
    }

    public function test_it_falls_back_to_http_when_https_fails_with_ssl_error(): void
    {
        Http::fake(function (Request $request) {
            if ($request->url() === 'https://example.com') {
                throw new ConnectionException('SSL handshake failed');
            }

            return Http::response('ok', 200);
        });

        $result = app(HttpHealthCheck::class)->check('example.com');

        $this->assertTrue($result['is_up']);
        $this->assertSame(200, $result['status_code']);
        $this->assertNull($result['error_message']);
    }

    public function test_it_marks_server_errors_as_down(): void
    {
        Http::fake([
            'https://example.com' => Http::response('error', 500),
        ]);

        $result = app(HttpHealthCheck::class)->check('example.com');

        $this->assertFalse($result['is_up']);
        $this->assertSame(500, $result['status_code']);
        $this->assertSame('HTTP 500', $result['error_message']);
    }
}
