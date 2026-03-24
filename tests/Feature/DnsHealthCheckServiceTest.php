<?php

namespace Tests\Feature;

use App\Services\DnsHealthCheck;
use Tests\TestCase;

class DnsHealthCheckServiceTest extends TestCase
{
    public function test_it_is_valid_when_dns_records_exist(): void
    {
        $service = new class extends DnsHealthCheck
        {
            protected function resolveRecords(string $domain, int $type): array|false
            {
                return match ($type) {
                    DNS_A => [['ip' => '203.0.113.10']],
                    DNS_NS => [['target' => 'ns1.example.com']],
                    default => [],
                };
            }
        };

        $result = $service->check('example.com');

        $this->assertTrue($result['is_valid']);
        $this->assertTrue($result['has_a_record']);
        $this->assertFalse($result['has_mx_record']);
        $this->assertSame(['ns1.example.com'], $result['nameservers']);
    }

    public function test_it_is_invalid_when_no_dns_records_are_found(): void
    {
        $service = new class extends DnsHealthCheck
        {
            protected function resolveRecords(string $domain, int $type): array|false
            {
                return [];
            }
        };

        $result = $service->check('example.com');

        $this->assertFalse($result['is_valid']);
        $this->assertSame('No DNS records found', $result['error_message']);
        $this->assertFalse($result['has_a_record']);
        $this->assertFalse($result['has_mx_record']);
    }
}
