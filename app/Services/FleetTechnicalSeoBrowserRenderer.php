<?php

namespace App\Services;

interface FleetTechnicalSeoBrowserRenderer
{
    /**
     * @return array<string, mixed>
     */
    public function render(string $url): array;
}
