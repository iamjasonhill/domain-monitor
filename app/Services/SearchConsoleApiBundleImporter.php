<?php

namespace App\Services;

use App\Models\WebProperty;
use InvalidArgumentException;
use RuntimeException;

class SearchConsoleApiBundleImporter
{
    private const MAX_JSON_BYTES = 5_242_880;

    public function __construct(
        private readonly SearchConsoleIssueSnapshotImporter $snapshotImporter,
    ) {}

    /**
     * @return array{
     *   artifact_path: string,
     *   imported_issue_classes: array<int, string>,
     *   snapshots: array<int, \App\Models\SearchConsoleIssueSnapshot>
     * }
     */
    public function importBundleForProperty(
        WebProperty $property,
        string $jsonPath,
        string $captureMethod = 'gsc_api',
        ?string $capturedBy = null
    ): array {
        if (! in_array($captureMethod, ['gsc_api', 'gsc_mcp_api'], true)) {
            throw new InvalidArgumentException('Capture method must be gsc_api or gsc_mcp_api.');
        }

        if (! is_file($jsonPath)) {
            throw new InvalidArgumentException(sprintf('Search Console API bundle not found at [%s].', $jsonPath));
        }

        if (filesize($jsonPath) > self::MAX_JSON_BYTES) {
            throw new InvalidArgumentException('Search Console API bundle exceeds the supported size limit.');
        }

        $decoded = json_decode((string) file_get_contents($jsonPath), true);

        if (! is_array($decoded)) {
            throw new RuntimeException('Search Console API bundle is not valid JSON.');
        }

        /** @var array<string, mixed> $sharedPayload */
        $sharedPayload = is_array($decoded['shared'] ?? null) ? $decoded['shared'] : [];
        /** @var array<string, mixed> $issueEvidence */
        $issueEvidence = is_array($decoded['issue_evidence'] ?? null) ? $decoded['issue_evidence'] : [];

        if ($issueEvidence === []) {
            throw new RuntimeException('Search Console API bundle does not contain any issue_evidence entries.');
        }

        $artifactPath = $this->snapshotImporter->storeArtifactForProperty($property, $jsonPath, 'search-console-api-evidence');
        $snapshots = [];

        foreach ($issueEvidence as $issueClass => $payload) {
            if (! is_array($payload)) {
                continue;
            }

            $mergedPayload = array_replace_recursive(
                $this->bundleDefaults($decoded, $sharedPayload),
                $payload
            );

            $result = $this->snapshotImporter->importApiEvidencePayloadForProperty(
                $property,
                $issueClass,
                $mergedPayload,
                $captureMethod,
                $capturedBy ?: 'bundle_api_issue_import',
                $artifactPath
            );

            $snapshots[] = $result['snapshot'];
        }

        if ($snapshots === []) {
            throw new RuntimeException('Search Console API bundle did not contain any importable issue evidence entries.');
        }

        return [
            'artifact_path' => $artifactPath,
            'imported_issue_classes' => array_map(
                static fn ($snapshot): string => $snapshot->issue_class,
                $snapshots
            ),
            'snapshots' => $snapshots,
        ];
    }

    /**
     * @param  array<string, mixed>  $bundle
     * @param  array<string, mixed>  $sharedPayload
     * @return array<string, mixed>
     */
    private function bundleDefaults(array $bundle, array $sharedPayload): array
    {
        $defaults = [];

        foreach (['source_report', 'source_property', 'property_scope', 'first_detected', 'last_update'] as $key) {
            if (array_key_exists($key, $bundle)) {
                $defaults[$key] = $bundle[$key];
            }
        }

        return array_replace_recursive($defaults, $sharedPayload);
    }
}
