<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BrandStyleSurfaceDraftBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BrandStyleSurfaceDraftController extends Controller
{
    public function __invoke(Request $request, BrandStyleSurfaceDraftBuilder $draftBuilder): JsonResponse
    {
        $validated = $request->validate([
            'hostname' => 'nullable|string|max:255',
        ]);

        return response()->json($draftBuilder->build(
            hostname: $validated['hostname'] ?? null,
        ));
    }
}
