<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BrandStyleSurfaceDraftBuilder
{
    /**
     * @return array{
     *   source_system: string,
     *   contract_version: int,
     *   generated_at: string,
     *   notes: array<int, string>,
     *   proposals: array<int, array<string, mixed>>
     * }
     */
    public function build(?string $hostname = null): array
    {
        $requestedHostname = $this->normalizeHostname($hostname);
        $proposals = $this->proposals();

        if ($requestedHostname !== null) {
            $proposals = array_values(array_filter(
                $proposals,
                fn (array $proposal): bool => ($proposal['hostname'] ?? null) === $requestedHostname
            ));
        }

        return [
            'source_system' => 'domain-monitor-brand-style-drafts',
            'contract_version' => 1,
            'generated_at' => now()->toIso8601String(),
            'notes' => [
                'Draft and review feed only. Proposed brand-style facts are not published to MoverooCombined until approval_status is approved.',
                'Approved proposals can annotate the published brand-surface payload without changing the existing MoverooCombined contract.',
            ],
            'proposals' => $proposals,
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function approvedMetadataByHostname(): array
    {
        return collect($this->proposals())
            ->filter(fn (array $proposal): bool => ($proposal['approval_status'] ?? null) === 'approved')
            ->mapWithKeys(fn (array $proposal): array => [
                (string) $proposal['hostname'] => $this->publishedMetadata($proposal),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function proposals(): array
    {
        $configuredProposals = config('domain_monitor.published_brand_surfaces.brand_style_proposals', []);
        $storedProposals = $this->storedProposals();

        if (! is_array($configuredProposals)) {
            $configuredProposals = [];
        }

        return collect($storedProposals)
            ->merge($configuredProposals)
            ->map(fn (mixed $proposal, mixed $hostname): ?array => $this->proposalRecord($hostname, $proposal))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function storedProposals(): array
    {
        return collect(Storage::disk('local')->files('brand-style-drafts'))
            ->filter(fn (string $path): bool => Str::endsWith($path, '.json'))
            ->mapWithKeys(function (string $path): array {
                $proposal = json_decode((string) Storage::disk('local')->get($path), true);

                if (! is_array($proposal)) {
                    return [];
                }

                $hostname = $this->normalizeHostname($proposal['hostname'] ?? null);

                return $hostname === null ? [] : [$hostname => $proposal];
            })
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function proposalRecord(mixed $hostname, mixed $proposal): ?array
    {
        if (! is_array($proposal)) {
            return null;
        }

        $normalizedHostname = $this->normalizeHostname($hostname);
        if ($normalizedHostname === null) {
            return null;
        }

        $metadataByHostname = config('domain_monitor.published_brand_surfaces.hostnames', []);
        $publishedMetadata = is_array($metadataByHostname) && is_array($metadataByHostname[$normalizedHostname] ?? null)
            ? $metadataByHostname[$normalizedHostname]
            : [];

        $sourceDomain = $this->normalizeHostname($proposal['source_marketing_domain'] ?? ($publishedMetadata['owning_marketing_domain'] ?? null));
        $approvalStatus = $this->normalizeStatus($proposal['approval_status'] ?? null);

        return [
            'hostname' => $normalizedHostname,
            'source_marketing_domain' => $sourceDomain,
            'property_slug' => $proposal['property_slug'] ?? ($publishedMetadata['property_slug'] ?? null),
            'surface_slug' => $proposal['surface_slug'] ?? ($publishedMetadata['surface_slug'] ?? null),
            'journey_type' => $proposal['journey_type'] ?? ($publishedMetadata['journey_type'] ?? null),
            'proposal_status' => $approvalStatus === 'approved' ? 'approved_for_publish' : 'draft_review_required',
            'approval_status' => $approvalStatus,
            'approved_by' => $approvalStatus === 'approved' ? ($proposal['approved_by'] ?? null) : null,
            'approved_at' => $approvalStatus === 'approved' ? ($proposal['approved_at'] ?? null) : null,
            'review_reason' => $proposal['review_reason'] ?? null,
            'candidate' => $this->candidate($proposal, $publishedMetadata),
            'evidence' => $this->evidence($proposal, $sourceDomain),
            'publish_gate' => [
                'can_publish' => $approvalStatus === 'approved',
                'reason' => $approvalStatus === 'approved'
                    ? 'approved_brand_style_surface'
                    : 'draft_requires_human_or_trusted_review',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $proposal
     * @param  array<string, mixed>  $publishedMetadata
     * @return array<string, mixed>
     */
    private function candidate(array $proposal, array $publishedMetadata): array
    {
        $candidate = is_array($proposal['candidate'] ?? null) ? $proposal['candidate'] : [];

        return [
            'brand' => $candidate['brand'] ?? ($proposal['brand'] ?? ($publishedMetadata['brand'] ?? [])),
            'theme' => $candidate['theme'] ?? ($proposal['theme'] ?? ($publishedMetadata['theme'] ?? [])),
            'copy' => $candidate['copy'] ?? ($proposal['copy'] ?? ($publishedMetadata['copy'] ?? [])),
            'contact' => $candidate['contact'] ?? ($proposal['contact'] ?? ($publishedMetadata['contact'] ?? [])),
            'links' => $candidate['links'] ?? ($proposal['links'] ?? ($publishedMetadata['links'] ?? [])),
        ];
    }

    /**
     * @param  array<string, mixed>  $proposal
     * @return array<int, array<string, mixed>>
     */
    private function evidence(array $proposal, ?string $sourceDomain): array
    {
        $evidence = $proposal['evidence'] ?? [];

        if (! is_array($evidence)) {
            return [];
        }

        return collect($evidence)
            ->map(function (mixed $item) use ($sourceDomain): ?array {
                if (! is_array($item)) {
                    return null;
                }

                return [
                    'field' => $item['field'] ?? 'unknown',
                    'value' => $item['value'] ?? null,
                    'source_url' => $item['source_url'] ?? ($sourceDomain === null ? null : 'https://'.$sourceDomain),
                    'source_type' => $item['source_type'] ?? 'reviewed_metadata',
                    'confidence' => $item['confidence'] ?? 'medium',
                    'captured_at' => $item['captured_at'] ?? null,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $proposal
     * @return array<string, mixed>
     */
    private function publishedMetadata(array $proposal): array
    {
        return [
            'source_marketing_domain' => $proposal['source_marketing_domain'] ?? null,
            'approval_status' => $proposal['approval_status'] ?? 'draft',
            'approved_by' => $proposal['approved_by'] ?? null,
            'approved_at' => $proposal['approved_at'] ?? null,
            'evidence_count' => count($proposal['evidence'] ?? []),
        ];
    }

    private function normalizeStatus(mixed $status): string
    {
        if (! is_string($status)) {
            return 'draft';
        }

        return in_array($status, ['approved', 'draft', 'needs_review', 'blocked'], true)
            ? $status
            : 'draft';
    }

    private function normalizeHostname(mixed $hostname): ?string
    {
        if (! is_string($hostname) || trim($hostname) === '') {
            return null;
        }

        return Str::of($hostname)
            ->lower()
            ->replaceStart('https://', '')
            ->replaceStart('http://', '')
            ->before('/')
            ->trim('.')
            ->toString();
    }
}
