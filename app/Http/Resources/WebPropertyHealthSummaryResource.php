<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WebPropertyHealthSummaryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->resource->slug,
            'name' => $this->resource->name,
            'health_summary' => $this->resource->healthSummary(),
            'updated_at' => $this->resource->updated_at?->toIso8601String(),
        ];
    }
}
