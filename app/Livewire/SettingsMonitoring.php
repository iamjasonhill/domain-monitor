<?php

namespace App\Livewire;

use App\Services\DomainMonitorSettings;
use Livewire\Component;

class SettingsMonitoring extends Component
{
    public int $recentFailuresHours = 24;

    public int $pruneDomainChecksDays = 90;

    public int $pruneEligibilityChecksDays = 180;

    public function mount(DomainMonitorSettings $settings): void
    {
        $this->recentFailuresHours = $settings->recentFailuresHours();
        $this->pruneDomainChecksDays = $settings->pruneDomainChecksDays();
        $this->pruneEligibilityChecksDays = $settings->pruneEligibilityChecksDays();
    }

    public function save(DomainMonitorSettings $settings): void
    {
        $this->validate([
            'recentFailuresHours' => 'required|integer|min:1|max:168',
            'pruneDomainChecksDays' => 'required|integer|min:1|max:3650',
            'pruneEligibilityChecksDays' => 'required|integer|min:1|max:3650',
        ]);

        $settings->setRecentFailuresHours($this->recentFailuresHours);
        $settings->setPruneDomainChecksDays($this->pruneDomainChecksDays);
        $settings->setPruneEligibilityChecksDays($this->pruneEligibilityChecksDays);

        $this->recentFailuresHours = $settings->recentFailuresHours();
        $this->pruneDomainChecksDays = $settings->pruneDomainChecksDays();
        $this->pruneEligibilityChecksDays = $settings->pruneEligibilityChecksDays();

        session()->flash('message', 'Monitoring settings updated.');
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('livewire.settings-monitoring');
    }
}
