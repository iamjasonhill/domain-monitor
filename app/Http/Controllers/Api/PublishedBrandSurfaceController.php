<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PublishedBrandSurfaceFeedBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublishedBrandSurfaceController extends Controller
{
    public function __invoke(Request $request, PublishedBrandSurfaceFeedBuilder $feedBuilder): JsonResponse
    {
        $validated = $request->validate([
            'hostname' => 'nullable|string|max:255',
        ]);

        return response()->json($feedBuilder->build(
            hostname: $validated['hostname'] ?? null,
        ));
    }
}
