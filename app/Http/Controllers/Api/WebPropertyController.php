<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\WebPropertyHealthSummaryResource;
use App\Http\Resources\WebPropertyResource;
use App\Http\Resources\WebPropertySummaryResource;
use App\Models\MonitoringFinding;
use App\Models\WebProperty;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Schema;

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
            'include_external_links' => 'nullable|boolean',
        ]);

        $includeExternalLinks = (bool) ($validated['include_external_links'] ?? false);

        $query = $this->baseQuery(includeExternalLinkDetails: $includeExternalLinks)->orderBy('name');
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
        $relations = [
            'primaryDomain.tags',
            'repositories',
            'analyticsSources',
            'analyticsSources.latestInstallAudit',
            'propertyDomains.domain' => function ($query) use ($includeExternalLinkDetails) {
                $domainRelations = [
                    'platform',
                    'tags',
                    'dnsRecords',
                    'latestEmailSecurityCheck',
                    'deployments.domain',
                    'alerts' => fn ($alertQuery) => $alertQuery->whereNull('resolved_at'),
                ];

                if ($includeExternalLinkDetails) {
                    $domainRelations[] = 'latestExternalLinksCheck';
                }

                $query->withLatestCheckStatuses()
                    ->with($domainRelations);
            },
        ];

        if ($this->eventArchitectureTablesExist()) {
            $relations[] = 'eventContractAssignments.eventContract';
        }

        if ($this->conversionSurfaceTablesExist()) {
            $relations[] = 'conversionSurfaces.domain';
            $relations[] = 'conversionSurfaces.analyticsSource';
            $relations[] = 'conversionSurfaces.eventContractAssignment.eventContract';
        }

        if ($this->monitoringFindingsTableExists()) {
            $relations['monitoringFindings'] = fn ($query) => $query
                ->where('status', MonitoringFinding::STATUS_OPEN)
                ->with('domain:id,domain,platform,dns_config_name,parked_override')
                ->orderByDesc('last_detected_at');
        }

        if (Schema::hasTable('domain_seo_baselines')) {
            $relations['seoBaselines'] = fn ($query) => $query
                ->orderByDesc('captured_at')
                ->orderByDesc('created_at')
                ->limit(12);
        }

        $query = WebProperty::query();

        if (Schema::hasTable('search_console_issue_snapshots')) {
            $query->withGscEvidenceSummaryAttributes();
        }

        return $query->with($relations);
    }

    private function eventArchitectureTablesExist(): bool
    {
        return Schema::hasTable('web_property_event_contracts')
            && Schema::hasTable('analytics_event_contracts');
    }

    private function conversionSurfaceTablesExist(): bool
    {
        return Schema::hasTable('web_property_conversion_surfaces')
            && $this->eventArchitectureTablesExist();
    }

    private function monitoringFindingsTableExists(): bool
    {
        return Schema::hasTable('monitoring_findings');
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
