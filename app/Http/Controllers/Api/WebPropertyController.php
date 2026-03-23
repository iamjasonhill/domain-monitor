<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\WebPropertyHealthSummaryResource;
use App\Http\Resources\WebPropertyResource;
use App\Models\WebProperty;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WebPropertyController extends Controller
{
    private const DEFAULT_PER_PAGE = 50;

    private const MAX_PER_PAGE = 100;

    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'status' => 'nullable|in:active,planned,paused,archived',
            'property_type' => 'nullable|string|max:50',
            'per_page' => 'nullable|integer|min:1',
        ]);

        $perPage = min((int) ($validated['per_page'] ?? self::DEFAULT_PER_PAGE), self::MAX_PER_PAGE);

        $query = $this->baseQuery()->orderBy('name');

        if (isset($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (isset($validated['property_type'])) {
            $query->where('property_type', $validated['property_type']);
        }

        return WebPropertyResource::collection(
            $query->paginate($perPage)->appends($request->query())
        );
    }

    public function show(string $slug): WebPropertyResource|JsonResponse
    {
        $property = $this->findBySlug($slug);

        if (! $property) {
            return response()->json([
                'error' => 'Web property not found',
            ], 404);
        }

        return new WebPropertyResource($property);
    }

    public function summary(): JsonResponse
    {
        $properties = $this->baseQuery()->orderBy('name')->get();

        return response()->json([
            'source_system' => 'domain-monitor',
            'contract_version' => 1,
            'generated_at' => now()->toIso8601String(),
            'web_properties' => WebPropertyResource::collection($properties)->resolve(),
        ]);
    }

    public function healthSummary(string $slug): WebPropertyHealthSummaryResource|JsonResponse
    {
        $property = $this->findBySlug($slug);

        if (! $property) {
            return response()->json([
                'error' => 'Web property not found',
            ], 404);
        }

        return new WebPropertyHealthSummaryResource($property);
    }

    /**
     * @return Builder<WebProperty>
     */
    private function baseQuery(): Builder
    {
        return WebProperty::query()
            ->with([
                'primaryDomain',
                'repositories',
                'analyticsSources',
                'propertyDomains.domain' => function ($query) {
                    $query->withLatestCheckStatuses()
                        ->with([
                            'platform',
                            'tags',
                            'deployments.domain',
                            'alerts' => fn ($alertQuery) => $alertQuery->whereNull('resolved_at'),
                        ]);
                },
            ]);
    }

    private function findBySlug(string $slug): ?WebProperty
    {
        return $this->baseQuery()
            ->where('slug', $slug)
            ->first();
    }
}
