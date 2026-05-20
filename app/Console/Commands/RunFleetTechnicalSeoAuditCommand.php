<?php

namespace App\Console\Commands;

use App\Models\WebProperty;
use App\Services\FleetTechnicalSeoAuditRunner;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class RunFleetTechnicalSeoAuditCommand extends Command
{
    protected $signature = 'monitoring:run-fleet-technical-seo-audit
                            {--property= : Web property slug to audit}
                            {--domain= : Domain selector to resolve to a web property}
                            {--url-cap=25 : Maximum URLs to include in the bounded deterministic crawl}
                            {--promote-findings : Promote qualifying failures/recoveries into MonitoringFinding records}';

    protected $description = 'Run the deterministic Fleet technical SEO audit slice for one web property.';

    public function handle(FleetTechnicalSeoAuditRunner $runner): int
    {
        $propertyOption = $this->optionString('property');
        $domainOption = $this->optionString('domain');

        if (($propertyOption === null && $domainOption === null) || ($propertyOption !== null && $domainOption !== null)) {
            $this->error('Provide exactly one selector: --property=<slug> or --domain=<domain>.');

            return self::FAILURE;
        }

        $properties = WebProperty::query()
            ->with(['primaryDomain', 'primaryDomain.tags', 'propertyDomains.domain', 'conversionSurfaces'])
            ->when(
                $propertyOption,
                fn (Builder $query) => $query->where('slug', $propertyOption)
            )
            ->when(
                $domainOption,
                fn (Builder $query) => $query->whereHas(
                    'propertyDomains.domain',
                    fn (Builder $domainQuery) => $domainQuery->where('domain', $domainOption)
                )->orWhereHas(
                    'primaryDomain',
                    fn (Builder $domainQuery) => $domainQuery->where('domain', $domainOption)
                )
            )
            ->get();

        if ($properties->count() !== 1) {
            $this->error(sprintf(
                'Expected exactly one web property for the selector; matched [%d].',
                $properties->count()
            ));

            return self::FAILURE;
        }

        $urlCap = max(1, (int) $this->option('url-cap'));
        $promoteFindings = (bool) $this->option('promote-findings');
        $property = $properties->firstOrFail();
        $run = $runner->run($property, $urlCap, 'operator_requested', $promoteFindings);
        $counts = $run->summary_counts ?? [];

        $this->info(sprintf(
            'Fleet technical SEO audit complete for [%s]. Run [%s].',
            $property->slug,
            $run->id
        ));
        $this->line(sprintf(
            'pass=%d fail=%d manual_review=%d unknown=%d not_applicable=%d skipped_due_to_limit=%d',
            (int) ($counts['pass'] ?? 0),
            (int) ($counts['fail'] ?? 0),
            (int) ($counts['manual_review'] ?? 0),
            (int) ($counts['unknown'] ?? 0),
            (int) ($counts['not_applicable'] ?? 0),
            (int) ($counts['not_checked_due_to_limit'] ?? 0)
        ));

        return self::SUCCESS;
    }

    private function optionString(string $name): ?string
    {
        $value = $this->option($name);

        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }
}
