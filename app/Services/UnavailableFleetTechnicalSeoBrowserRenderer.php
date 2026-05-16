<?php

namespace App\Services;

class UnavailableFleetTechnicalSeoBrowserRenderer implements FleetTechnicalSeoBrowserRenderer
{
    /**
     * @return array<string, mixed>
     */
    public function render(string $url): array
    {
        return [
            'available' => false,
            'url' => $url,
            'reason' => 'No browser-render adapter is configured for this environment.',
        ];
    }
}
