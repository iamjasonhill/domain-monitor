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

    public ?string $targetMoverooSubdomainUrl = null;

    public ?string $targetContactUsPageUrl = null;

    public ?string $targetLegacyBookingsReplacementUrl = null;

    public ?string $targetLegacyPaymentsReplacementUrl = null;

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
        $this->targetMoverooSubdomainUrl = $this->property->target_moveroo_subdomain_url;
        $this->targetContactUsPageUrl = $this->property->target_contact_us_page_url;
        $this->targetLegacyBookingsReplacementUrl = $this->property->target_legacy_bookings_replacement_url;
        $this->targetLegacyPaymentsReplacementUrl = $this->property->target_legacy_payments_replacement_url;
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

        $this->authorizePropertyMutation();

        $normalizedTargets = [
            'targetHouseholdQuoteUrl' => $this->normalizeTargetUrl($this->targetHouseholdQuoteUrl),
            'targetHouseholdBookingUrl' => $this->normalizeTargetUrl($this->targetHouseholdBookingUrl),
            'targetVehicleQuoteUrl' => $this->normalizeTargetUrl($this->targetVehicleQuoteUrl),
            'targetVehicleBookingUrl' => $this->normalizeTargetUrl($this->targetVehicleBookingUrl),
            'targetMoverooSubdomainUrl' => $this->normalizeTargetUrl($this->targetMoverooSubdomainUrl),
            'targetContactUsPageUrl' => $this->normalizeTargetUrl($this->targetContactUsPageUrl),
            'targetLegacyBookingsReplacementUrl' => $this->normalizeTargetUrl($this->targetLegacyBookingsReplacementUrl),
            'targetLegacyPaymentsReplacementUrl' => $this->normalizeTargetUrl($this->targetLegacyPaymentsReplacementUrl),
        ];

        $this->resetValidation();

        $validated = Validator::make(
            $normalizedTargets,
            [
                'targetHouseholdQuoteUrl' => ['nullable', 'url', 'max:2048', 'regex:/^https?:\\/\\//i'],
                'targetHouseholdBookingUrl' => ['nullable', 'url', 'max:2048', 'regex:/^https?:\\/\\//i'],
                'targetVehicleQuoteUrl' => ['nullable', 'url', 'max:2048', 'regex:/^https?:\\/\\//i'],
                'targetVehicleBookingUrl' => ['nullable', 'url', 'max:2048', 'regex:/^https?:\\/\\//i'],
                'targetMoverooSubdomainUrl' => ['nullable', 'url', 'max:2048', 'regex:/^https?:\\/\\//i'],
                'targetContactUsPageUrl' => ['nullable', 'url', 'max:2048', 'regex:/^https?:\\/\\//i'],
                'targetLegacyBookingsReplacementUrl' => ['nullable', 'url', 'max:2048', 'regex:/^https?:\\/\\//i'],
                'targetLegacyPaymentsReplacementUrl' => ['nullable', 'url', 'max:2048', 'regex:/^https?:\\/\\//i'],
            ]
        )->validate();

        $this->property->update([
            'target_household_quote_url' => $validated['targetHouseholdQuoteUrl'],
            'target_household_booking_url' => $validated['targetHouseholdBookingUrl'],
            'target_vehicle_quote_url' => $validated['targetVehicleQuoteUrl'],
            'target_vehicle_booking_url' => $validated['targetVehicleBookingUrl'],
            'target_moveroo_subdomain_url' => $validated['targetMoverooSubdomainUrl'],
            'target_contact_us_page_url' => $validated['targetContactUsPageUrl'],
            'target_legacy_bookings_replacement_url' => $validated['targetLegacyBookingsReplacementUrl'],
            'target_legacy_payments_replacement_url' => $validated['targetLegacyPaymentsReplacementUrl'],
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

        $this->authorizePropertyMutation();

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

    private function normalizeTargetUrl(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function authorizePropertyMutation(): void
    {
        $user = auth()->user();

        abort_unless($user !== null, 403);

        if (app()->environment(['local', 'testing'])) {
            return;
        }

        $allowedEmails = array_filter(array_map(
            static fn (mixed $email): string => mb_strtolower(trim((string) $email)),
            (array) config('domain_monitor.property_mutation_emails', config('horizon.allowed_emails', []))
        ));

        abort_unless(
            in_array(mb_strtolower((string) $user->email), $allowedEmails, true),
            403
        );
    }
}
