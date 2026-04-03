<?php

namespace App\Services;

use App\Models\DetectedIssueVerification;
use Illuminate\Support\Carbon;

class DetectedIssueVerificationService
{
    /**
     * @param  array<int, string>  $issueIds
     * @return array<string, DetectedIssueVerification>
     */
    public function latestMapForIssueIds(array $issueIds): array
    {
        $normalizedIssueIds = array_values(array_unique(array_filter(
            $issueIds,
            static fn (string $issueId): bool => $issueId !== ''
        )));

        if ($normalizedIssueIds === []) {
            return [];
        }

        $latest = [];

        DetectedIssueVerification::query()
            ->whereIn('issue_id', $normalizedIssueIds)
            ->orderByDesc('verified_at')
            ->orderByDesc('created_at')
            ->get()
            ->each(function (DetectedIssueVerification $verification) use (&$latest): void {
                $latest[$verification->issue_id] ??= $verification;
            });

        return $latest;
    }

    public function latestForIssueId(string $issueId): ?DetectedIssueVerification
    {
        if ($issueId === '') {
            return null;
        }

        return $this->latestMapForIssueIds([$issueId])[$issueId] ?? null;
    }

    /**
     * @param  array<string, mixed>  $issue
     * @return array<string, mixed>
     */
    public function annotateIssue(array $issue, ?DetectedIssueVerification $verification = null): array
    {
        $verification ??= $this->latestForIssueId((string) ($issue['issue_id'] ?? ''));
        $isSuppressed = $verification instanceof DetectedIssueVerification
            ? $this->isCurrentlySuppressed($issue, $verification)
            : false;

        $issue['status'] = $isSuppressed
            ? $verification->status
            : 'open';
        $issue['hidden_until'] = $isSuppressed && $verification->hidden_until instanceof Carbon
            ? $verification->hidden_until->toIso8601String()
            : null;
        $issue['verification'] = $verification instanceof DetectedIssueVerification
            ? [
                'status' => $verification->status,
                'hidden_until' => $verification->hidden_until?->toIso8601String(),
                'verification_source' => $verification->verification_source,
                'verification_notes' => $verification->verification_notes ?? [],
                'verified_at' => $verification->verified_at->toIso8601String(),
                'is_currently_suppressed' => $isSuppressed,
            ]
            : null;

        return $issue;
    }

    /**
     * @param  array<string, mixed>  $issue
     */
    public function isCurrentlySuppressed(array $issue, DetectedIssueVerification $verification): bool
    {
        if ($verification->status !== 'verified_fixed_pending_recrawl') {
            return false;
        }

        if (! $verification->hidden_until instanceof Carbon || ! $verification->hidden_until->isFuture()) {
            return false;
        }

        $observedAt = $this->latestObservedAt($issue);

        if ($observedAt instanceof Carbon && $observedAt->gt($verification->verified_at)) {
            return false;
        }

        return true;
    }

    /**
     * @param  array{status:string, hidden_until?:\Illuminate\Support\Carbon|string|null, verification_source?:string|null, verification_notes?:array<int, string>|null, verified_at?:\Illuminate\Support\Carbon|string|null}  $attributes
     * @param  array<string, mixed>  $issueContext
     */
    public function record(string $issueId, array $attributes, array $issueContext = []): DetectedIssueVerification
    {
        return DetectedIssueVerification::create([
            'issue_id' => $issueId,
            'property_slug' => is_string($issueContext['property_slug'] ?? null) ? $issueContext['property_slug'] : null,
            'domain' => is_string($issueContext['domain'] ?? null) ? $issueContext['domain'] : null,
            'issue_class' => is_string($issueContext['issue_class'] ?? null) ? $issueContext['issue_class'] : null,
            'status' => (string) $attributes['status'],
            'hidden_until' => $attributes['hidden_until'] ?? null,
            'verification_source' => is_string($attributes['verification_source'] ?? null) ? $attributes['verification_source'] : null,
            'verification_notes' => is_array($attributes['verification_notes'] ?? null) ? array_values(array_filter($attributes['verification_notes'], 'is_string')) : null,
            'verified_at' => $attributes['verified_at'] ?? now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $issue
     */
    private function latestObservedAt(array $issue): ?Carbon
    {
        $timestamps = [
            data_get($issue, 'evidence.captured_at'),
            data_get($issue, 'evidence.api_captured_at'),
            $issue['detected_at'] ?? null,
        ];

        return collect($timestamps)
            ->filter(fn (mixed $value): bool => is_string($value) && $value !== '')
            ->map(function (string $value): ?Carbon {
                try {
                    return Carbon::parse($value);
                } catch (\Throwable) {
                    return null;
                }
            })
            ->filter()
            ->sortByDesc(fn (Carbon $date): int => $date->getTimestamp())
            ->first();
    }
}
