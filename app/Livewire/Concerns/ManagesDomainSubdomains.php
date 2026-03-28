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
            $result = app(DomainSubdomainService::class)->syncFromDnsRecords($this->domain);

            if (! $result['ok']) {
                session()->flash('error', $result['error'] ?? 'Failed to discover subdomains from DNS.');

                return;
            }

            $this->loadDomain();
            session()->flash('message', $result['message'] ?? 'Subdomains synced from DNS successfully.');
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
