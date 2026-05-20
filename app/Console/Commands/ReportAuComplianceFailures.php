<?php

namespace App\Console\Commands;

use App\Models\SynergyCredential;
use App\Services\AuComplianceFailureReport;
use App\Services\SynergyWholesaleClient;
use Illuminate\Console\Command;
use Throwable;

class ReportAuComplianceFailures extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'domains:report-au-compliance-failures
                            {--output-dir= : Directory for Markdown and CSV report artifacts}
                            {--dry-run : Build and print the report summary without writing files}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a read-only .au compliance failure report from current Synergy truth and local domain metadata';

    public function __construct(private readonly AuComplianceFailureReport $report)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $credential = SynergyCredential::query()->where('is_active', true)->first();

        if (! $credential) {
            $this->error('No active Synergy Wholesale credentials found.');

            return Command::FAILURE;
        }

        try {
            $client = SynergyWholesaleClient::fromEncryptedCredentials(
                $credential->reseller_id,
                $credential->api_key_encrypted,
                $credential->api_url
            );

            $synergyFailures = $client->listNonCompliantAuDomains();
        } catch (Throwable) {
            $this->error('Unable to retrieve non-compliant .au domains from Synergy Wholesale. Check credentials/API availability; credentials were not logged.');

            return Command::FAILURE;
        }

        if ($synergyFailures === null) {
            $this->error('Unable to retrieve non-compliant .au domains from Synergy Wholesale. Check credentials/API availability; credentials were not logged.');

            return Command::FAILURE;
        }

        $report = $this->report->build($synergyFailures);

        $this->info('Generated .au compliance failure report summary:');
        $this->line('Generated at: '.$report['generated_at']);
        $this->line('Total failing domains: '.$report['total_failing_domains']);
        $this->line('Matched local domains: '.$report['matched_local_domains']);
        $this->line('Unmatched Synergy domains: '.$report['unmatched_synergy_domains']);

        if ($this->option('dry-run')) {
            $this->warn('Dry run only: no report files were written and no Domain Monitor or Synergy records were changed.');

            return Command::SUCCESS;
        }

        $outputDirectory = (string) ($this->option('output-dir') ?: AuComplianceFailureReport::DEFAULT_OUTPUT_DIR);
        $paths = $this->report->write($report, $outputDirectory);

        $this->info('Markdown report: '.$paths['markdown']);
        $this->info('CSV report: '.$paths['csv']);
        $this->warn('Report only: no COR, DNS, renewal, registrant, Domain Monitor, or Synergy records were changed.');

        return Command::SUCCESS;
    }
}
