<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\PropertyRepository;
use App\Models\WebProperty;
use Illuminate\Support\Facades\Http;
use Throwable;

class ControllerMetadataDriftScanner
{
    /**
     * @return array{status:string, summary:string, evidence:array<string, mixed>}
     */
    public function audit(WebProperty $property, int $timeout = 10): array
    {
        $primaryDomain = $property->primaryDomainModel();

        if (! $primaryDomain instanceof Domain) {
            return $this->pass('Property does not have a primary domain for controller metadata drift checks.', [
                'verdict' => 'missing_primary_domain',
                'property_slug' => $property->slug,
            ]);
        }

        if (! $property->coverageEligibility()['eligible'] || $primaryDomain->isParked() || $primaryDomain->isEmailOnly()) {
            return $this->pass('Controller metadata drift checks are not required for this property.', [
                'verdict' => 'not_applicable',
                'property_slug' => $property->slug,
                'domain' => $primaryDomain->domain,
                'property_type' => $property->property_type,
                'property_status' => $property->status,
                'domain_platform' => $primaryDomain->platform,
                'parked' => $primaryDomain->isParked(),
                'email_only' => $primaryDomain->isEmailOnly(),
            ]);
        }

        $controller = $property->controllerRepository();
        $controllerEvidence = $this->controllerEvidence($controller, $property);

        if (! $this->storedControllerLooksWordPress($controller)) {
            return $this->pass('Stored controller metadata already points away from the WordPress control surface.', [
                'verdict' => 'controller_metadata_aligned_or_not_wordpress',
                'property_slug' => $property->slug,
                'domain' => $primaryDomain->domain,
                ...$controllerEvidence,
            ]);
        }

        try {
            $liveDetection = $this->detectLivePlatform($property, $primaryDomain, $timeout);
        } catch (Throwable $exception) {
            return $this->pass('Live platform probe could not verify controller metadata drift.', [
                'verdict' => 'live_probe_failed',
                'property_slug' => $property->slug,
                'domain' => $primaryDomain->domain,
                'error' => $exception->getMessage(),
                ...$controllerEvidence,
            ]);
        }

        $livePlatform = $this->normalizePlatform($liveDetection['platform_type'] ?? null);

        if ($livePlatform !== 'astro') {
            return $this->pass('Live platform evidence does not currently indicate an Astro cutover.', [
                'verdict' => 'no_astro_live_evidence',
                'property_slug' => $property->slug,
                'domain' => $primaryDomain->domain,
                'live_platform' => $liveDetection['platform_type'] ?? null,
                'detection_confidence' => $liveDetection['detection_confidence'] ?? null,
                'live_detection' => $liveDetection,
                ...$controllerEvidence,
            ]);
        }

        return [
            'status' => 'fail',
            'summary' => 'Live platform evidence indicates Astro, but stored controller metadata still points at the WordPress/_wp-house control surface. Promote the Astro controller metadata before routing policy work.',
            'evidence' => [
                'verdict' => 'controller_metadata_drift',
                'property_slug' => $property->slug,
                'domain' => $primaryDomain->domain,
                'live_platform' => $liveDetection['platform_type'] ?? null,
                'detection_confidence' => $liveDetection['detection_confidence'] ?? null,
                'live_detection' => $liveDetection,
                'domain_platform_before_probe' => $primaryDomain->platform,
                'domain_hosting_provider' => $primaryDomain->hosting_provider,
                'suggested_command' => sprintf(
                    'php artisan web-properties:promote-controller %s --repo-name=<astro-repo> --framework=Astro --platform=Astro --target-platform=Astro --record-astro-cutover',
                    $property->slug
                ),
                ...$controllerEvidence,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $evidence
     * @return array{status:string, summary:string, evidence:array<string, mixed>}
     */
    private function pass(string $summary, array $evidence): array
    {
        return [
            'status' => 'pass',
            'summary' => $summary,
            'evidence' => $evidence,
        ];
    }

    private function storedControllerLooksWordPress(?PropertyRepository $controller): bool
    {
        if (! $controller instanceof PropertyRepository) {
            return false;
        }

        if ($controller->repo_name === '_wp-house') {
            return true;
        }

        return str_contains($this->normalizePlatform($controller->framework), 'wordpress');
    }

    /**
     * @return array<string, mixed>
     */
    private function detectLivePlatform(WebProperty $property, Domain $primaryDomain, int $timeout): array
    {
        $url = $this->probeUrl($property, $primaryDomain);
        $response = Http::timeout($timeout)
            ->withoutVerifying()
            ->withHeaders([
                'User-Agent' => 'DomainMonitor/1.0',
            ])
            ->get($url);
        $html = $response->body();
        $headers = $response->headers();
        $server = $this->firstHeader($headers, 'server');
        $vercelId = $this->firstHeader($headers, 'x-vercel-id');
        $isAstro = $this->hasAstroEvidence($html);

        return [
            'platform_type' => $isAstro ? 'Astro' : null,
            'detection_confidence' => $isAstro ? 'high' : 'none',
            'detection_source' => 'live_http_homepage',
            'probe_url' => $url,
            'status_code' => $response->status(),
            'server' => $server,
            'vercel_id' => $vercelId,
            'vercel_evidence' => is_string($server) && str_contains(mb_strtolower($server), 'vercel')
                || is_string($vercelId) && trim($vercelId) !== '',
        ];
    }

    private function probeUrl(WebProperty $property, Domain $primaryDomain): string
    {
        if (is_string($property->production_url) && trim($property->production_url) !== '') {
            return trim($property->production_url);
        }

        return 'https://'.$primaryDomain->domain.'/';
    }

    private function hasAstroEvidence(string $html): bool
    {
        return preg_match('/<meta[^>]*name=["\']generator["\'][^>]*content=["\']Astro\s*v?([\d.]*)["\']/i', $html) === 1
            || str_contains($html, 'class="astro-')
            || str_contains($html, 'data-astro-')
            || str_contains($html, '/_astro/')
            || str_contains($html, '_astro/');
    }

    /**
     * @param  array<string, array<int, string>|string>  $headers
     */
    private function firstHeader(array $headers, string $name): ?string
    {
        $value = null;
        $target = mb_strtolower($name);

        foreach ($headers as $headerName => $headerValue) {
            if (mb_strtolower((string) $headerName) === $target) {
                $value = $headerValue;
                break;
            }
        }

        if (is_array($value)) {
            $value = $value[0] ?? null;
        }

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function controllerEvidence(?PropertyRepository $controller, WebProperty $property): array
    {
        $readiness = $property->executionReadinessSummary();

        return [
            'stored_controller_repo' => $controller?->repo_name,
            'stored_controller_framework' => $controller?->framework,
            'stored_controller_local_path' => $controller?->local_path,
            'stored_controller_repo_url' => $controller?->repo_url,
            'stored_execution_surface' => $readiness['execution_surface'] ?? null,
            'stored_control_state' => $readiness['control_state'],
        ];
    }

    private function normalizePlatform(mixed $platform): string
    {
        return mb_strtolower(trim((string) $platform));
    }
}
