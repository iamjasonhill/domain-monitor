<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\WebPropertyHealthSummaryResource;
use App\Http\Resources\WebPropertyResource;
use App\Models\SearchConsoleIssueSnapshot;
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

        $query = $this->baseQuery()->orderBy('name');
        $this->applyFleetFocusFilter($query, $validated);

        $properties = $query->get();

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
            ->addSelect([
                'has_gsc_issue_detail' => SearchConsoleIssueSnapshot::query()
                    ->selectRaw('1')
                    ->whereColumn('web_property_id', 'web_properties.id')
                    ->where('capture_method', 'gsc_drilldown_zip')
                    ->limit(1),
                'gsc_issue_detail_snapshot_count' => SearchConsoleIssueSnapshot::query()
                    ->selectRaw('count(*)')
                    ->whereColumn('web_property_id', 'web_properties.id')
                    ->where('capture_method', 'gsc_drilldown_zip'),
                'gsc_issue_detail_last_captured_at' => SearchConsoleIssueSnapshot::query()
                    ->selectRaw('max(captured_at)')
                    ->whereColumn('web_property_id', 'web_properties.id')
                    ->where('capture_method', 'gsc_drilldown_zip'),
                'has_gsc_api_enrichment' => SearchConsoleIssueSnapshot::query()
                    ->selectRaw('1')
                    ->whereColumn('web_property_id', 'web_properties.id')
                    ->whereIn('capture_method', ['gsc_api', 'gsc_mcp_api'])
                    ->limit(1),
                'gsc_api_snapshot_count' => SearchConsoleIssueSnapshot::query()
                    ->selectRaw('count(*)')
                    ->whereColumn('web_property_id', 'web_properties.id')
                    ->whereIn('capture_method', ['gsc_api', 'gsc_mcp_api']),
                'gsc_api_last_captured_at' => SearchConsoleIssueSnapshot::query()
                    ->selectRaw('max(captured_at)')
                    ->whereColumn('web_property_id', 'web_properties.id')
                    ->whereIn('capture_method', ['gsc_api', 'gsc_mcp_api']),
            ])
            ->with([
                'primaryDomain.tags',
                'repositories',
                'analyticsSources',
                'analyticsSources.latestInstallAudit',
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
