<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\DomainCheck;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

class DomainHealthCheckRunner
{
    public function __construct(
        private readonly SecurityHeadersHealthCheck $securityHeadersHealthCheck,
        private readonly SeoHealthCheck $seoHealthCheck,
        private readonly BrokenLinkHealthCheck $brokenLinkHealthCheck,
    ) {}

    /**
     * @return array{
     *   status: 'refreshed'|'skipped'|'failed',
     *   checked_at: string|null,
     *   reason: string|null
     * }
     */
    public function run(Domain $domain, string $type): array
    {
        if (! in_array($type, ['security_headers', 'seo', 'broken_links'], true)) {
            throw new InvalidArgumentException("Unsupported Fleet health check type [{$type}].");
        }

        $domain->loadMissing('platform');

        $skipReason = $domain->monitoringSkipReason($type);
        if ($skipReason !== null) {
            return [
                'status' => 'skipped',
                'checked_at' => $this->latestCheckTimestamp($domain, $type),
                'reason' => $skipReason,
            ];
        }

        try {
            $startedAt = now();

            if ($type === 'security_headers') {
                $result = $this->securityHeadersHealthCheck->check($domain->domain);
                $status = $this->determineWarnStatus($result);
            } elseif ($type === 'seo') {
                $result = $this->seoHealthCheck->check($domain->domain);
                $status = $this->determineWarnStatus($result);
            } else {
                $result = $this->brokenLinkHealthCheck->check($domain->domain);
                $status = $this->determineBrokenLinksStatus($result);
            }

            $payload = $result['payload'];
            $duration = isset($payload['duration_ms']) && is_numeric($payload['duration_ms'])
                ? (int) $payload['duration_ms']
                : 0;

            /** @var DomainCheck $check */
            $check = $domain->checks()->create([
                'check_type' => $type,
                'status' => $status,
                'response_code' => null,
                'started_at' => $startedAt,
                'finished_at' => now(),
                'duration_ms' => $duration,
                'error_message' => is_string($result['error_message'] ?? null) ? $result['error_message'] : null,
                'payload' => $payload,
                'retry_count' => 0,
            ]);

            $domain->forceFill(['last_checked_at' => now()])->save();

            return [
                'status' => 'refreshed',
                'checked_at' => $check->finished_at?->toIso8601String(),
                'reason' => null,
            ];
        } catch (Throwable $exception) {
            Log::warning('Fleet property health check refresh failed', [
                'domain_id' => $domain->id,
                'domain' => $domain->domain,
                'check_type' => $type,
                'exception' => $exception->getMessage(),
            ]);

            return [
                'status' => 'failed',
                'checked_at' => $this->latestCheckTimestamp($domain, $type),
                'reason' => 'health_check_refresh_failed',
            ];
        }
    }

    private function latestCheckTimestamp(Domain $domain, string $type): ?string
    {
        $latestCheck = $domain->checks()
            ->where('check_type', $type)
            ->latest('finished_at')
            ->first();

        return $latestCheck?->finished_at?->toIso8601String();
    }

    /**
     * @param  array{is_valid: bool, verified?: bool}  $result
     */
    private function determineWarnStatus(array $result): string
    {
        if (($result['verified'] ?? false) !== true) {
            return 'unknown';
        }

        return $result['is_valid'] ? 'ok' : 'warn';
    }

    /**
     * @param  array{is_valid: bool, verified?: bool}  $result
     */
    private function determineBrokenLinksStatus(array $result): string
    {
        if (($result['verified'] ?? false) !== true) {
            return 'unknown';
        }

        return $result['is_valid'] ? 'ok' : 'fail';
    }
}
