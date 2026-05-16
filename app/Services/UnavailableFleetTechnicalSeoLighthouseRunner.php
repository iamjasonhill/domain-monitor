<?php

namespace App\Services;

class UnavailableFleetTechnicalSeoLighthouseRunner implements FleetTechnicalSeoLighthouseRunner
{
    /**
     * @return array<string, mixed>
     */
    public function run(string $url): array
    {
        return [
            'available' => false,
            'url' => $url,
            'reason' => 'No Lighthouse lab adapter is configured for this environment.',
        ];
    }
}
