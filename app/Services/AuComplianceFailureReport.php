<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\DomainAlert;
use App\Models\DomainComplianceCheck;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AuComplianceFailureReport
{
    public const DEFAULT_OUTPUT_DIR = '/Users/jasonhill/Projects/Business/vault/domains/compliance/reports';

    public const STATUS_NEEDS_REVIEW = 'needs review';

    public const STATUS_NEEDS_OLD_ENTITY_LOOKUP = 'needs old entity lookup';

    /**
     * @var array<int, string>
     */
    public const WORKFLOW_STATUSES = [
        self::STATUS_NEEDS_REVIEW,
        self::STATUS_NEEDS_OLD_ENTITY_LOOKUP,
        'needs current eligible entity selected',
        'cor draft needed',
        'cor ready for synergy',
        'submitted in synergy',
        'resolved',
        'parked for later',
    ];

    /**
     * @var array<int, string>
     */
    private const HEADERS = [
        'domain',
        'synergy_failure_reason',
        'local_record_status',
        'manual_workflow_status',
        'local_registrant_name',
        'registrant_id_type',
        'registrant_id',
        'eligibility_type',
        'eligibility_valid',
        'eligibility_last_check',
        'expiry_date',
        'auto_renew',
        'renewal_required',
        'can_renew',
        'registrar',
        'latest_compliance_check_date',
        'latest_compliance_check_result',
        'latest_compliance_check_reason',
        'open_compliance_alert_state',
        'open_compliance_alert_triggered_at',
    ];

    /**
     * @param  array<int, array{domain: string, reason: string|null}>  $synergyFailures
     * @return array{generated_at: string, total_failing_domains: int, matched_local_domains: int, unmatched_synergy_domains: int, rows: array<int, array<string, string>>}
     */
    public function build(array $synergyFailures, ?Carbon $generatedAt = null): array
    {
        $generatedAt ??= now();
        $failuresByDomain = $this->normaliseSynergyFailures($synergyFailures);
        $domains = $this->localDomains(array_keys($failuresByDomain));

        $rows = [];
        foreach ($failuresByDomain as $domainName => $reason) {
            $domain = $domains->get($domainName);
            $rows[] = $this->row($domainName, $reason, $domain);
        }

        return [
            'generated_at' => $generatedAt->toIso8601String(),
            'total_failing_domains' => count($rows),
            'matched_local_domains' => collect($rows)->where('local_record_status', 'matched')->count(),
            'unmatched_synergy_domains' => collect($rows)->where('local_record_status', 'not in local domain table')->count(),
            'rows' => $rows,
        ];
    }

    /**
     * @param  array{generated_at: string, total_failing_domains: int, matched_local_domains: int, unmatched_synergy_domains: int, rows: array<int, array<string, string>>}  $report
     * @return array{markdown: string, csv: string}
     */
    public function render(array $report): array
    {
        return [
            'markdown' => $this->renderMarkdown($report),
            'csv' => $this->renderCsv($report['rows']),
        ];
    }

    /**
     * @param  array{generated_at: string, total_failing_domains: int, matched_local_domains: int, unmatched_synergy_domains: int, rows: array<int, array<string, string>>}  $report
     * @return array{markdown: string, csv: string}
     */
    public function write(array $report, string $outputDirectory): array
    {
        if (! is_dir($outputDirectory)) {
            mkdir($outputDirectory, 0755, true);
        }

        $safeTimestamp = Carbon::parse($report['generated_at'])->format('Ymd-His');
        $paths = [
            'markdown' => rtrim($outputDirectory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR."au-compliance-failures-{$safeTimestamp}.md",
            'csv' => rtrim($outputDirectory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR."au-compliance-failures-{$safeTimestamp}.csv",
        ];

        $rendered = $this->render($report);

        file_put_contents($paths['markdown'], $rendered['markdown']);
        file_put_contents($paths['csv'], $rendered['csv']);

        return $paths;
    }

    /**
     * @param  array<int, array{domain: string, reason: string|null}>  $synergyFailures
     * @return array<string, string>
     */
    private function normaliseSynergyFailures(array $synergyFailures): array
    {
        $failures = [];

        foreach ($synergyFailures as $failure) {
            $domain = strtolower(trim($failure['domain']));

            if ($domain === '') {
                continue;
            }

            $failures[$domain] = trim((string) ($failure['reason'] ?? '')) ?: 'No Synergy failure reason supplied';
        }

        ksort($failures);

        return $failures;
    }

    /**
     * @param  array<int, string>  $domainNames
     * @return Collection<string, Domain>
     */
    private function localDomains(array $domainNames): Collection
    {
        if ($domainNames === []) {
            return collect();
        }

        return Domain::query()
            ->with([
                'alerts' => fn ($query) => $query
                    ->where('alert_type', 'compliance_issue')
                    ->whereNull('resolved_at')
                    ->latest('triggered_at'),
                'complianceChecks',
            ])
            ->whereIn('domain', $domainNames)
            ->get()
            ->keyBy(fn (Domain $domain): string => strtolower($domain->domain));
    }

    /**
     * @return array<string, string>
     */
    private function row(string $domainName, string $reason, ?Domain $domain): array
    {
        $latestCheck = $domain?->complianceChecks->first();
        $openAlert = $domain?->alerts->first();

        return [
            'domain' => $domainName,
            'synergy_failure_reason' => $reason,
            'local_record_status' => $domain ? 'matched' : 'not in local domain table',
            'manual_workflow_status' => $domain ? self::STATUS_NEEDS_REVIEW : self::STATUS_NEEDS_OLD_ENTITY_LOOKUP,
            'local_registrant_name' => $this->nullableString($domain?->registrant_name),
            'registrant_id_type' => $this->nullableString($domain?->registrant_id_type),
            'registrant_id' => $this->nullableString($domain?->registrant_id),
            'eligibility_type' => $this->nullableString($domain?->eligibility_type),
            'eligibility_valid' => $this->nullableBool($domain?->eligibility_valid),
            'eligibility_last_check' => $domain?->eligibility_last_check?->toDateString() ?? '',
            'expiry_date' => $domain?->expires_at?->toDateString() ?? '',
            'auto_renew' => $this->nullableBool($domain?->auto_renew),
            'renewal_required' => $this->nullableBool($domain?->renewal_required),
            'can_renew' => $this->nullableBool($domain?->can_renew),
            'registrar' => $this->nullableString($domain?->registrar),
            'latest_compliance_check_date' => $latestCheck instanceof DomainComplianceCheck ? $latestCheck->checked_at->toIso8601String() : '',
            'latest_compliance_check_result' => $latestCheck instanceof DomainComplianceCheck ? ($latestCheck->is_compliant ? 'compliant' : 'non-compliant') : '',
            'latest_compliance_check_reason' => $latestCheck instanceof DomainComplianceCheck ? $this->nullableString($latestCheck->compliance_reason) : '',
            'open_compliance_alert_state' => $openAlert instanceof DomainAlert ? 'open '.$openAlert->severity : 'none',
            'open_compliance_alert_triggered_at' => $openAlert instanceof DomainAlert ? $openAlert->triggered_at->toIso8601String() : '',
        ];
    }

    private function nullableString(?string $value): string
    {
        return $value ?? '';
    }

    private function nullableBool(?bool $value): string
    {
        return match ($value) {
            true => 'yes',
            false => 'no',
            null => '',
        };
    }

    /**
     * @param  array{generated_at: string, total_failing_domains: int, matched_local_domains: int, unmatched_synergy_domains: int, rows: array<int, array<string, string>>}  $report
     */
    private function renderMarkdown(array $report): string
    {
        $lines = [
            '# .au Compliance Failure Report',
            '',
            '## Summary',
            '',
            '- Generated at: '.$report['generated_at'],
            '- Total failing domains: '.$report['total_failing_domains'],
            '- Matched local domains: '.$report['matched_local_domains'],
            '- Unmatched Synergy domains: '.$report['unmatched_synergy_domains'],
            '',
            '## Recommended Next Actions',
            '',
            '- Review each failing domain before preparing any COR or evidence pack.',
            '- Look up the old registrant ABN, ACN, or business-name basis where the report is incomplete.',
            '- Select a current eligible entity and prepare the COR evidence pack outside Domain Monitor.',
            '- Submit any COR manually in Synergy Wholesale; this report does not change Synergy, DNS, renewal, or registrant records.',
            '',
            '## Workflow Status Values',
            '',
            implode(', ', self::WORKFLOW_STATUSES),
            '',
            '## Failing Domains',
            '',
        ];

        $lines[] = '| '.implode(' | ', self::HEADERS).' |';
        $lines[] = '| '.implode(' | ', array_fill(0, count(self::HEADERS), '---')).' |';

        foreach ($report['rows'] as $row) {
            $lines[] = '| '.implode(' | ', array_map(fn (string $header): string => $this->markdownCell($row[$header] ?? ''), self::HEADERS)).' |';
        }

        return implode(PHP_EOL, $lines).PHP_EOL;
    }

    /**
     * @param  array<int, array<string, string>>  $rows
     */
    private function renderCsv(array $rows): string
    {
        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            return '';
        }

        fputcsv($handle, self::HEADERS);

        foreach ($rows as $row) {
            fputcsv($handle, array_map(fn (string $header): string => $row[$header] ?? '', self::HEADERS));
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv === false ? '' : $csv;
    }

    private function markdownCell(string $value): string
    {
        return str_replace(["\n", '|'], [' ', '\\|'], $value);
    }
}
