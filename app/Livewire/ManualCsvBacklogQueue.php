<?php

namespace App\Livewire;

use App\Models\WebProperty;
use App\Services\ManualCsvBacklogService;
use App\Services\ManualSearchConsoleEvidenceImporter;
use Illuminate\Support\Facades\Artisan;
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
            $key => ['required', 'file', 'mimes:zip', 'max:5120'],
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

            $tagRefreshError = $this->refreshCoverageTags($property);

            unset($this->evidenceArchives[$propertyId]);

            session()->flash('message', sprintf(
                'Imported Search Console evidence for %s. Baseline %s recorded.%s',
                $property->name,
                $result['baseline']->captured_at->format('Y-m-d H:i'),
                $tagRefreshError ? ' Coverage tags will refresh on the next scheduled sync.' : ' Coverage tags refreshed.'
            ));
        } catch (\Throwable $exception) {
            session()->flash('error', 'Manual CSV import failed: '.$exception->getMessage());
        }
    }

    private function refreshCoverageTags(WebProperty $property): ?string
    {
        $domain = $property->primaryDomainName();

        if (! is_string($domain) || $domain === '') {
            return 'Missing primary domain.';
        }

        try {
            $exitCode = Artisan::call('coverage:sync-tags', [
                '--domain' => [$domain],
            ]);
        } catch (\Throwable $exception) {
            return $exception->getMessage();
        }

        return $exitCode === 0 ? null : trim(Artisan::output());
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
