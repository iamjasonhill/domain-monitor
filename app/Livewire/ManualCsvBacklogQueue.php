<?php

namespace App\Livewire;

use App\Models\WebProperty;
use App\Services\ManualCsvBacklogService;
use App\Services\ManualSearchConsoleEvidenceImporter;
use Livewire\Component;
use Livewire\WithFileUploads;

class ManualCsvBacklogQueue extends Component
{
    use WithFileUploads;

    /**
     * @var array<string, mixed>
     */
    public array $evidenceArchives = [];

    public function importEvidence(string $propertyId, ManualSearchConsoleEvidenceImporter $importer): void
    {
        $key = 'evidenceArchives.'.$propertyId;

        $this->validate([
            $key => ['required', 'file', 'mimes:zip'],
        ]);

        $property = WebProperty::query()->find($propertyId);

        if (! $property instanceof WebProperty) {
            session()->flash('error', 'Property not found.');

            return;
        }

        try {
            $result = $importer->importForProperty(
                $property,
                (string) $this->evidenceArchives[$propertyId]->getRealPath(),
                auth()->user()->email ?: 'domain_monitor_ui'
            );

            unset($this->evidenceArchives[$propertyId]);

            session()->flash('message', sprintf(
                'Imported Search Console evidence for %s. Baseline %s recorded.',
                $property->name,
                $result['baseline']->captured_at->format('Y-m-d H:i')
            ));
        } catch (\Throwable $exception) {
            session()->flash('error', 'Manual CSV import failed: '.$exception->getMessage());
        }
    }

    public function render(): \Illuminate\View\View
    {
        $service = app(ManualCsvBacklogService::class);
        $snapshot = $service->snapshot();

        return view('livewire.manual-csv-backlog-queue', [
            'pendingItems' => $snapshot['items'],
            'stats' => $snapshot['stats'],
        ]);
    }
}
