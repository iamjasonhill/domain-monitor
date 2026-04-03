<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DetectedIssueSummaryService;
use App\Services\DetectedIssueVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class DetectedIssueVerificationController extends Controller
{
    public function store(
        string $issueId,
        Request $request,
        DetectedIssueSummaryService $issueSummaryService,
        DetectedIssueVerificationService $verificationService,
    ): JsonResponse {
        $issue = $issueSummaryService->find($issueId);

        if ($issue === null) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $validated = $request->validate([
            'status' => 'required|string|in:verified_fixed_pending_recrawl',
            'hidden_until' => 'nullable|date|after:now',
            'verification_notes' => 'nullable|array',
            'verification_notes.*' => 'string|max:500',
        ]);

        $hiddenUntil = isset($validated['hidden_until'])
            ? Carbon::parse((string) $validated['hidden_until'])
            : null;
        $maxHiddenUntil = now()->addDays(14);

        if ($hiddenUntil !== null && $hiddenUntil->gt($maxHiddenUntil)) {
            throw ValidationException::withMessages([
                'hidden_until' => ['The hidden_until value may not be more than 14 days in the future.'],
            ]);
        }

        if ($hiddenUntil === null && $validated['status'] === 'verified_fixed_pending_recrawl') {
            $hiddenUntil = $maxHiddenUntil;
        }

        $verification = $verificationService->record($issueId, [
            'status' => $validated['status'],
            'hidden_until' => $hiddenUntil?->toIso8601String(),
            'verification_source' => $this->verificationSource($request),
            'verification_notes' => $validated['verification_notes'] ?? [],
        ], $issue);

        return response()->json([
            'success' => true,
            'issue_id' => $issueId,
            'status' => $verification->status,
            'hidden_until' => $verification->hidden_until?->toIso8601String(),
            'verification_source' => $verification->verification_source,
            'verification_notes' => $verification->verification_notes ?? [],
            'verified_at' => $verification->verified_at->toIso8601String(),
        ], 201);
    }

    private function verificationSource(Request $request): string
    {
        return match ($request->attributes->get('authenticated_api_client')) {
            'fleet_control' => 'fleet-control',
            default => 'external-api',
        };
    }
}
