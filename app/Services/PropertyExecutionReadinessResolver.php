<?php

namespace App\Services;

use App\Models\PropertyRepository;
use App\Models\WebProperty;

class PropertyExecutionReadinessResolver
{
    public function controllerRepository(?WebProperty $property): ?PropertyRepository
    {
        if (! $property instanceof WebProperty) {
            return null;
        }

        $repositories = $property->relationLoaded('repositories')
            ? $property->getRelation('repositories')
            : $property->repositories()->get();
        $orderedRepositories = $repositories
            ->sortByDesc(fn (PropertyRepository $repository) => $repository->is_controller)
            ->sortByDesc(fn (PropertyRepository $repository) => $repository->is_primary)
            ->values();

        $explicitController = $orderedRepositories->first(
            fn (PropertyRepository $repository) => $repository->is_controller
        );

        if ($explicitController instanceof PropertyRepository) {
            return $explicitController;
        }

        return $orderedRepositories->first(
            fn (PropertyRepository $repository) => $this->hasControllerPath($repository)
        ) ?? $orderedRepositories->first();
    }

    public function hasControllerPath(?PropertyRepository $repository): bool
    {
        return $repository instanceof PropertyRepository
            && is_string($repository->local_path)
            && trim($repository->local_path) !== '';
    }

    /**
     * @return array{
     *   controller_repo:string|null,
     *   controller_repo_url:string|null,
     *   controller_local_path:string|null,
     *   deployment_provider:string|null,
     *   deployment_project_name:string|null,
     *   deployment_project_id:string|null
     * }
     */
    public function controllerRepositorySummary(?PropertyRepository $repository): array
    {
        return [
            'controller_repo' => $repository?->repo_name,
            'controller_repo_url' => $repository?->repo_url,
            'controller_local_path' => $repository?->local_path,
            'deployment_provider' => $repository?->deployment_provider,
            'deployment_project_name' => $repository?->deployment_project_name,
            'deployment_project_id' => $repository?->deployment_project_id,
        ];
    }

    public function executionSurface(?PropertyRepository $repository, ?WebProperty $property = null, ?string $platformProfile = null): ?string
    {
        if (! $repository instanceof PropertyRepository) {
            return null;
        }

        if ($repository->repo_name === '_wp-house') {
            return 'fleet_wordpress_controlled';
        }

        $propertyPlatform = $property instanceof WebProperty ? $property->platform : null;
        $framework = strtolower((string) ($repository->framework ?? $propertyPlatform ?? ''));

        if (str_contains($framework, 'astro') || $platformProfile === 'astro_marketing_managed') {
            return 'astro_repo_controlled';
        }

        return 'repository_controlled';
    }

    /**
     * @return array{
     *   control_state:string,
     *   execution_surface:string|null,
     *   fleet_managed:bool,
     *   controller_repo:string|null,
     *   controller_repo_url:string|null,
     *   controller_local_path:string|null,
     *   deployment_provider:string|null,
     *   deployment_project_name:string|null,
     *   deployment_project_id:string|null
     * }
     */
    public function executionReadinessForProperty(WebProperty $property): array
    {
        $eligibility = $property->coverageEligibility();
        $controllerRepository = $this->controllerRepository($property);
        $controllerRepositorySummary = $this->controllerRepositorySummary($controllerRepository);

        if (! $eligibility['eligible']) {
            return [
                'control_state' => 'not_applicable',
                'execution_surface' => null,
                'fleet_managed' => false,
                ...$controllerRepositorySummary,
            ];
        }

        if (! $this->hasControllerPath($controllerRepository)) {
            return [
                'control_state' => 'uncontrolled',
                'execution_surface' => null,
                'fleet_managed' => false,
                ...$controllerRepositorySummary,
            ];
        }

        $executionSurface = $this->executionSurface($controllerRepository, $property);

        return [
            'control_state' => 'controlled',
            'execution_surface' => $executionSurface,
            'fleet_managed' => $property->isFleetManagedExecutionSurface($executionSurface),
            ...$controllerRepositorySummary,
        ];
    }

    /**
     * @return array{
     *   control_state:string,
     *   execution_surface:string|null,
     *   fleet_managed:bool,
     *   controller_repo:string|null,
     *   controller_repo_url:string|null,
     *   controller_local_path:string|null,
     *   deployment_provider:string|null,
     *   deployment_project_name:string|null,
     *   deployment_project_id:string|null
     * }
     */
    public function executionReadinessForQueue(?WebProperty $property, string $coverageStatus, string $platformProfile): array
    {
        $controllerRepository = $this->controllerRepository($property);
        $controllerRepositorySummary = $this->controllerRepositorySummary($controllerRepository);

        if ($coverageStatus === 'not_required') {
            return [
                'control_state' => 'not_applicable',
                'execution_surface' => null,
                'fleet_managed' => false,
                ...$controllerRepositorySummary,
            ];
        }

        if ($coverageStatus !== 'controlled') {
            return [
                'control_state' => 'uncontrolled',
                'execution_surface' => null,
                'fleet_managed' => false,
                ...$controllerRepositorySummary,
            ];
        }

        $executionSurface = $this->executionSurface($controllerRepository, $property, $platformProfile);

        return [
            'control_state' => 'controlled',
            'execution_surface' => $executionSurface,
            'fleet_managed' => $property?->isFleetManagedExecutionSurface($executionSurface) ?? false,
            ...$controllerRepositorySummary,
        ];
    }
}
