<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Artisan;
use Livewire\Component;

class CommandsList extends Component
{
    public array $commands = [];

    public function mount(): void
    {
        $this->loadCommands();
    }

    protected function loadCommands(): void
    {
        // Get all Artisan commands using the Artisan facade
        $artisan = app(\Illuminate\Contracts\Console\Kernel::class);
        $artisanCommands = $artisan->all();

        $this->commands = collect($artisanCommands)
            ->map(function ($command, $name) {
                return [
                    'name' => $name,
                    'description' => $command->getDescription() ?: 'No description available',
                    'signature' => $command->getSynopsis() ?: $name,
                ];
            })
            ->sortBy('name')
            ->values()
            ->toArray();
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('livewire.commands-list');
    }
}
