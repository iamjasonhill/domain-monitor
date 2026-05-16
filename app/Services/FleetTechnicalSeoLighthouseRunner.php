<?php

namespace App\Services;

interface FleetTechnicalSeoLighthouseRunner
{
    /**
     * @return array<string, mixed>
     */
    public function run(string $url): array;
}
