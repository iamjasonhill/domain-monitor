<?php

namespace App\Console\Commands;

use App\Models\PropertyRepository;
use App\Models\WebProperty;
use App\Services\WebPropertyAstroCutoverRecorder;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class PromoteWebPropertyController extends Command
{
    protected $signature = 'web-properties:promote-controller
                            {slug : Web property slug}
                            {--repo-name= : Canonical controller repository name}
                            {--repo-provider= : Repository provider (github, git, local_only)}
                            {--repo-url= : Canonical controller repository URL}
                            {--local-path= : Canonical controller local path}
                            {--default-branch= : Repository default branch}
                            {--deployment-branch= : Deployment branch}
                            {--framework= : Controller framework label}
                            {--deployment-provider= : Deployment provider}
                            {--deployment-project-name= : Deployment project name}
                            {--deployment-project-id= : Deployment project id}
                            {--platform= : Current platform value to store on the web property}
                            {--target-platform= : Target platform value to store on the web property}
                            {--notes= : Notes to store on the promoted controller row}
                            {--demoted-note= : Optional note to write to every demoted repository row}
                            {--record-astro-cutover : Record astro_cutover_at after promotion}
                            {--astro-cutover-at= : Explicit ISO-8601 timestamp to record as the Astro cutover}
                            {--captured-by= : captured_by value used for the cutover checkpoint}
                            {--refresh-seo-baseline : Refresh the SEO baseline when recording Astro cutover}
                            {--dry-run : Only report the changes that would be made}';

    protected $description = 'Promote a canonical controller repository for a web property and demote older controller surfaces';

    public function handle(WebPropertyAstroCutoverRecorder $astroCutoverRecorder): int
    {
        $slug = $this->argument('slug');
        $dryRun = (bool) $this->option('dry-run');

        $property = WebProperty::query()
            ->with('repositories')
            ->where('slug', $slug)
            ->first();

        if (! $property instanceof WebProperty) {
            $this->error('Could not find the requested web property.');

            return self::FAILURE;
        }

        $repoName = $this->requiredTrimmedOption('repo-name');
        $localPath = $this->requiredTrimmedOption('local-path');

        if ($repoName === null || $localPath === null) {
            return self::INVALID;
        }

        $platform = $this->trimmedOption('platform');
        $targetPlatform = $this->trimmedOption('target-platform');
        $framework = $this->resolvedFramework($platform, $targetPlatform);

        if ($framework === null) {
            $this->error('A controller framework is required. Pass --framework or provide platform metadata that resolves to one.');

            return self::INVALID;
        }

        $repoUrl = $this->trimmedOption('repo-url');
        $repoProvider = $this->resolvedRepoProvider($repoUrl);
        $defaultBranch = $this->trimmedOption('default-branch');
        $deploymentBranch = $this->trimmedOption('deployment-branch');
        $deploymentProvider = $this->trimmedOption('deployment-provider');
        $deploymentProjectName = $this->trimmedOption('deployment-project-name');
        $deploymentProjectId = $this->trimmedOption('deployment-project-id');
        $notes = $this->trimmedOption('notes');
        $demotedNote = $this->trimmedOption('demoted-note');
        $recordAstroCutover = (bool) $this->option('record-astro-cutover');
        $refreshSeoBaseline = (bool) $this->option('refresh-seo-baseline');
        $capturedBy = $this->trimmedOption('captured-by');
        $astroCutoverAt = $this->astroCutoverTimestamp();

        $targetRepository = $property->repositories->first(
            fn (PropertyRepository $repository): bool => $repository->repo_name === $repoName
                || $repository->local_path === $localPath
        );

        $demotedRepositories = $property->repositories
            ->reject(fn (PropertyRepository $repository): bool => $targetRepository instanceof PropertyRepository
                && $repository->id === $targetRepository->id)
            ->values();

        $this->line(sprintf('Property: %s (%s)', $property->name, $property->slug));
        $this->line(sprintf(
            'Current platform state: platform=%s, target_platform=%s',
            $property->platform ?? 'null',
            $property->target_platform ?? 'null'
        ));
        $this->line(sprintf('Current controller summary: %s', $property->brainSummary(includeFullExternalLinks: false)['controller_repo'] ?? 'none'));
        $this->line(sprintf('Promoted controller: %s -> %s', $repoName, $localPath));

        if ($demotedRepositories->isNotEmpty()) {
            $this->line('Repositories to demote:');

            foreach ($demotedRepositories as $repository) {
                $this->line(sprintf(
                    '  - %s (%s)',
                    $repository->repo_name,
                    $repository->local_path ?? 'no local path'
                ));
            }
        } else {
            $this->line('Repositories to demote: none');
        }

        if ($recordAstroCutover) {
            $this->line(sprintf(
                'Astro cutover checkpoint: %s%s',
                $astroCutoverAt?->toIso8601String() ?? 'now()',
                $refreshSeoBaseline ? ' with SEO baseline refresh' : ''
            ));
        }

        if ($dryRun) {
            $this->info('Dry run complete. No changes were written.');

            return self::SUCCESS;
        }

        $property->forceFill([
            'platform' => $platform ?? $property->platform,
            'target_platform' => $targetPlatform ?? $property->target_platform,
        ])->save();

        foreach ($demotedRepositories as $repository) {
            $attributes = [
                'is_primary' => false,
                'is_controller' => false,
            ];

            if ($demotedNote !== null) {
                $attributes['notes'] = $demotedNote;
            }

            $repository->forceFill($attributes)->save();
        }

        $targetAttributes = [
            'repo_name' => $repoName,
            'repo_provider' => $repoProvider,
            'repo_url' => $repoUrl,
            'local_path' => $localPath,
            'default_branch' => $defaultBranch,
            'deployment_branch' => $deploymentBranch,
            'framework' => $framework,
            'deployment_provider' => $deploymentProvider,
            'deployment_project_name' => $deploymentProjectName,
            'deployment_project_id' => $deploymentProjectId,
            'is_primary' => true,
            'is_controller' => true,
            'notes' => $notes,
        ];

        if ($targetRepository instanceof PropertyRepository) {
            $targetRepository->forceFill($targetAttributes)->save();
        } else {
            $targetRepository = PropertyRepository::query()->create([
                'web_property_id' => $property->id,
                ...$targetAttributes,
            ]);
        }

        if ($recordAstroCutover) {
            $astroCutoverRecorder->record(
                $property->slug,
                $astroCutoverAt,
                $refreshSeoBaseline,
                $capturedBy,
                $notes
            );
        }

        $property->refresh()->load('repositories');
        $summary = $property->fresh()?->brainSummary(includeFullExternalLinks: false) ?? [];

        $this->info('Controller promotion complete.');
        $this->line(sprintf('Controller repo: %s', $summary['controller_repo'] ?? 'unknown'));
        $this->line(sprintf('Controller local path: %s', $summary['controller_local_path'] ?? 'unknown'));
        $this->line(sprintf('Execution surface: %s', $summary['execution_surface'] ?? 'unknown'));

        return self::SUCCESS;
    }

    private function requiredTrimmedOption(string $name): ?string
    {
        $value = $this->trimmedOption($name);

        if ($value !== null) {
            return $value;
        }

        $this->error(sprintf('The --%s option is required.', $name));

        return null;
    }

    private function trimmedOption(string $name): ?string
    {
        $value = $this->option($name);

        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }

    private function resolvedFramework(?string $platform, ?string $targetPlatform): ?string
    {
        $framework = $this->trimmedOption('framework');

        if ($framework !== null) {
            return $framework;
        }

        return $targetPlatform ?? $platform;
    }

    private function resolvedRepoProvider(?string $repoUrl): string
    {
        $explicitProvider = $this->trimmedOption('repo-provider');

        if ($explicitProvider !== null) {
            return $explicitProvider;
        }

        if ($repoUrl === null) {
            return 'local_only';
        }

        $host = parse_url($repoUrl, PHP_URL_HOST);

        if (! is_string($host) || trim($host) === '') {
            return 'git';
        }

        return str_contains(strtolower($host), 'github.com') ? 'github' : 'git';
    }

    private function astroCutoverTimestamp(): ?Carbon
    {
        $value = $this->trimmedOption('astro-cutover-at');

        if ($value === null) {
            return null;
        }

        return Carbon::parse($value);
    }
}
