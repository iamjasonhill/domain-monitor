<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class IntegrationMetaController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'service' => 'domain-monitor',
            'generated_at' => now()->toIso8601String(),
            'auth' => [
                'scheme' => 'Bearer',
                'header' => 'Authorization',
                'accepted_tokens' => [
                    'BRAIN_API_KEY',
                    'OPS_API_KEY',
                    'FLEET_CONTROL_API_KEY',
                ],
            ],
            'feeds' => [
                [
                    'path' => '/api/web-properties-summary',
                    'source_system' => 'domain-monitor',
                    'contract_version' => 1,
                    'purpose' => 'authoritative web property summary',
                ],
                [
                    'path' => '/api/issues',
                    'source_system' => 'domain-monitor-issues',
                    'contract_version' => 1,
                    'purpose' => 'normalized detected issue feed',
                ],
                [
                    'path' => '/api/issues/{issue_id}',
                    'source_system' => 'domain-monitor-issues',
                    'contract_version' => 1,
                    'purpose' => 'detected issue detail',
                ],
                [
                    'path' => '/api/issues/{issue_id}/verification',
                    'source_system' => 'domain-monitor-issues',
                    'contract_version' => 1,
                    'purpose' => 'issue verification writeback for queue suppression and pending recrawl',
                    'method' => 'POST',
                    'accepted_tokens' => ['FLEET_CONTROL_API_KEY'],
                    'optional' => true,
                ],
                [
                    'path' => '/api/dashboard/priority-queue',
                    'source_system' => 'domain-monitor-priority-queue',
                    'contract_version' => 2,
                    'purpose' => 'enriched operational queue context',
                    'optional' => true,
                ],
            ],
        ]);
    }
}
