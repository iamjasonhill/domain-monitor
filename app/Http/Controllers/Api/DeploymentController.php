<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Deployment;
use App\Models\Domain;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DeploymentController extends Controller
{
    /**
     * Record a deployment and notify Brain for SEO snapshot.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'domain' => 'required|string',
            'commit' => 'nullable|string|max:40',
            'notes' => 'nullable|string|max:1000',
        ]);

        // Find domain by name
        $domain = Domain::where('domain', $validated['domain'])->first();

        if (! $domain) {
            return response()->json([
                'error' => 'Domain not found',
                'domain' => $validated['domain'],
            ], 404);
        }

        // Create deployment record
        $deployment = Deployment::create([
            'domain_id' => $domain->id,
            'git_commit' => $validated['commit'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'deployed_at' => now(),
        ]);

        // Fire event to Brain (async, don't block response)
        $this->notifyBrain($domain, $deployment);

        return response()->json([
            'success' => true,
            'deployment_id' => $deployment->id,
            'domain' => $domain->domain,
            'deployed_at' => $deployment->deployed_at->toIso8601String(),
        ], 201);
    }

    /**
     * Send deployment event to Brain.
     */
    private function notifyBrain(Domain $domain, Deployment $deployment): void
    {
        $brainUrl = config('services.brain.base_url');
        $brainKey = config('services.brain.api_key');

        if (! $brainUrl || ! $brainKey) {
            Log::warning('Brain not configured, skipping deployment notification');

            return;
        }

        try {
            Http::withHeaders([
                'X-Brain-Key' => $brainKey,
            ])->post("{$brainUrl}/api/events", [
                'type' => 'deployment.completed',
                'project' => 'domain-monitor',
                'data' => [
                    'domain' => $domain->domain,
                    'deployment_id' => $deployment->id,
                    'git_commit' => $deployment->git_commit,
                    'deployed_at' => $deployment->deployed_at->toIso8601String(),
                ],
            ]);

            Log::info("Deployment event sent to Brain for {$domain->domain}");
        } catch (\Exception $e) {
            Log::error('Failed to notify Brain of deployment: '.$e->getMessage());
        }
    }
}
