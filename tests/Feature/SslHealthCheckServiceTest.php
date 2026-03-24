<?php

namespace Tests\Feature;

use App\Services\SslHealthCheck;
use Tests\TestCase;

class SslHealthCheckServiceTest extends TestCase
{
    public function test_it_reports_a_valid_certificate(): void
    {
        $now = 1_700_000_000;
        $socket = fopen('php://temp', 'r');

        $service = new class($socket, $now) extends SslHealthCheck
        {
            public function __construct(
                private $socket,
                private int $now,
            ) {}

            protected function createContext(array $options)
            {
                return $options;
            }

            protected function openSocket(string $address, int $timeout, $context)
            {
                return $this->socket;
            }

            protected function getContextParams($socket): array
            {
                return [
                    'options' => [
                        'ssl' => [
                            'peer_certificate' => 'leaf-cert',
                            'peer_certificate_chain' => ['chain-cert'],
                        ],
                    ],
                ];
            }

            protected function parseCertificate($certificate): array|false
            {
                return match ($certificate) {
                    'leaf-cert' => [
                        'subject' => ['CN' => 'example.com'],
                        'issuer' => ['CN' => 'Test Issuer'],
                        'validFrom_time_t' => $this->now - 3600,
                        'validTo_time_t' => $this->now + (40 * 86400),
                    ],
                    'chain-cert' => [
                        'subject' => ['CN' => 'Intermediate CA'],
                        'issuer' => ['CN' => 'Root CA'],
                        'validTo_time_t' => $this->now + (365 * 86400),
                    ],
                    default => false,
                };
            }

            protected function getMetaData($socket): array
            {
                return [
                    'crypto' => [
                        'protocol' => 'TLSv1.3',
                        'cipher_name' => 'TLS_AES_256_GCM_SHA384',
                        'cipher_bits' => 256,
                    ],
                ];
            }

            protected function closeSocket($socket): void
            {
                fclose($socket);
            }

            protected function currentTime(): int
            {
                return $this->now;
            }
        };

        $result = $service->check('example.com');

        $this->assertTrue($result['is_valid']);
        $this->assertSame(40, $result['days_until_expiry']);
        $this->assertSame('Test Issuer', $result['issuer']);
        $this->assertSame('TLSv1.3', $result['protocol']);
        $this->assertSame('TLS_AES_256_GCM_SHA384 (256 bits)', $result['cipher']);
        $this->assertCount(1, $result['chain']);
    }

    public function test_it_reports_connection_failures(): void
    {
        $service = new class extends SslHealthCheck
        {
            protected function createContext(array $options)
            {
                return $options;
            }

            protected function openSocket(string $address, int $timeout, $context)
            {
                return false;
            }

            protected function lastSocketError(): string
            {
                return 'SSL connection failed: test failure';
            }
        };

        $result = $service->check('example.com');

        $this->assertFalse($result['is_valid']);
        $this->assertSame('SSL connection failed: test failure', $result['error_message']);
        $this->assertSame('connection', $result['payload']['error_type']);
    }
}
