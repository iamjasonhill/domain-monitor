<?php

namespace App\Livewire;

use App\Models\Domain;
use App\Models\DomainTag;
use App\Services\HostingDetector;
use App\Services\PlatformDetector;
use Livewire\Component;

class DomainForm extends Component
{
    public ?string $domainId = null;

    public Domain $domain;

    public string $domain_name = '';

    public ?string $project_key = null;

    public ?string $registrar = null;

    public ?string $hosting_provider = null;

    public ?string $hosting_admin_url = null;

    public ?string $platform = null;

    public ?string $notes = null;

    public bool $is_active = true;

    public int $check_frequency_minutes = 60;

    /**
     * @var array<int, string>
     */
    public array $selectedTags = [];

    public function mount(?string $domainId = null): void
    {
        $this->domainId = $domainId;

        if ($this->domainId) {
            $this->domain = Domain::with(['platform', 'tags'])->findOrFail($this->domainId);
            $this->domain_name = $this->domain->domain;
            $this->project_key = $this->domain->project_key;
            $this->registrar = $this->domain->registrar;
            $this->hosting_provider = $this->domain->hosting_provider;
            $this->hosting_admin_url = $this->domain->hosting_admin_url;
            // Use platform from relationship if available, otherwise fallback to simple field
            $this->platform = $this->domain->platform?->platform_type ?? $this->domain->platform;
            $this->notes = $this->domain->notes;
            $this->is_active = $this->domain->is_active;
            $this->check_frequency_minutes = $this->domain->check_frequency_minutes;
            $this->selectedTags = $this->domain->tags->pluck('id')->toArray();
        } else {
            $this->domain = new Domain;
        }
    }

    /**
     * @return array<string, array<int, string>>
     */
    protected function rules(): array
    {
        return [
            'domain_name' => ['required', 'string', 'max:255', 'regex:/^([a-z0-9]+(-[a-z0-9]+)*\.)+[a-z]{2,}$/i'],
            'project_key' => ['nullable', 'string', 'max:255'],
            'registrar' => ['nullable', 'string', 'max:255'],
            'hosting_provider' => ['nullable', 'string', 'max:255'],
            'hosting_admin_url' => ['nullable', 'url', 'max:255'],
            'platform' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['boolean'],
            'check_frequency_minutes' => ['required', 'integer', 'min:1', 'max:10080'], // Max 1 week
        ];
    }

    public function save(): void
    {
        $this->validate();

        // Check if domain already exists (for create mode)
        if (! $this->domainId) {
            $existing = Domain::where('domain', $this->domain_name)->first();
            if ($existing) {
                $this->addError('domain_name', 'This domain already exists.');

                return;
            }
        } else {
            // Check if domain name changed and conflicts with another domain
            if ($this->domain->domain !== $this->domain_name) {
                $existing = Domain::where('domain', $this->domain_name)
                    ->where('id', '!=', $this->domainId)
                    ->first();
                if ($existing) {
                    $this->addError('domain_name', 'This domain already exists.');

                    return;
                }
            }
        }

        $this->domain->domain = $this->domain_name;
        $this->domain->project_key = $this->project_key;
        $this->domain->registrar = $this->registrar;
        $this->domain->hosting_provider = $this->hosting_provider;
        $this->domain->hosting_admin_url = $this->hosting_admin_url;
        $this->domain->platform = $this->platform;
        $this->domain->notes = $this->notes;

        // If we have a platform relationship, sync the simple platform field
        if ($this->domain->platform && $this->platform) {
            $this->domain->platform()->updateOrCreate(
                ['domain_id' => $this->domain->id],
                ['platform_type' => $this->platform]
            );
        }
        $this->domain->is_active = $this->is_active;
        $this->domain->check_frequency_minutes = $this->check_frequency_minutes;

        $this->domain->save();

        // Sync tags
        $this->domain->tags()->sync($this->selectedTags);

        $action = $this->domainId ? 'updated' : 'created';
        session()->flash('message', "Domain '{$this->domain->domain}' has been {$action} successfully!");

        $this->redirect(route('domains.show', $this->domain->id), navigate: true);
    }

    public function detectPlatform(PlatformDetector $detector): void
    {
        if (! $this->domain_name) {
            session()->flash('error', 'Please enter a domain name first.');

            return;
        }

        try {
            $result = $detector->detect($this->domain_name);
            $this->platform = $result['platform_type'] ?? null;

            if ($this->platform) {
                session()->flash('message', "Platform detected: {$this->platform} ({$result['detection_confidence']} confidence)");
            } else {
                session()->flash('error', 'Could not detect platform. Please enter manually.');
            }
        } catch (\Exception $e) {
            session()->flash('error', 'Platform detection failed: '.$e->getMessage());
        }
    }

    public function detectHosting(HostingDetector $detector): void
    {
        if (! $this->domain_name) {
            session()->flash('error', 'Please enter a domain name first.');

            return;
        }

        try {
            $result = $detector->detect($this->domain_name);
            $this->hosting_provider = $result['provider'] ?? null;
            $this->hosting_admin_url = $result['admin_url'] ?? null;

            if ($this->hosting_provider) {
                session()->flash('message', "Hosting detected: {$this->hosting_provider} ({$result['confidence']} confidence)");
            } else {
                session()->flash('error', 'Could not detect hosting provider. Please enter manually.');
            }
        } catch (\Exception $e) {
            session()->flash('error', 'Hosting detection failed: '.$e->getMessage());
        }
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        $availableTags = DomainTag::orderedByPriority()->get();

        return view('livewire.domain-form', [
            'availableTags' => $availableTags,
        ]);
    }
}
