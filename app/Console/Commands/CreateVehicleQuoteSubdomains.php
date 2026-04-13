<?php

namespace App\Console\Commands;

use App\Models\Domain;
use App\Services\DomainDnsRecordService;
use App\Services\DomainSubdomainService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class CreateVehicleQuoteSubdomains extends Command
{
    private const string TARGET_IP = '170.64.144.64';

    /**
     * @var array<string, string>
     */
    private const array HOSTNAME_MAP = [
        'cartransport.au' => 'quoting',
        'cartransportaus.com.au' => 'quoting',
        'cartransportwithpersonalitems.com.au' => 'quoting',
        'interstate-car-transport.com.au' => 'quoting',
        'interstatecarcarriers.com.au' => 'quoting',
        'movemycar.com.au' => 'portal',
        'movingcars.com.au' => 'quoting',
        'supercheapcartransport.com.au' => 'portal',
        'transportnondrivablecars.com.au' => 'quoting',
        'vehicle.net.au' => 'quoting',
    ];

    protected $signature = 'domains:create-vehicle-quote-subdomains
                            {--domain=* : Restrict the run to one or more configured vehicle transport domains}
                            {--dry-run : Show what would be created without writing DNS records}';

    protected $description = 'Create generic quote-portal A records for the configured vehicle transport domains';

    public function handle(DomainDnsRecordService $dnsRecordService, DomainSubdomainService $subdomainService): int
    {
        $configuredDomains = collect(self::HOSTNAME_MAP);
        $requestedDomains = collect($this->option('domain'))
            ->filter(fn (mixed $domain): bool => is_string($domain) && trim($domain) !== '')
            ->map(fn (string $domain): string => strtolower(trim($domain)))
            ->values();

        if ($requestedDomains->isNotEmpty()) {
            $unknownDomains = $requestedDomains
                ->reject(fn (string $domain): bool => $configuredDomains->has($domain))
                ->values();

            if ($unknownDomains->isNotEmpty()) {
                $this->error('Unsupported domain selection: '.$unknownDomains->implode(', '));
                $this->line('Supported domains: '.$configuredDomains->keys()->implode(', '));

                return self::FAILURE;
            }

            $configuredDomains = $configuredDomains->only($requestedDomains->all());
        }

        /** @var Collection<string, Domain> $domainsByName */
        $domainsByName = Domain::query()
            ->with([
                'dnsRecords' => fn ($query) => $query->select('id', 'domain_id', 'host'),
                'subdomains' => fn ($query) => $query->select('id', 'domain_id', 'subdomain', 'full_domain')->where('is_active', true),
            ])
            ->whereIn('domain', $configuredDomains->keys()->all())
            ->get()
            ->keyBy(fn (Domain $domain): string => strtolower($domain->domain));

        $dryRun = (bool) $this->option('dry-run');
        $created = 0;
        $existing = 0;
        $failed = 0;
        $missing = 0;

        foreach ($configuredDomains as $domainName => $hostLabel) {
            /** @var Domain|null $domain */
            $domain = $domainsByName->get($domainName);

            if (! $domain instanceof Domain) {
                $missing++;
                $this->warn("Skipping {$domainName}: domain is not present in Domain Monitor.");

                continue;
            }

            $fullHostname = $hostLabel.'.'.$domain->domain;

            if ($this->hostnameAlreadyExists($domain, $hostLabel, $fullHostname)) {
                $existing++;
                $this->line("Skipping {$fullHostname}: hostname already exists.");

                continue;
            }

            if ($dryRun) {
                $created++;
                $this->info("Would create {$fullHostname} -> ".self::TARGET_IP);

                continue;
            }

            $result = $dnsRecordService->saveRecord($domain, [
                'host' => $hostLabel,
                'type' => 'A',
                'value' => self::TARGET_IP,
                'ttl' => 300,
                'priority' => 0,
            ]);

            if ($result['ok'] === false) {
                $failed++;
                $error = array_key_exists('error', $result) ? $result['error'] : 'unknown error';
                $this->error("Failed to create {$fullHostname}: {$error}");

                continue;
            }

            $created++;
            $this->info("Created {$fullHostname} -> ".self::TARGET_IP);

            $syncResult = $subdomainService->syncFromDnsRecords($domain->fresh('dnsRecords', 'subdomains'));

            if ($syncResult['ok'] === false) {
                $error = array_key_exists('error', $syncResult) ? $syncResult['error'] : 'unknown error';
                $this->warn("  DNS record created, but subdomain sync needs review: {$error}");
            }
        }

        $verb = $dryRun ? 'Planned' : 'Created';
        $this->newLine();
        $this->info("{$verb}: {$created}");
        $this->line("Existing/skipped: {$existing}");
        $this->line("Missing from Domain Monitor: {$missing}");

        if ($failed > 0) {
            $this->line("Failed: {$failed}");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function hostnameAlreadyExists(Domain $domain, string $hostLabel, string $fullHostname): bool
    {
        $hostnames = $domain->dnsRecords
            ->pluck('host')
            ->merge($domain->subdomains->pluck('subdomain'))
            ->merge($domain->subdomains->pluck('full_domain'))
            ->filter(fn (mixed $host): bool => is_string($host) && trim($host) !== '')
            ->map(fn (string $host): string => strtolower(trim($host)))
            ->values();

        return $hostnames->contains(strtolower($hostLabel))
            || $hostnames->contains(strtolower($fullHostname));
    }
}
