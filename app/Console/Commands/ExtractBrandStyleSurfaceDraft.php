<?php

namespace App\Console\Commands;

use App\Services\BrandStyle\MarketingSiteStyleExtractor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ExtractBrandStyleSurfaceDraft extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'brand-surfaces:extract-style-draft
        {hostname : App-served hostname to draft style metadata for}
        {--write : Write the draft JSON under storage/app/private/brand-style-drafts}
        {--path= : Optional storage path when --write is used}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Extract a bounded review-only brand style draft from a marketing site.';

    /**
     * Execute the console command.
     */
    public function handle(MarketingSiteStyleExtractor $extractor): int
    {
        $hostname = $this->normalizeHostname((string) $this->argument('hostname'));
        $metadata = $this->metadataForHostname($hostname);

        if ($metadata === []) {
            $this->error("No published brand-surface metadata found for {$hostname}.");

            return self::FAILURE;
        }

        $sourceMarketingDomain = $this->normalizeHostname((string) ($metadata['owning_marketing_domain'] ?? ''));

        if ($sourceMarketingDomain === '') {
            $this->error("No owning marketing domain configured for {$hostname}.");

            return self::FAILURE;
        }

        $draft = $extractor->extract(
            hostname: $hostname,
            sourceMarketingDomain: $sourceMarketingDomain,
            publishedMetadata: $metadata,
        );

        $encodedDraft = json_encode($draft, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $this->line($encodedDraft === false ? '{}' : $encodedDraft);

        if ($draft['proposal_status'] === 'extraction_failed') {
            return self::FAILURE;
        }

        if ((bool) $this->option('write')) {
            $path = $this->writeDraft($hostname, $draft);
            $this->info("Draft written to storage/app/private/{$path}");
        }

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    private function metadataForHostname(string $hostname): array
    {
        $hostnames = config('domain_monitor.published_brand_surfaces.hostnames', []);
        $metadata = is_array($hostnames) ? ($hostnames[$hostname] ?? []) : [];

        return is_array($metadata) ? $metadata : [];
    }

    /**
     * @param  array<string, mixed>  $draft
     */
    private function writeDraft(string $hostname, array $draft): string
    {
        $pathOption = $this->option('path');
        $path = is_string($pathOption) && trim($pathOption) !== ''
            ? trim($pathOption)
            : sprintf('brand-style-drafts/%s-%s.json', Str::slug($hostname), now()->utc()->format('Ymd-His'));

        Storage::disk('local')->put($path, json_encode($draft, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);

        return $path;
    }

    private function normalizeHostname(string $hostname): string
    {
        return Str::of($hostname)
            ->lower()
            ->replaceStart('https://', '')
            ->replaceStart('http://', '')
            ->before('/')
            ->trim('.')
            ->toString();
    }
}
