<?php

namespace App\Livewire;

use App\Services\DomainMonitorSettings;
use Livewire\Component;

class SettingsMonitoring extends Component
{
    public int $recentFailuresHours = 24;

    public function mount(DomainMonitorSettings $settings): void
    {
        $this->recentFailuresHours = $settings->recentFailuresHours();
    }

    public function save(DomainMonitorSettings $settings): void
    {
        $this->validate([
            'recentFailuresHours' => 'required|integer|min:1|max:168',
        ]);

        $settings->setRecentFailuresHours($this->recentFailuresHours);

        $this->recentFailuresHours = $settings->recentFailuresHours();

        session()->flash('message', 'Monitoring settings updated.');
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('livewire.settings-monitoring');
    }
}
