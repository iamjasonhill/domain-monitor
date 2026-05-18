<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RuntimeAnalyticsContextFeedBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RuntimeAnalyticsContextController extends Controller
{
    public function __invoke(Request $request, RuntimeAnalyticsContextFeedBuilder $contextFeedBuilder): JsonResponse
    {
        $validated = $request->validate([
            'hostname' => 'nullable|string|max:255',
            'runtime_path' => 'nullable|string|max:255',
            'site_key' => 'nullable|string|max:100',
        ]);

        $contexts = $contextFeedBuilder->build(
            hostname: $validated['hostname'] ?? null,
            runtimePath: $validated['runtime_path'] ?? null,
            siteKey: $validated['site_key'] ?? null
        );

        return response()->json([
            'source_system' => 'domain-monitor-runtime-analytics',
            'contract_version' => 1,
            'generated_at' => now()->toIso8601String(),
            'runtime_contexts' => $contexts->all(),
        ]);
    }
}
