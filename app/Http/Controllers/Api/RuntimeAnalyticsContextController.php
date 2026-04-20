<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RuntimeAnalyticsContextResource;
use App\Models\WebPropertyConversionSurface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RuntimeAnalyticsContextController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'hostname' => 'nullable|string|max:255',
            'runtime_path' => 'nullable|string|max:255',
            'site_key' => 'nullable|string|max:100',
        ]);

        $query = WebPropertyConversionSurface::query()
            ->with([
                'webProperty.analyticsSources',
                'webProperty.eventContractAssignments.eventContract',
                'analyticsSource',
                'eventContractAssignment.eventContract',
            ])
            ->orderBy('hostname');

        if (isset($validated['hostname']) && trim((string) $validated['hostname']) !== '') {
            $query->where('hostname', strtolower(trim((string) $validated['hostname'], ". \t\n\r\0\x0B")));
        }

        if (isset($validated['runtime_path']) && trim((string) $validated['runtime_path']) !== '') {
            $query->where('runtime_path', trim((string) $validated['runtime_path']));
        }

        if (isset($validated['site_key']) && trim((string) $validated['site_key']) !== '') {
            $query->whereHas('webProperty', fn ($builder) => $builder->where('site_key', trim((string) $validated['site_key'])));
        }

        $contexts = $query->get();

        return response()->json([
            'source_system' => 'domain-monitor-runtime-analytics',
            'contract_version' => 1,
            'generated_at' => now()->toIso8601String(),
            'runtime_contexts' => RuntimeAnalyticsContextResource::collection($contexts)->resolve(),
        ]);
    }
}
