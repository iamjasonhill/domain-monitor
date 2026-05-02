<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LiveSeoVerificationPacketRequest;
use App\Models\WebProperty;
use App\Services\PropertySiteSignalScanner;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

class WebPropertyLiveSeoVerificationController extends Controller
{
    public function __invoke(
        string $slug,
        LiveSeoVerificationPacketRequest $request,
        PropertySiteSignalScanner $scanner,
    ): JsonResponse {
        $property = WebProperty::query()
            ->with([
                'primaryDomain',
                'propertyDomains.domain',
            ])
            ->where('slug', $slug)
            ->first();

        if (! $property instanceof WebProperty) {
            return response()->json([
                'error' => 'Web property not found',
            ], 404);
        }

        $validated = $request->validated();
        $sampleUrl = is_string($validated['sample_url'] ?? null) ? $validated['sample_url'] : null;
        $targetUrl = is_string($validated['target_url'] ?? null) ? $validated['target_url'] : null;
        $url = is_string($validated['url'] ?? null) ? $validated['url'] : ($targetUrl ?? $sampleUrl);
        $urlPattern = is_string($validated['url_pattern'] ?? null) ? $validated['url_pattern'] : null;
        $timeout = array_key_exists('timeout', $validated) ? (int) $validated['timeout'] : 10;
        $requestedChecks = $this->requestedChecks($validated['requested_checks'] ?? null);
        $packetContext = [
            'measurement_key' => $this->stringValue($validated['measurement_key'] ?? null),
            'evidence_ref' => $this->stringValue($validated['evidence_ref'] ?? null),
            'site_key' => $this->stringValue($validated['site_key'] ?? null),
            'expected_canonical' => $this->stringValue($validated['expected_canonical'] ?? null),
            'owning_repo' => $this->stringValue($validated['owning_repo'] ?? null),
            'reason' => $this->stringValue($validated['reason'] ?? null),
            'requested_checks' => $requestedChecks,
        ];

        if (! is_string($url) || trim($url) === '') {
            return response()->json([
                'error' => 'A verification URL or pattern sample URL is required.',
            ], 422);
        }

        try {
            $packet = $scanner->liveSeoVerificationPacket($property, $url, $urlPattern, $timeout, $packetContext);
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'error' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'source_system' => 'domain-monitor-live-seo-verification',
            'contract_version' => 1,
            'property_slug' => $property->slug,
            'property_name' => $property->name,
            ...$packet,
        ]);
    }

    private function stringValue(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /**
     * @return array<int, string>
     */
    private function requestedChecks(mixed $value): array
    {
        if (is_string($value)) {
            $value = explode(',', $value);
        }

        return collect(is_array($value) ? $value : [])
            ->filter(fn (mixed $item): bool => is_string($item) && trim($item) !== '')
            ->map(fn (string $item): string => trim($item))
            ->values()
            ->all();
    }
}
