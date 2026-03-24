<?php

namespace Tests\Feature;

use App\Services\UptimeHealthCheck;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class UptimeHealthCheckServiceTest extends TestCase
{
    public function test_it_falls_back_to_get_when_head_is_not_allowed(): void
    {
        Http::fake(function (Request $request) {
            if ($request->method() === 'HEAD') {
                return Http::response('', 405);
            }

            return Http::response('ok', 200);
        });

        $result = app(UptimeHealthCheck::class)->check('example.com');

        $this->assertTrue($result['is_valid']);
        $this->assertSame(200, $result['status_code']);
        $this->assertNull($result['error_message']);
    }

    public function test_it_marks_server_errors_as_invalid(): void
    {
        Http::fake([
            'https://example.com' => Http::response('down', 503),
        ]);

        $result = app(UptimeHealthCheck::class)->check('example.com');

        $this->assertFalse($result['is_valid']);
        $this->assertSame(503, $result['status_code']);
        $this->assertSame('HTTP 503', $result['error_message']);
    }
}
