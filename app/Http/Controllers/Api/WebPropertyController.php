<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\WebPropertyHealthSummaryResource;
use App\Http\Resources\WebPropertyResource;
use App\Http\Resources\WebPropertySummaryResource;
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
            'fleet_focus' => 'nullable|boolean',
            'per_page' => 'nullable|integer|min:1',
        ]);

        $perPage = min((int) ($validated['per_page'] ?? self::DEFAULT_PER_PAGE), self::MAX_PER_PAGE);

        $query = $this->baseQuery()->orderBy('name');

        $this->applyFleetFocusFilter($query, $validated);

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

    public function summary(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fleet_focus' => 'nullable|boolean',
        ]);

        $query = $this->baseQuery(includeExternalLinkDetails: false)->orderBy('name');
        $this->applyFleetFocusFilter($query, $validated);

        $properties = $query->get();

        return response()->json([
            'source_system' => 'domain-monitor',
            'contract_version' => 1,
            'generated_at' => now()->toIso8601String(),
            'web_properties' => WebPropertySummaryResource::collection($properties)->resolve(),
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
    private function baseQuery(bool $includeExternalLinkDetails = true): Builder
    {
        return WebProperty::query()
            ->withGscEvidenceSummaryAttributes()
            ->with([
                'primaryDomain.tags',
                'repositories',
                'analyticsSources',
                'analyticsSources.latestInstallAudit',
                'seoBaselines' => fn ($query) => $query
                    ->orderByDesc('captured_at')
                    ->orderByDesc('created_at')
                    ->limit(12),
                'propertyDomains.domain' => function ($query) use ($includeExternalLinkDetails) {
                    $relations = [
                        'platform',
                        'tags',
                        'deployments.domain',
                        'alerts' => fn ($alertQuery) => $alertQuery->whereNull('resolved_at'),
                    ];

                    if ($includeExternalLinkDetails) {
                        $relations[] = 'latestExternalLinksCheck';
                    }

                    $query->withLatestCheckStatuses()
                        ->with($relations);
                },
            ]);
    }

    private function findBySlug(string $slug): ?WebProperty
    {
        return $this->baseQuery()
            ->where('slug', $slug)
            ->first();
    }

    /**
     * @param  Builder<WebProperty>  $query
     * @param  array<string, mixed>  $validated
     */
    private function applyFleetFocusFilter(Builder $query, array $validated): void
    {
        if (! array_key_exists('fleet_focus', $validated)) {
            return;
        }

        if ($validated['fleet_focus'] === null) {
            return;
        }

        $tagName = (string) config('domain_monitor.fleet_focus.tag_name', 'fleet.live');

        if ($tagName === '') {
            return;
        }

        if ((bool) $validated['fleet_focus']) {
            $query->fleetFocus();

            return;
        }

        $query->fleetFocus(false);
    }
}
