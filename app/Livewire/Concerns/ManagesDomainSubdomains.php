<?php

namespace App\Livewire\Concerns;

use App\Services\DomainSubdomainService;

trait ManagesDomainSubdomains
{
    public function openAddSubdomainModal(): void
    {
        $this->resetSubdomainForm();
        $this->showSubdomainModal = true;
    }

    public function openEditSubdomainModal(string $subdomainId): void
    {
        $subdomain = app(DomainSubdomainService::class)->findForDomain($this->domain, $subdomainId);
        if (! $subdomain) {
            session()->flash('error', 'Subdomain not found.');

            return;
        }

        $this->editingSubdomainId = $subdomainId;
        $this->subdomainName = $subdomain->subdomain;
        $this->subdomainNotes = $subdomain->notes ?? '';
        $this->showSubdomainModal = true;
    }

    public function closeSubdomainModal(): void
    {
        $this->showSubdomainModal = false;
        $this->resetSubdomainForm();
    }

    public function saveSubdomain(): void
    {
        $result = app(DomainSubdomainService::class)->saveSubdomain(
            $this->domain,
            $this->subdomainName,
            $this->subdomainNotes,
            $this->editingSubdomainId
        );

        if ($result['ok']) {
            session()->flash('message', $result['message'] ?? 'Subdomain saved successfully!');
        } else {
            session()->flash('error', $result['error'] ?? 'Failed to save subdomain.');

            return;
        }

        $this->closeSubdomainModal();
        $this->loadDomain();
    }

    public function deleteSubdomain(string $subdomainId): void
    {
        $result = app(DomainSubdomainService::class)->deleteSubdomain($this->domain, $subdomainId);

        if (! $result['ok']) {
            session()->flash('error', $result['error'] ?? 'Failed to delete subdomain.');

            return;
        }

        session()->flash('message', $result['message'] ?? 'Subdomain deleted successfully!');
        $this->loadDomain();
    }

    public function updateAllSubdomainsIp(): void
    {
        if (! $this->domain) {
            session()->flash('error', 'Domain not found.');

            return;
        }

        $result = app(DomainSubdomainService::class)->updateAllSubdomainsIp($this->domain);

        if (! $result['ok']) {
            session()->flash('error', $result['error'] ?? 'Failed to update subdomains.');

            return;
        }

        if (isset($result['info'])) {
            session()->flash('info', $result['info']);
        } elseif (isset($result['message'])) {
            session()->flash('message', $result['message']);
        }

        $this->loadDomain();
    }

    public function updateSubdomainIp(string $subdomainId): void
    {
        try {
            $result = app(DomainSubdomainService::class)->updateSubdomainIp($this->domain, $subdomainId);

            if ($result['ok']) {
                $this->loadDomain();
                session()->flash('message', $result['message'] ?? 'Subdomain IP information updated!');
            } else {
                session()->flash('error', $result['error'] ?? 'Failed to update subdomain IP.');
            }
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to update subdomain IP: '.$e->getMessage());
        }
    }

    public function discoverSubdomainsFromDns(): void
    {
        if (! $this->domain) {
            session()->flash('error', 'Domain not found.');

            return;
        }

        try {
            $dnsRecords = $this->domain->dnsRecords;

            if ($dnsRecords->isEmpty()) {
                session()->flash('error', 'No DNS records found. Please sync DNS records first.');

                return;
            }

            $discoveredSubdomains = [];
            $existingSubdomains = $this->domain->subdomains->pluck('subdomain')->toArray();

            foreach ($dnsRecords as $record) {
                if (! in_array($record->type, ['A', 'AAAA', 'CNAME'])) {
                    continue;
                }

                $host = strtolower($record->host);

                if (in_array($host, ['@', 'www', 'mail', 'webmail', 'ftp', 'cpanel', 'whm', 'localhost', '*'])) {
                    continue;
                }

                if (str_starts_with($host, '*')) {
                    continue;
                }

                if (! in_array($host, $discoveredSubdomains) && ! in_array($host, $existingSubdomains)) {
                    $discoveredSubdomains[] = $host;
                }
            }

            if (empty($discoveredSubdomains)) {
                session()->flash('info', 'No new subdomains found in DNS records.');

                return;
            }

            $count = 0;
            foreach ($discoveredSubdomains as $subdomainName) {
                $this->domain->subdomains()->create([
                    'subdomain' => $subdomainName,
                    'full_domain' => "$subdomainName.{$this->domain->domain}",
                    'is_active' => true,
                ]);
                $count++;
            }

            $this->loadDomain();
            session()->flash('message', "Discovered {$count} new subdomains from DNS records.");
        } catch (\Exception $e) {
            session()->flash('error', 'Error discovering subdomains: '.$e->getMessage());
        }
    }

    private function resetSubdomainForm(): void
    {
        $this->editingSubdomainId = null;
        $this->subdomainName = '';
        $this->subdomainNotes = '';
    }
}
