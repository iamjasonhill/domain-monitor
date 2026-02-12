<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DomainFullResource;
use App\Http\Resources\DomainResource;
use App\Models\Domain;
use App\Models\DomainTag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;

class DomainController extends Controller
{
    private const DEFAULT_PER_PAGE = 50;

    private const MAX_PER_PAGE = 100;

    /**
     * Display a listing of all domains.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'tag' => 'nullable|string|max:100',
            'status' => 'nullable|in:active,inactive',
            'platform' => 'nullable|string|max:100',
            'scaffolding_status' => 'nullable|in:pending,in_progress,complete,failed',
            'target_platform' => 'nullable|string|max:100',
            'per_page' => 'nullable|integer|min:1',
        ]);

        $perPage = min((int) ($validated['per_page'] ?? self::DEFAULT_PER_PAGE), self::MAX_PER_PAGE);

        $query = Domain::query()->orderBy('domain');

        // Optional tag filter
        if (isset($validated['tag'])) {
            $query->whereHas('tags', function ($q) use ($validated) {
                $q->where('name', 'like', $validated['tag']);
            });
        }

        // Optional status filter
        if (isset($validated['status'])) {
            $query->where('is_active', $validated['status'] === 'active');
        }

        // Optional platform filter
        if (isset($validated['platform'])) {
            $platformFilter = $validated['platform'];
            $query->where(function ($q) use ($platformFilter) {
                $q->where('platform', 'like', "%{$platformFilter}%")
                    ->orWhereHas('platform', function ($pq) use ($platformFilter) {
                        $pq->where('platform_type', 'like', "%{$platformFilter}%");
                    });
            });
        }

        // Optional scaffolding status filter
        if (isset($validated['scaffolding_status'])) {
            $query->where('scaffolding_status', $validated['scaffolding_status']);
        }

        // Optional target platform filter
        if (isset($validated['target_platform'])) {
            $query->where('target_platform', 'like', '%'.$validated['target_platform'].'%');
        }

        return DomainResource::collection(
            $query->paginate($perPage)->appends($request->query())
        );
    }

    /**
     * Display a single domain with full details.
     */
    public function show(Request $request, string $domainId): DomainFullResource|JsonResponse
    {
        // Try finding by ID first
        $domain = Domain::find($domainId);

        // Fall back to finding by domain name
        if (! $domain) {
            $domain = Domain::where('domain', $domainId)->first();
        }

        // Fall back to finding by project_key
        if (! $domain) {
            $domain = Domain::where('project_key', $domainId)->first();
        }

        if (! $domain) {
            return response()->json([
                'error' => 'Domain not found',
            ], 404);
        }

        return new DomainFullResource($domain);
    }

    /**
     * Update a domain's fields (for WebForge integration).
     */
    public function update(Request $request, string $domainId): JsonResponse
    {
        $domain = Domain::find($domainId);

        if (! $domain) {
            return response()->json([
                'error' => 'Domain not found',
            ], 404);
        }

        $validated = $request->validate([
            'platform' => 'nullable|string|max:100',
            'target_platform' => 'nullable|string|max:100',
            'migration_tier' => 'nullable|integer|min:1|max:3',
            'scaffolding_status' => 'nullable|in:pending,in_progress,complete,failed',
            'scaffolded_by' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
            'tags' => 'nullable|array',
            'tags.*' => 'exists:domain_tags,id',
        ]);

        // If scaffolding_status is set to 'complete', set scaffolded_at
        if (isset($validated['scaffolding_status']) && $validated['scaffolding_status'] === 'complete') {
            $validated['scaffolded_at'] = now();
        }

        // Update domain fields (excluding tags)
        $domain->update(Arr::except($validated, ['tags']));

        // Sync tags if provided
        if (isset($validated['tags'])) {
            $domain->tags()->sync($validated['tags']);
        }

        return response()->json([
            'message' => 'Domain updated successfully',
            'data' => new DomainFullResource($domain->fresh(['platform', 'tags'])),
        ]);
    }

    /**
     * Add a tag to a domain.
     */
    public function addTag(Request $request, string $domainId, string $tagId): JsonResponse
    {
        $domain = Domain::find($domainId);
        if (! $domain) {
            return response()->json(['error' => 'Domain not found'], 404);
        }

        $tag = DomainTag::find($tagId);
        if (! $tag) {
            // Try finding by name
            $tag = DomainTag::where('name', $tagId)->first();
        }

        if (! $tag) {
            return response()->json(['error' => 'Tag not found'], 404);
        }

        $domain->tags()->syncWithoutDetaching([$tag->id]);

        return response()->json([
            'message' => 'Tag added successfully',
            'data' => new DomainFullResource($domain->fresh(['platform', 'tags'])),
        ]);
    }

    /**
     * Remove a tag from a domain.
     */
    public function removeTag(Request $request, string $domainId, string $tagId): JsonResponse
    {
        $domain = Domain::find($domainId);
        if (! $domain) {
            return response()->json(['error' => 'Domain not found'], 404);
        }

        $tag = DomainTag::find($tagId);
        if (! $tag) {
            // Try finding by name
            $tag = DomainTag::where('name', $tagId)->first();
        }

        if (! $tag) {
            return response()->json(['error' => 'Tag not found'], 404);
        }

        $domain->tags()->detach($tag->id);

        return response()->json([
            'message' => 'Tag removed successfully',
            'data' => new DomainFullResource($domain->fresh(['platform', 'tags'])),
        ]);
    }
}
