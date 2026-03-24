<?php

namespace App\Livewire\Concerns;

use App\Services\DomainDnsRecordService;
use Illuminate\Support\Facades\Log;

trait ManagesDomainDnsRecords
{
    public function syncDnsRecords(): void
    {
        if (! $this->isAustralianTld()) {
            session()->flash('error', 'Only Australian TLD domains (.com.au, .net.au, etc.) can sync DNS records.');
            $this->dispatch('dns-sync-complete');

            return;
        }

        try {
            \App\Jobs\SyncDnsRecordsJob::dispatch($this->domain->id);

            session()->flash('message', 'DNS sync queued. Job will process in the background via Horizon.');
            $this->dispatch('dns-sync-complete');
        } catch (\Exception $e) {
            session()->flash('error', 'Error queueing DNS sync: '.$e->getMessage());
            $this->dispatch('dns-sync-complete');
        }
    }

    public function openAddDnsRecordModal(): void
    {
        $this->resetDnsRecordForm();
        $this->showDnsRecordModal = true;
    }

    public function openEditDnsRecordModal(string $recordId): void
    {
        if (! $this->domain) {
            return;
        }

        $record = app(DomainDnsRecordService::class)->findDomainRecord($this->domain, $recordId);

        if (! $record) {
            session()->flash('error', 'DNS record not found.');

            return;
        }

        $this->editingDnsRecordId = $record->id;
        $this->dnsRecordHost = $record->host;
        $this->dnsRecordType = $record->type;
        $this->dnsRecordValue = $record->value;
        $this->dnsRecordTtl = $record->ttl ?? 300;
        $this->dnsRecordPriority = $record->priority ?? 0;
        $this->showDnsRecordModal = true;
    }

    public function closeDnsRecordModal(): void
    {
        $this->showDnsRecordModal = false;
        $this->resetDnsRecordForm();
    }

    public function saveDnsRecord(): void
    {
        if (! $this->domain) {
            $this->addError('dnsRecordHost', 'Domain not found.');

            return;
        }

        $host = trim($this->dnsRecordHost ?? '');
        if ($host === '' || $host === '@') {
            $host = '@';
            $this->dnsRecordHost = '@';
        }

        $this->validate([
            'dnsRecordHost' => ['required', 'string', 'max:255'],
            'dnsRecordType' => ['required', 'string', 'in:A,AAAA,CNAME,MX,NS,TXT,SRV'],
            'dnsRecordValue' => ['required', 'string', 'max:65535'],
            'dnsRecordTtl' => ['required', 'integer', 'min:60', 'max:86400'],
            'dnsRecordPriority' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ], [
            'dnsRecordHost.required' => 'Host/Subdomain is required. Use @ for root domain or leave empty.',
            'dnsRecordValue.required' => 'Value is required.',
            'dnsRecordTtl.required' => 'TTL is required.',
            'dnsRecordTtl.min' => 'TTL must be at least 60 seconds.',
            'dnsRecordTtl.max' => 'TTL cannot exceed 86400 seconds (1 day).',
            'dnsRecordPriority.min' => 'Priority must be 0 or greater.',
            'dnsRecordPriority.max' => 'Priority cannot exceed 65535.',
        ]);

        if ($host !== '@' && ! preg_match('/^[a-z0-9]([a-z0-9\-_]*[a-z0-9])?$/i', $host)) {
            $this->addError('dnsRecordHost', 'Host must be a valid subdomain name (letters, numbers, hyphens, underscores) or @ for root domain.');

            return;
        }

        if ($this->dnsRecordType === 'MX' && $this->dnsRecordPriority < 1) {
            $this->addError('dnsRecordPriority', 'Priority is required for MX records (typically 10-100).');

            return;
        }

        $service = app(DomainDnsRecordService::class);
        $recordData = [
            'host' => $this->dnsRecordHost,
            'type' => $this->dnsRecordType,
            'value' => $this->dnsRecordValue,
            'ttl' => $this->dnsRecordTtl,
            'priority' => $this->dnsRecordPriority,
        ];

        try {
            $result = $service->saveRecord($this->domain, $recordData, $this->editingDnsRecordId);

            if ($result['ok']) {
                session()->flash('message', $result['message'] ?? 'DNS record saved successfully!');
                $this->closeDnsRecordModal();
                $this->loadDomain();
            } else {
                $errorField = $result['error_field'] ?? 'dnsRecordValue';
                $this->addError($errorField, $result['error'] ?? 'Failed to save DNS record.');
            }
        } catch (\Exception $e) {
            $this->addError('dnsRecordValue', 'Error saving DNS record: '.$e->getMessage());
        }
    }

    public function deleteDnsRecord(string $recordId): void
    {
        if (! $this->domain) {
            session()->flash('error', 'Domain not found.');

            return;
        }

        try {
            $result = app(DomainDnsRecordService::class)->deleteRecord($this->domain, $recordId);

            if ($result['ok']) {
                session()->flash('message', $result['message'] ?? 'DNS record deleted successfully!');
                $this->loadDomain();
            } else {
                session()->flash('error', $result['error'] ?? 'Failed to delete DNS record.');
            }
        } catch (\Exception $e) {
            session()->flash('error', 'Error deleting DNS record: '.$e->getMessage());
            Log::error('DNS record deletion failed', [
                'domain_id' => $this->domain->id,
                'record_id' => $recordId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function resetDnsRecordForm(): void
    {
        $this->editingDnsRecordId = null;
        $this->dnsRecordHost = '';
        $this->dnsRecordType = 'A';
        $this->dnsRecordValue = '';
        $this->dnsRecordTtl = 300;
        $this->dnsRecordPriority = 0;
    }
}
