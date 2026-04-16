<?php

namespace App\Services;

use App\Models\WebProperty;
use Illuminate\Support\Carbon;

class WebPropertyPlatformMigrationSummaryBuilder
{
    /**
     * @return array{
     *   current_platform: string|null,
     *   target_platform: string|null,
     *   astro_cutover_at: string|null
     * }
     */
    public function build(WebProperty $property): array
    {
        return [
            'current_platform' => $this->nullableString($property->platform),
            'target_platform' => $this->nullableString($property->target_platform),
            'astro_cutover_at' => $this->timestamp($property->astro_cutover_at),
        ];
    }

    private function timestamp(mixed $value): ?string
    {
        if ($value instanceof Carbon) {
            return $value->toIso8601String();
        }

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return Carbon::parse($value)->toIso8601String();
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
