<?php

namespace App\Livewire;

use App\Models\WebProperty;
use Livewire\Component;

class WebPropertyDetail extends Component
{
    public string $propertySlug;

    public ?WebProperty $property = null;

    public function mount(): void
    {
        $this->loadProperty();
    }

    public function loadProperty(): void
    {
        $this->property = WebProperty::query()
            ->with([
                'primaryDomain',
                'repositories',
                'analyticsSources',
                'analyticsSources.latestInstallAudit',
                'propertyDomains.domain' => function ($query): void {
                    $query->withLatestCheckStatuses()
                        ->with([
                            'platform',
                            'tags',
                            'deployments.domain',
                            'alerts' => fn ($alertQuery) => $alertQuery->whereNull('resolved_at'),
                        ]);
                },
            ])
            ->where('slug', $this->propertySlug)
            ->firstOrFail();
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.web-property-detail');
    }
}
