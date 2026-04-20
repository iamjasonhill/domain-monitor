<?php

namespace App\Console\Commands;

use App\Models\DetectedIssueVerification;
use App\Models\Domain;
use App\Models\WebProperty;
use App\Services\DetectedIssueIdentityService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RenameWebPropertySlug extends Command
{
    protected $signature = 'web-properties:rename-slug
                            {from : Existing web property slug}
                            {to : Replacement web property slug}
                            {--dry-run : Only report the changes that would be made}';

    protected $description = 'Rename a web property slug and repair slug-derived verification identities';

    public function handle(DetectedIssueIdentityService $issueIdentityService): int
    {
        $from = trim((string) $this->argument('from'));
        $to = trim((string) $this->argument('to'));
        $dryRun = (bool) $this->option('dry-run');

        if ($from === '' || $to === '') {
            $this->error('Both the existing and replacement slugs are required.');

            return self::INVALID;
        }

        if ($from === $to) {
            $this->error('The replacement slug must be different from the existing slug.');

            return self::INVALID;
        }

        $property = WebProperty::query()
            ->with('propertyDomains.domain:id,domain')
            ->where('slug', $from)
            ->first();

        if (! $property instanceof WebProperty) {
            $this->error('Could not find the requested web property.');

            return self::FAILURE;
        }

        $existingTarget = WebProperty::query()
            ->where('slug', $to)
            ->first();

        if ($existingTarget instanceof WebProperty) {
            $this->error('The replacement slug is already in use.');

            return self::FAILURE;
        }

        $verifications = DetectedIssueVerification::query()
            ->where('property_slug', $from)
            ->orderBy('verified_at')
            ->get();

        $rewritableVerificationCount = 0;
        $unrewritableVerificationIds = [];

        foreach ($verifications as $verification) {
            $newIssueId = $this->replacementIssueId($verification, $property, $to, $issueIdentityService);

            if ($newIssueId === null) {
                $unrewritableVerificationIds[] = $verification->id;

                continue;
            }

            $rewritableVerificationCount++;
        }

        $this->line(sprintf('Property: %s (%s)', $property->name, $property->slug));
        $this->line(sprintf('Replacement slug: %s', $to));
        $this->line(sprintf('Detected issue verifications to retag: %d', $verifications->count()));
        $this->line(sprintf('Detected issue IDs that can be repaired: %d', $rewritableVerificationCount));

        if ($unrewritableVerificationIds !== []) {
            $this->warn(sprintf(
                'Detected issue verifications without enough context to rebuild issue IDs: %s',
                implode(', ', $unrewritableVerificationIds)
            ));
        }

        if ($dryRun) {
            $this->info('Dry run complete. No changes were written.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($property, $to, $verifications, $issueIdentityService): void {
            $property->forceFill([
                'slug' => $to,
            ])->save();

            foreach ($verifications as $verification) {
                $attributes = [
                    'property_slug' => $to,
                ];

                $replacementIssueId = $this->replacementIssueId($verification, $property, $to, $issueIdentityService);

                if ($replacementIssueId !== null) {
                    $attributes['issue_id'] = $replacementIssueId;
                }

                $verification->forceFill($attributes)->save();
            }
        });

        $this->info('Web property slug rename complete.');
        $this->line(sprintf('New slug: %s', $to));

        return self::SUCCESS;
    }

    private function replacementIssueId(
        DetectedIssueVerification $verification,
        WebProperty $property,
        string $replacementSlug,
        DetectedIssueIdentityService $issueIdentityService
    ): ?string {
        $issueClass = is_string($verification->issue_class) ? trim($verification->issue_class) : '';

        if ($issueClass === '') {
            return null;
        }

        $domainId = $this->domainIdForVerification($verification, $property);

        if ($domainId === null) {
            return null;
        }

        return $issueIdentityService->makeIssueId($domainId, $replacementSlug, $issueClass);
    }

    private function domainIdForVerification(DetectedIssueVerification $verification, WebProperty $property): ?string
    {
        $domainName = is_string($verification->domain) ? trim($verification->domain) : '';

        if ($domainName !== '') {
            $domainId = Domain::query()
                ->where('domain', $domainName)
                ->value('id');

            if (is_string($domainId) && $domainId !== '') {
                return $domainId;
            }
        }

        return is_string($property->primary_domain_id) && $property->primary_domain_id !== ''
            ? $property->primary_domain_id
            : null;
    }
}
