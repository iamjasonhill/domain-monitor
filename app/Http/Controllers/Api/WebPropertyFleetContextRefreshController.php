<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RefreshFleetPropertyContextRequest;
use App\Services\FleetPropertyContextRefreshService;
use Illuminate\Http\JsonResponse;

class WebPropertyFleetContextRefreshController extends Controller
{
    public function __invoke(
        RefreshFleetPropertyContextRequest $request,
        string $slug,
        FleetPropertyContextRefreshService $service
    ): JsonResponse {
        try {
            $summary = $service->refresh(
                $slug,
                (bool) $request->boolean('force_search_console_api_enrichment'),
                $request->integer('search_console_stale_days') ?: null,
            );
        } catch (\RuntimeException $exception) {
            return response()->json([
                'success' => false,
                'error' => 'Web property not found.',
            ], 404);
        }

        return response()->json($summary, $summary['success'] ? 200 : 207);
    }
}
