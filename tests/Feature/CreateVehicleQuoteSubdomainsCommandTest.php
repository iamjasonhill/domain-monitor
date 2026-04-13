<?php

namespace Tests\Feature;

use App\Models\DnsRecord;
use App\Models\Domain;
use App\Services\DomainDnsRecordService;
use App\Services\DomainSubdomainService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class CreateVehicleQuoteSubdomainsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_reports_planned_creations_and_skips_existing_hostnames_in_dry_run(): void
    {
        $carTransport = Domain::factory()->create([
            'domain' => 'cartransport.au',
            'is_active' => true,
        ]);

        DnsRecord::create([
            'domain_id' => $carTransport->id,
            'host' => 'quotes.cartransport.au',
            'type' => 'A',
            'value' => '209.38.88.239',
            'ttl' => 300,
        ]);

        $movingCars = Domain::factory()->create([
            'domain' => 'movingcars.com.au',
            'is_active' => true,
        ]);

        DnsRecord::create([
            'domain_id' => $movingCars->id,
            'host' => 'movingcars.com.au',
            'type' => 'A',
            'value' => '216.150.1.1',
            'ttl' => 300,
        ]);

        app()->instance(DomainDnsRecordService::class, new class extends DomainDnsRecordService
        {
            /**
             * @param  array{host: string, type: string, value: string, ttl: int, priority: int}  $recordData
             */
            public function saveRecord(Domain $domain, array $recordData, ?string $editingDnsRecordId = null): array
            {
                throw new \RuntimeException('saveRecord should not be called during dry run.');
            }
        });

        app()->instance(DomainSubdomainService::class, new class extends DomainSubdomainService
        {
            public function syncFromDnsRecords(Domain $domain): array
            {
                throw new \RuntimeException('syncFromDnsRecords should not be called during dry run.');
            }
        });

        $exitCode = Artisan::call('domains:create-vehicle-quote-subdomains', [
            '--dry-run' => true,
            '--domain' => ['cartransport.au', 'movingcars.com.au'],
        ]);

        $this->assertSame(0, $exitCode);

        $output = Artisan::output();

        $this->assertStringContainsString('Would create quoting.cartransport.au -> 170.64.144.64', $output);
        $this->assertStringContainsString('Would create quoting.movingcars.com.au -> 170.64.144.64', $output);
        $this->assertStringContainsString('Planned: 2', $output);
        $this->assertStringContainsString('Existing/skipped: 0', $output);
    }

    public function test_it_creates_the_configured_record_and_syncs_subdomains(): void
    {
        $domain = Domain::factory()->create([
            'domain' => 'movemycar.com.au',
            'is_active' => true,
        ]);

        $dnsRecordService = new class extends DomainDnsRecordService
        {
            /**
             * @var array<int, array{domain: string, host: string, type: string, value: string, ttl: int, priority: int}>
             */
            public array $calls = [];

            /**
             * @param  array{host: string, type: string, value: string, ttl: int, priority: int}  $recordData
             */
            public function saveRecord(Domain $domain, array $recordData, ?string $editingDnsRecordId = null): array
            {
                $this->calls[] = [
                    'domain' => $domain->domain,
                    'host' => $recordData['host'],
                    'type' => $recordData['type'],
                    'value' => $recordData['value'],
                    'ttl' => $recordData['ttl'],
                    'priority' => $recordData['priority'],
                ];

                return ['ok' => true, 'message' => 'DNS record added successfully!'];
            }
        };

        $subdomainService = new class extends DomainSubdomainService
        {
            /**
             * @var array<int, string>
             */
            public array $domains = [];

            public function syncFromDnsRecords(Domain $domain): array
            {
                $this->domains[] = $domain->domain;

                return [
                    'ok' => true,
                    'message' => 'Synced 1 new subdomain(s) and refreshed DNS resolution for 1 active subdomain(s).',
                ];
            }
        };

        app()->instance(DomainDnsRecordService::class, $dnsRecordService);
        app()->instance(DomainSubdomainService::class, $subdomainService);

        $exitCode = Artisan::call('domains:create-vehicle-quote-subdomains', [
            '--domain' => ['movemycar.com.au'],
        ]);

        $this->assertSame(0, $exitCode);

        $output = Artisan::output();
        $this->assertSame([
            [
                'domain' => 'movemycar.com.au',
                'host' => 'portal',
                'type' => 'A',
                'value' => '170.64.144.64',
                'ttl' => 300,
                'priority' => 0,
            ],
        ], $dnsRecordService->calls);
        $this->assertSame(['movemycar.com.au'], $subdomainService->domains);

        $this->assertStringContainsString('Created portal.movemycar.com.au -> 170.64.144.64', $output);
        $this->assertStringContainsString('Created: 1', $output);
    }

    public function test_it_rejects_unknown_domain_selection(): void
    {
        $exitCode = Artisan::call('domains:create-vehicle-quote-subdomains', [
            '--domain' => ['not-in-scope.example.com'],
        ]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Unsupported domain selection: not-in-scope.example.com', Artisan::output());
    }
}
