<?php

namespace App\Livewire;

use App\Models\WebProperty;
use App\Services\PropertyConversionLinkScanner;
use App\Services\SearchConsoleIssueEvidenceService;
use App\Services\SearchConsoleIssueSnapshotImporter;
use Illuminate\Support\Facades\Validator;
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

    public ?string $targetHouseholdQuoteUrl = null;

    public ?string $targetHouseholdBookingUrl = null;

    public ?string $targetVehicleQuoteUrl = null;

    public ?string $targetVehicleBookingUrl = null;

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

        $this->targetHouseholdQuoteUrl = $this->property->target_household_quote_url;
        $this->targetHouseholdBookingUrl = $this->property->target_household_booking_url;
        $this->targetVehicleQuoteUrl = $this->property->target_vehicle_quote_url;
        $this->targetVehicleBookingUrl = $this->property->target_vehicle_booking_url;
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
                auth()->user()?->email ?: 'domain_monitor_ui'
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

    public function saveConversionTargets(): void
    {
        if (! $this->property instanceof WebProperty) {
            session()->flash('error', 'Property not found.');

            return;
        }

        abort_unless(auth()->check(), 403);

        $validated = Validator::make(
            [
                'targetHouseholdQuoteUrl' => $this->targetHouseholdQuoteUrl,
                'targetHouseholdBookingUrl' => $this->targetHouseholdBookingUrl,
                'targetVehicleQuoteUrl' => $this->targetVehicleQuoteUrl,
                'targetVehicleBookingUrl' => $this->targetVehicleBookingUrl,
            ],
            [
                'targetHouseholdQuoteUrl' => ['nullable', 'url', 'max:2048'],
                'targetHouseholdBookingUrl' => ['nullable', 'url', 'max:2048'],
                'targetVehicleQuoteUrl' => ['nullable', 'url', 'max:2048'],
                'targetVehicleBookingUrl' => ['nullable', 'url', 'max:2048'],
            ]
        )->validate();

        $this->property->update([
            'target_household_quote_url' => $validated['targetHouseholdQuoteUrl'],
            'target_household_booking_url' => $validated['targetHouseholdBookingUrl'],
            'target_vehicle_quote_url' => $validated['targetVehicleQuoteUrl'],
            'target_vehicle_booking_url' => $validated['targetVehicleBookingUrl'],
        ]);

        $this->loadProperty();

        session()->flash('message', 'Target conversion links updated.');
    }

    public function refreshCurrentConversionLinks(PropertyConversionLinkScanner $scanner): void
    {
        if (! $this->property instanceof WebProperty) {
            session()->flash('error', 'Property not found.');

            return;
        }

        abort_unless(auth()->check(), 403);

        try {
            $scanner->persistForProperty($this->property);
            $this->loadProperty();

            session()->flash('message', 'Current conversion links refreshed from the live site navigation.');
        } catch (\Throwable $exception) {
            session()->flash('error', 'Conversion link scan failed: '.$exception->getMessage());
        }
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.web-property-detail');
    }
}
