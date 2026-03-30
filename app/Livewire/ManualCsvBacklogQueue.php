<?php

namespace App\Livewire;

use App\Services\ManualCsvBacklogService;
use Livewire\Component;

class ManualCsvBacklogQueue extends Component
{
    public function render(): \Illuminate\View\View
    {
        $service = app(ManualCsvBacklogService::class);
        $pendingItems = collect($service->pendingItems());

        return view('livewire.manual-csv-backlog-queue', [
            'pendingItems' => $pendingItems,
            'stats' => $service->stats(),
        ]);
    }
}
