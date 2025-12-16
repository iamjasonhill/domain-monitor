<?php

namespace App\Livewire;

use App\Models\Domain;
use App\Models\DomainCheck;
use Livewire\Component;

class Dashboard extends Component
{
    public function render(): \Illuminate\Contracts\View\View
    {
        $stats = [
            'total_domains' => Domain::count(),
            'active_domains' => Domain::where('is_active', true)->count(),
            'expiring_soon' => Domain::where('is_active', true)
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', now()->addDays(30)->endOfDay())
                ->where('expires_at', '>', now())
                ->count(),
            'recent_failures' => DomainCheck::where('status', 'fail')
                ->where('created_at', '>=', now()->subHours(24))
                ->distinct('domain_id')
                ->count('domain_id'),
            'failed_eligibility' => Domain::where('eligibility_valid', false)->count(),
        ];

        $recentDomains = Domain::where('is_active', true)
            ->orderBy('updated_at', 'desc')
            ->limit(5)
            ->get();

        $recentChecks = DomainCheck::with('domain')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return view('livewire.dashboard', [
            'stats' => $stats,
            'recentDomains' => $recentDomains,
            'recentChecks' => $recentChecks,
        ]);
    }
}
