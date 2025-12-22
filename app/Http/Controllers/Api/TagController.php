<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DomainResource;
use App\Models\DomainTag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TagController extends Controller
{
    /**
     * List all tags with domain counts.
     */
    public function index(Request $request): JsonResponse
    {
        $tags = DomainTag::query()
            ->withCount('domains')
            ->orderBy('priority', 'desc')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $tags->map(fn (DomainTag $tag) => [
                'id' => $tag->id,
                'name' => $tag->name,
                'color' => $tag->color,
                'priority' => $tag->priority,
                'description' => $tag->description,
                'domains_count' => $tag->domains_count,
            ]),
        ]);
    }

    /**
     * List domains for a specific tag.
     */
    public function domains(Request $request, string $tagId): AnonymousResourceCollection|JsonResponse
    {
        $tag = DomainTag::find($tagId);

        if (! $tag) {
            // Try finding by name (slug-like lookup)
            $tag = DomainTag::where('name', 'like', $tagId)->first();
        }

        if (! $tag) {
            return response()->json([
                'error' => 'Tag not found',
            ], 404);
        }

        $domains = $tag->domains()
            ->orderBy('domain')
            ->get();

        return DomainResource::collection($domains);
    }
}
