<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\DomainAlert;
use App\Models\DomainComplianceCheck;
use App\Models\SynergyCredential;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Mockery;
use Tests\TestCase;

class AuComplianceFailureReportCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        File::deleteDirectory($this->reportDirectory());

        parent::tearDown();
    }

    public function test_it_generates_markdown_and_csv_reports_for_current_synergy_failures(): void
    {
        $credential = SynergyCredential::factory()->create(['is_active' => true]);
        $domain = Domain::factory()->create([
            'domain' => 'example.com.au',
            'registrar' => 'Synergy Wholesale',
            'expires_at' => '2026-08-14',
            'auto_renew' => true,
            'registrant_name' => 'Old Example Pty Ltd',
            'registrant_id_type' => 'ABN',
            'registrant_id' => '12345678901',
            'eligibility_type' => 'Company',
            'eligibility_valid' => false,
            'eligibility_last_check' => '2026-05-19',
            'renewal_required' => true,
            'can_renew' => false,
        ]);

        DomainComplianceCheck::query()->create([
            'domain_id' => $domain->id,
            'is_compliant' => false,
            'compliance_reason' => 'Registrant ABN is no longer eligible',
            'source' => 'synergy',
            'checked_at' => now()->subHour(),
            'payload' => ['source' => 'test'],
        ]);

        DomainAlert::factory()->create([
            'domain_id' => $domain->id,
            'alert_type' => 'compliance_issue',
            'severity' => 'critical',
            'triggered_at' => now()->subMinutes(30),
            'resolved_at' => null,
        ]);

        $this->mockSynergyClient($credential, [
            ['domain' => 'example.com.au', 'reason' => 'Synergy says registrant eligibility failed'],
        ]);

        $this->artisan('domains:report-au-compliance-failures', [
            '--output-dir' => $this->reportDirectory(),
        ])->assertSuccessful();

        $markdown = $this->singleReportContents('md');
        $csv = $this->singleReportContents('csv');

        $this->assertStringContainsString('Total failing domains: 1', $markdown);
        $this->assertStringContainsString('Matched local domains: 1', $markdown);
        $this->assertStringContainsString('example.com.au', $markdown);
        $this->assertStringContainsString('Synergy says registrant eligibility failed', $markdown);
        $this->assertStringContainsString('Old Example Pty Ltd', $markdown);
        $this->assertStringContainsString('needs review', $markdown);
        $this->assertStringContainsString('open critical', $markdown);
        $this->assertStringContainsString('domain,synergy_failure_reason,local_record_status,manual_workflow_status', $csv);
        $this->assertStringContainsString('example.com.au', $csv);
    }

    public function test_it_includes_synergy_failures_missing_from_the_local_domain_table(): void
    {
        $credential = SynergyCredential::factory()->create(['is_active' => true]);

        $this->mockSynergyClient($credential, [
            ['domain' => 'missing.com.au', 'reason' => 'No matching registrant evidence'],
        ]);

        $this->artisan('domains:report-au-compliance-failures', [
            '--output-dir' => $this->reportDirectory(),
        ])->assertSuccessful();

        $markdown = $this->singleReportContents('md');

        $this->assertStringContainsString('Unmatched Synergy domains: 1', $markdown);
        $this->assertStringContainsString('missing.com.au', $markdown);
        $this->assertStringContainsString('not in local domain table', $markdown);
        $this->assertStringContainsString('needs old entity lookup', $markdown);
    }

    public function test_it_generates_empty_reports_when_synergy_has_no_failures(): void
    {
        $credential = SynergyCredential::factory()->create(['is_active' => true]);

        $this->mockSynergyClient($credential, []);

        $this->artisan('domains:report-au-compliance-failures', [
            '--output-dir' => $this->reportDirectory(),
        ])->assertSuccessful();

        $markdown = $this->singleReportContents('md');
        $csv = $this->singleReportContents('csv');

        $this->assertStringContainsString('Total failing domains: 0', $markdown);
        $this->assertStringContainsString('Matched local domains: 0', $markdown);
        $this->assertStringContainsString('Unmatched Synergy domains: 0', $markdown);
        $this->assertStringContainsString('domain,synergy_failure_reason,local_record_status,manual_workflow_status', $csv);
    }

    public function test_it_reports_synergy_api_failures_without_writing_report_files(): void
    {
        $credential = SynergyCredential::factory()->create(['is_active' => true]);

        $this->mockSynergyClient($credential, null);

        $this->artisan('domains:report-au-compliance-failures', [
            '--output-dir' => $this->reportDirectory(),
        ])->assertFailed()
            ->expectsOutputToContain('Unable to retrieve non-compliant .au domains from Synergy Wholesale.');

        $this->assertDirectoryDoesNotExist($this->reportDirectory());
    }

    public function test_dry_run_does_not_write_report_files(): void
    {
        $credential = SynergyCredential::factory()->create(['is_active' => true]);

        $this->mockSynergyClient($credential, [
            ['domain' => 'example.com.au', 'reason' => 'Synergy says registrant eligibility failed'],
        ]);

        $this->artisan('domains:report-au-compliance-failures', [
            '--output-dir' => $this->reportDirectory(),
            '--dry-run' => true,
        ])->assertSuccessful()
            ->expectsOutputToContain('Dry run only: no report files were written');

        $this->assertDirectoryDoesNotExist($this->reportDirectory());
    }

    /**
     * @param  array<int, array{domain: string, reason: string|null}>|null  $failures
     */
    private function mockSynergyClient(SynergyCredential $credential, ?array $failures): void
    {
        $synergyAlias = Mockery::mock('alias:App\Services\SynergyWholesaleClient');
        $synergyClient = Mockery::mock();

        $synergyAlias->shouldReceive('fromEncryptedCredentials')
            ->with($credential->reseller_id, $credential->api_key_encrypted, $credential->api_url)
            ->andReturn($synergyClient);

        $synergyClient->shouldReceive('listNonCompliantAuDomains')
            ->once()
            ->andReturn($failures);
    }

    private function reportDirectory(): string
    {
        return storage_path('framework/testing/au-compliance-report');
    }

    private function singleReportContents(string $extension): string
    {
        $files = File::glob($this->reportDirectory()."/*.{$extension}");

        $this->assertCount(1, $files);

        return (string) file_get_contents($files[0]);
    }
}
