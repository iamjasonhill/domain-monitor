<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SendDeploymentCompletedEventJob;
use App\Models\Deployment;
use App\Models\Domain;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

        // Queue event to Brain so API responses are never blocked by remote calls
        SendDeploymentCompletedEventJob::dispatch(
            $domain->domain,
            $deployment->id,
            $deployment->git_commit,
            $deployment->deployed_at->toIso8601String(),
        );

        return response()->json([
            'success' => true,
            'deployment_id' => $deployment->id,
            'domain' => $domain->domain,
            'deployed_at' => $deployment->deployed_at->toIso8601String(),
        ], 201);
    }
}
