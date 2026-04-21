<?php

namespace App\Services;

use App\Models\MonitoringFinding;
use App\Models\WebProperty;
use Brain\Client\BrainEventClient;

class MonitoringFindingManager
{
    public function __construct(
        private readonly DetectedIssueIdentityService $issueIdentityService,
    ) {}

    /**
     * @param  array<string, mixed>  $evidence
     */
    public function reportPropertyFinding(
        WebProperty $property,
        string $findingType,
        string $lane,
        string $issueType,
        string $title,
        string $summary,
        array $evidence,
        ?string $primaryDomainId = null
    ): string {
        $normalizedEvidence = $this->normalizeEvidence($evidence);
        $issueId = $this->issueIdentityService->makeIssueId(
            $primaryDomainId ?? '',
            $property->slug,
            $findingType
        );
        $now = now();

        $finding = MonitoringFinding::query()->firstWhere('issue_id', $issueId);
        $action = 'updated';

        if (! $finding instanceof MonitoringFinding) {
            MonitoringFinding::query()->create([
                'issue_id' => $issueId,
                'lane' => $lane,
                'finding_type' => $findingType,
                'issue_type' => $issueType,
                'scope_type' => 'web_property',
                'domain_id' => $primaryDomainId,
                'web_property_id' => $property->id,
                'status' => MonitoringFinding::STATUS_OPEN,
                'title' => $title,
                'summary' => $summary,
                'first_detected_at' => $now,
                'last_detected_at' => $now,
                'recovered_at' => null,
                'evidence' => $normalizedEvidence,
            ]);

            $this->emitBrainEvent('opened', $issueId, $property, $findingType, $lane, $issueType, $title, $summary, MonitoringFinding::STATUS_OPEN, $normalizedEvidence, $primaryDomainId);

            return 'opened';
        }

        if ($finding->status === MonitoringFinding::STATUS_RECOVERED) {
            $action = 'reopened';
        } elseif (
            $finding->summary === $summary
            && $this->normalizedJson(is_array($finding->evidence) ? $finding->evidence : []) === $this->normalizedJson($normalizedEvidence)
        ) {
            $action = 'observed';
        }

        $finding->forceFill([
            'lane' => $lane,
            'finding_type' => $findingType,
            'issue_type' => $issueType,
            'scope_type' => 'web_property',
            'domain_id' => $primaryDomainId,
            'web_property_id' => $property->id,
            'status' => MonitoringFinding::STATUS_OPEN,
            'title' => $title,
            'summary' => $summary,
            'last_detected_at' => $now,
            'recovered_at' => null,
            'evidence' => $normalizedEvidence,
        ])->save();

        if ($action !== 'observed') {
            $this->emitBrainEvent($action, $issueId, $property, $findingType, $lane, $issueType, $title, $summary, MonitoringFinding::STATUS_OPEN, $normalizedEvidence, $primaryDomainId);
        }

        return $action;
    }

    /**
     * @param  array<string, mixed>  $recoveryEvidence
     */
    public function recoverPropertyFinding(
        WebProperty $property,
        string $findingType,
        string $lane,
        string $recoverySummary,
        array $recoveryEvidence = [],
        ?string $primaryDomainId = null
    ): string {
        $issueId = $this->issueIdentityService->makeIssueId(
            $primaryDomainId ?? '',
            $property->slug,
            $findingType
        );
        $finding = MonitoringFinding::query()->firstWhere('issue_id', $issueId);

        if (! $finding instanceof MonitoringFinding || $finding->status === MonitoringFinding::STATUS_RECOVERED) {
            return 'noop';
        }

        $normalizedEvidence = $this->normalizeEvidence($recoveryEvidence);

        $finding->forceFill([
            'status' => MonitoringFinding::STATUS_RECOVERED,
            'summary' => $recoverySummary,
            'last_detected_at' => now(),
            'recovered_at' => now(),
            'evidence' => $normalizedEvidence,
        ])->save();

        $this->emitBrainEvent(
            'recovered',
            $issueId,
            $property,
            $findingType,
            $lane,
            $finding->issue_type,
            $finding->title,
            $recoverySummary,
            MonitoringFinding::STATUS_RECOVERED,
            $normalizedEvidence,
            $primaryDomainId
        );

        return 'recovered';
    }

    /**
     * @param  array<string, mixed>  $evidence
     */
    private function emitBrainEvent(
        string $action,
        string $issueId,
        WebProperty $property,
        string $findingType,
        string $lane,
        string $issueType,
        string $title,
        string $summary,
        string $findingStatus,
        array $evidence,
        ?string $primaryDomainId
    ): void {
        $baseUrl = config('services.brain.base_url');
        $apiKey = config('services.brain.api_key');

        if (! is_string($baseUrl) || $baseUrl === '' || ! is_string($apiKey) || $apiKey === '') {
            return;
        }

        /** @var BrainEventClient $brain */
        $brain = app(BrainEventClient::class);

        $payload = [
            'issue_id' => $issueId,
            'fingerprint' => 'domain_monitor.finding:'.$issueId,
            'message' => sprintf('%s: %s', $title, $summary),
            'action' => $action,
            'lane' => $lane,
            'finding_type' => $findingType,
            'issue_type' => $issueType,
            'finding_status' => $findingStatus,
            'title' => $title,
            'summary' => $summary,
            'occurred_at' => now()->toIso8601String(),
            'domain_id' => $primaryDomainId,
            'web_property' => [
                'id' => $property->id,
                'slug' => $property->slug,
                'name' => $property->name,
                'primary_domain' => $property->primaryDomainName(),
                'production_url' => $property->production_url,
            ],
            'evidence' => $evidence,
        ];

        $brain->sendAsync('domain_monitor.finding.'.$action, $payload);
    }

    /**
     * @param  array<string, mixed>  $evidence
     * @return array<string, mixed>
     */
    private function normalizeEvidence(array $evidence): array
    {
        ksort($evidence);

        foreach ($evidence as $key => $value) {
            if (is_array($value)) {
                $evidence[$key] = $this->normalizeNestedArray($value);
            }
        }

        return $evidence;
    }

    /**
     * @param  array<int|string, mixed>  $value
     * @return array<int|string, mixed>
     */
    private function normalizeNestedArray(array $value): array
    {
        if (array_is_list($value)) {
            return array_map(
                fn (mixed $item): mixed => is_array($item) ? $this->normalizeNestedArray($item) : $item,
                $value
            );
        }

        ksort($value);

        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->normalizeNestedArray($item);
            }
        }

        return $value;
    }

    /**
     * @param  array<int|string, mixed>  $value
     */
    private function normalizedJson(array $value): string
    {
        return json_encode($this->normalizeNestedArray($value), JSON_THROW_ON_ERROR);
    }
}
