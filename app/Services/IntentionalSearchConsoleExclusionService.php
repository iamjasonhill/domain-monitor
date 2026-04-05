<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\DomainCheck;
use App\Models\WebProperty;
use Illuminate\Support\Str;

class IntentionalSearchConsoleExclusionService
{
    /**
     * @param  array<string, mixed>  $issueEvidence
     * @return array<string, mixed>|null
     */
    public function classify(WebProperty $property, string $issueClass, array $issueEvidence): ?array
    {
        $candidateUrls = $this->candidateUrls($issueEvidence);

        if ($candidateUrls === []
            || ! $this->issueEvidenceRepresentsCompleteSet($issueEvidence, $candidateUrls)
            || ! $this->allUrlsAreIntentionalUtilityPaths($candidateUrls)) {
            return null;
        }

        $candidateDomain = $this->candidateDomainForUrls($property, $candidateUrls);

        if (! $candidateDomain instanceof Domain) {
            return null;
        }

        return match ($issueClass) {
            'blocked_by_robots_in_indexing' => $this->classifyBlockedByRobotsIssue($candidateDomain, $candidateUrls),
            'excluded_by_noindex' => $this->classifyNoindexIssue($candidateUrls),
            default => null,
        };
    }

    /**
     * @param  array<int, string>  $candidateUrls
     * @return array<string, mixed>|null
     */
    private function classifyBlockedByRobotsIssue(Domain $domain, array $candidateUrls): ?array
    {
        if (! $this->allUrlsAreWordPressAdminPaths($candidateUrls)) {
            return null;
        }

        $robotsInspection = $this->inspectStoredSeoRobotsState($domain);

        if (! is_array($robotsInspection) || ($robotsInspection['has_standard_wordpress_admin_rule'] ?? false) !== true) {
            return null;
        }

        return [
            'state' => 'expected_robots_exclusion',
            'code' => 'intentional_admin_exclusion',
            'reason' => 'standard_wordpress_admin_paths_blocked_in_robots',
            'matched_urls' => array_slice($candidateUrls, 0, 5),
            'matched_url_count' => count($candidateUrls),
            'robots' => [
                'url' => $robotsInspection['url'] ?? null,
                'disallow_wp_admin' => true,
                'allow_admin_ajax' => (bool) ($robotsInspection['allow_admin_ajax'] ?? false),
                'checked_at' => $robotsInspection['checked_at'] ?? null,
            ],
        ];
    }

    /**
     * @param  array<int, string>  $candidateUrls
     * @return array<string, mixed>
     */
    private function classifyNoindexIssue(array $candidateUrls): array
    {
        return [
            'state' => 'expected_noindex_exclusion',
            'code' => 'intentional_admin_exclusion',
            'reason' => 'admin_or_login_utility_paths_are_intentionally_noindexed',
            'matched_urls' => array_slice($candidateUrls, 0, 5),
            'matched_url_count' => count($candidateUrls),
        ];
    }

    /**
     * @param  array<string, mixed>  $issueEvidence
     * @return array<int, string>
     */
    private function candidateUrls(array $issueEvidence): array
    {
        $urls = [];

        foreach ((array) ($issueEvidence['affected_urls'] ?? []) as $url) {
            if (is_string($url) && $url !== '') {
                $urls[] = $url;
            }
        }

        foreach ((array) ($issueEvidence['sample_urls'] ?? []) as $url) {
            if (is_string($url) && $url !== '') {
                $urls[] = $url;
            }
        }

        foreach ((array) ($issueEvidence['examples'] ?? []) as $example) {
            if (is_array($example) && is_string($example['url'] ?? null) && $example['url'] !== '') {
                $urls[] = $example['url'];
            }
        }

        return array_values(array_unique($urls));
    }

    /**
     * @param  array<string, mixed>  $issueEvidence
     * @param  array<int, string>  $candidateUrls
     */
    private function issueEvidenceRepresentsCompleteSet(array $issueEvidence, array $candidateUrls): bool
    {
        if (($issueEvidence['is_example_set_truncated'] ?? false) === true) {
            return false;
        }

        $affectedUrlCount = is_numeric($issueEvidence['affected_url_count'] ?? null)
            ? (int) $issueEvidence['affected_url_count']
            : null;
        $exactExampleCount = is_numeric($issueEvidence['exact_example_count'] ?? null)
            ? (int) $issueEvidence['exact_example_count']
            : null;
        $candidateUrlCount = count($candidateUrls);

        if ($exactExampleCount !== null && $affectedUrlCount !== null) {
            return $candidateUrlCount >= $exactExampleCount
                && $candidateUrlCount >= $affectedUrlCount;
        }

        if ($exactExampleCount !== null) {
            return $candidateUrlCount >= $exactExampleCount;
        }

        if ($affectedUrlCount !== null) {
            return $candidateUrlCount >= $affectedUrlCount;
        }

        return false;
    }

    /**
     * @param  array<int, string>  $candidateUrls
     */
    private function allUrlsAreIntentionalUtilityPaths(array $candidateUrls): bool
    {
        foreach ($candidateUrls as $url) {
            if (! $this->isIntentionalUtilityPath($url)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int, string>  $candidateUrls
     */
    private function allUrlsAreWordPressAdminPaths(array $candidateUrls): bool
    {
        foreach ($candidateUrls as $url) {
            if (! $this->isWordPressAdminPath($url)) {
                return false;
            }
        }

        return true;
    }

    private function isIntentionalUtilityPath(string $url): bool
    {
        $path = Str::lower((string) parse_url($url, PHP_URL_PATH));

        if ($this->isWordPressAdminPath($url)) {
            return true;
        }

        if ($path === '/wp-login.php') {
            return true;
        }

        return false;
    }

    private function isWordPressAdminPath(string $url): bool
    {
        $path = Str::lower((string) parse_url($url, PHP_URL_PATH));

        return in_array($path, ['/wp-admin', '/wp-admin/'], true)
            || Str::startsWith($path, '/wp-admin/');
    }

    /**
     * @param  array<int, string>  $candidateUrls
     */
    private function candidateDomainForUrls(WebProperty $property, array $candidateUrls): ?Domain
    {
        $property->loadMissing(['primaryDomain.latestSeoCheck', 'propertyDomains.domain.latestSeoCheck']);

        $parsedHosts = collect($candidateUrls)
            ->map(function (string $url): ?string {
                $scheme = Str::lower((string) parse_url($url, PHP_URL_SCHEME));
                $host = Str::lower((string) parse_url($url, PHP_URL_HOST));

                if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
                    return null;
                }

                return $host;
            });

        if ($parsedHosts->contains(fn (?string $host): bool => ! is_string($host))) {
            return null;
        }

        $hosts = $parsedHosts
            ->unique()
            ->values();

        if ($hosts->count() !== 1) {
            return null;
        }

        $candidateHost = $hosts->first();

        if (! is_string($candidateHost)) {
            return null;
        }

        foreach ($this->knownPropertyDomains($property) as $domain) {
            if (Str::lower($domain->domain) === $candidateHost) {
                return $domain;
            }
        }

        return null;
    }

    /**
     * @return array<int, Domain>
     */
    private function knownPropertyDomains(WebProperty $property): array
    {
        $domains = [];

        if ($property->primaryDomain instanceof Domain) {
            $domains[$property->primaryDomain->id] = $property->primaryDomain;
        }

        foreach ($property->propertyDomains as $link) {
            if ($link->domain instanceof Domain) {
                $domains[$link->domain->id] = $link->domain;
            }
        }

        return array_values($domains);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function inspectStoredSeoRobotsState(Domain $domain): ?array
    {
        $seoCheck = $domain->relationLoaded('latestSeoCheck')
            ? $domain->latestSeoCheck
            : $domain->latestSeoCheck()->first();

        if (! $seoCheck instanceof DomainCheck) {
            return null;
        }

        $robots = data_get($seoCheck->payload, 'results.robots');

        if (! is_array($robots)) {
            return null;
        }

        return [
            'url' => is_string($robots['url'] ?? null) ? $robots['url'] : null,
            'has_standard_wordpress_admin_rule' => ($robots['has_standard_wordpress_admin_rule'] ?? false) === true,
            'allow_admin_ajax' => ($robots['allow_admin_ajax'] ?? false) === true,
            'checked_at' => $seoCheck->finished_at?->toIso8601String(),
        ];
    }
}
