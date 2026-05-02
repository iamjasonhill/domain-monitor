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
                    'MOVEROO_REMOVALS_API_KEY',
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
                    'path' => '/api/runtime/analytics-contexts',
                    'source_system' => 'domain-monitor-runtime-analytics',
                    'contract_version' => 1,
                    'purpose' => 'lightweight hostname-to-analytics runtime resolution feed',
                ],
                [
                    'path' => '/api/issues',
                    'source_system' => 'domain-monitor-issues',
                    'contract_version' => 2,
                    'purpose' => 'canonical operator-facing detected issue feed; use fleet_focus=1 for Fleet must-fix totals',
                    'query_parameters' => [
                        'fleet_focus' => 'optional boolean filter for Fleet-focused properties only',
                    ],
                ],
                [
                    'path' => '/api/issues/{issue_id}',
                    'source_system' => 'domain-monitor-issues',
                    'contract_version' => 2,
                    'purpose' => 'detected issue detail',
                ],
                [
                    'path' => '/api/web-properties/{slug}/astro-cutover',
                    'source_system' => 'domain-monitor',
                    'contract_version' => 1,
                    'purpose' => 'record Astro go-live milestone and trigger an immediate SEO baseline checkpoint',
                    'method' => 'POST',
                    'accepted_tokens' => ['FLEET_CONTROL_API_KEY'],
                    'optional' => true,
                ],
                [
                    'path' => '/api/web-properties/{slug}/live-seo-verification',
                    'source_system' => 'domain-monitor-live-seo-verification',
                    'contract_version' => 1,
                    'purpose' => 'live SEO verification packet for one URL or one URL pattern sample on a selected property',
                    'query_parameters' => [
                        'measurement_key' => 'optional MM-Google or Search Intelligence measurement key; reused as verification_key when present',
                        'evidence_ref' => 'optional upstream evidence reference, report URL, artifact path, or note',
                        'site_key' => 'optional upstream site/property key',
                        'url' => 'exact live URL to verify',
                        'target_url' => 'alias for url when the caller uses packet-style input naming',
                        'url_pattern' => 'optional pattern label when the verification is representing a URL group',
                        'sample_url' => 'required sample URL when using url_pattern',
                        'expected_canonical' => 'optional absolute canonical URL expected on the live page',
                        'owning_repo' => 'optional expected site repository owner/name',
                        'reason' => 'optional plain-English verification reason',
                        'requested_checks' => 'optional comma-separated list or repeated array of requested checks',
                    ],
                    'verdicts' => [
                        'passes_live_verification',
                        'needs_attention',
                        'inconclusive',
                    ],
                    'optional' => true,
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
                    'purpose' => 'enriched priority-queue-only operational queue context; do not treat stats.must_fix as total detected must-fix truth',
                    'optional' => true,
                ],
            ],
        ]);
    }
}
