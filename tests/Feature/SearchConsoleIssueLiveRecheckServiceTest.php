<?php

namespace Tests\Feature;

use App\Services\SearchConsoleIssueLiveRecheckService;
use ReflectionMethod;
use Tests\TestCase;

class SearchConsoleIssueLiveRecheckServiceTest extends TestCase
{
    public function test_host_resolves_publicly_accepts_hosts_with_at_least_one_public_ip(): void
    {
        $service = new class extends SearchConsoleIssueLiveRecheckService
        {
            protected function shouldBypassDnsLookups(): bool
            {
                return false;
            }

            /**
             * @return array<int, array<string, mixed>>
             */
            protected function dnsRecordsForHost(string $host): array
            {
                return [
                    ['ip' => '10.0.0.10'],
                    ['ip' => '203.0.113.20'],
                ];
            }
        };

        $this->assertTrue($this->hostResolvesPublicly($service, 'mixed.example.com'));
    }

    public function test_host_resolves_publicly_rejects_hosts_without_any_public_ip(): void
    {
        $service = new class extends SearchConsoleIssueLiveRecheckService
        {
            protected function shouldBypassDnsLookups(): bool
            {
                return false;
            }

            /**
             * @return array<int, array<string, mixed>>
             */
            protected function dnsRecordsForHost(string $host): array
            {
                return [
                    ['ip' => '10.0.0.10'],
                    ['ipv6' => 'fd00::10'],
                ];
            }
        };

        $this->assertFalse($this->hostResolvesPublicly($service, 'private-only.example.com'));
    }

    public function test_host_resolves_publicly_accepts_hosts_with_only_public_ips(): void
    {
        $service = new class extends SearchConsoleIssueLiveRecheckService
        {
            protected function shouldBypassDnsLookups(): bool
            {
                return false;
            }

            /**
             * @return array<int, array<string, mixed>>
             */
            protected function dnsRecordsForHost(string $host): array
            {
                return [
                    ['ip' => '198.51.100.10'],
                    ['ipv6' => '2001:db8::20'],
                ];
            }
        };

        $this->assertTrue($this->hostResolvesPublicly($service, 'public-only.example.com'));
    }

    public function test_host_resolves_publicly_rejects_hosts_with_no_dns_records(): void
    {
        $service = new class extends SearchConsoleIssueLiveRecheckService
        {
            protected function shouldBypassDnsLookups(): bool
            {
                return false;
            }

            /**
             * @return array<int, array<string, mixed>>
             */
            protected function dnsRecordsForHost(string $host): array
            {
                return [];
            }
        };

        $this->assertFalse($this->hostResolvesPublicly($service, 'missing.example.com'));
    }

    public function test_host_resolves_publicly_rejects_hosts_with_no_ip_answers(): void
    {
        $service = new class extends SearchConsoleIssueLiveRecheckService
        {
            protected function shouldBypassDnsLookups(): bool
            {
                return false;
            }

            /**
             * @return array<int, array<string, mixed>>
             */
            protected function dnsRecordsForHost(string $host): array
            {
                return [
                    ['host' => 'alias.example.com', 'type' => 'CNAME'],
                ];
            }
        };

        $this->assertFalse($this->hostResolvesPublicly($service, 'cname-only.example.com'));
    }

    private function hostResolvesPublicly(SearchConsoleIssueLiveRecheckService $service, string $host): bool
    {
        $method = new ReflectionMethod(SearchConsoleIssueLiveRecheckService::class, 'hostResolvesPublicly');
        $method->setAccessible(true);

        /** @var bool $result */
        $result = $method->invoke($service, $host);

        return $result;
    }
}
