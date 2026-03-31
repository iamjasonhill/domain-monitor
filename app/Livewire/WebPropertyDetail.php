<?php

namespace App\Livewire;

use App\Models\WebProperty;
use App\Services\SearchConsoleIssueEvidenceService;
use App\Services\SearchConsoleIssueSnapshotImporter;
use Livewire\Component;
use Livewire\WithFileUploads;

class WebPropertyDetail extends Component
{
    use WithFileUploads;

    public string $propertySlug;

    public ?WebProperty $property = null;

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $searchConsoleIssueSummaries = [];

    public mixed $issueDetailArchive = null;

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
                'analyticsSources.latestSearchConsoleCoverage',
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

        $this->searchConsoleIssueSummaries = app(SearchConsoleIssueEvidenceService::class)
            ->propertyIssueSummaries($this->property);
    }

    public function importIssueDetail(SearchConsoleIssueSnapshotImporter $importer): void
    {
        $this->validate([
            'issueDetailArchive' => ['required', 'file', 'mimes:zip', 'max:5120'],
        ]);

        if (! $this->property instanceof WebProperty) {
            session()->flash('error', 'Property not found.');

            return;
        }

        try {
            $result = $importer->importDrilldownZipForProperty(
                $this->property,
                (string) $this->issueDetailArchive->getRealPath(),
                auth()->user()->email ?: 'domain_monitor_ui'
            );

            $this->issueDetailArchive = null;
            $this->loadProperty();

            session()->flash('message', sprintf(
                'Imported Search Console issue detail for %s (%s).',
                $this->property->name,
                data_get(config('domain_monitor.search_console_issue_catalog.'.$result['snapshot']->issue_class), 'label', $result['snapshot']->issue_class)
            ));
        } catch (\Throwable $exception) {
            session()->flash('error', 'Issue detail import failed: '.$exception->getMessage());
        }
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.web-property-detail');
    }
}
