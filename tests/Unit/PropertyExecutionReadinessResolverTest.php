<?php

namespace Tests\Unit;

use App\Models\PropertyRepository;
use App\Models\WebProperty;
use App\Services\PropertyExecutionReadinessResolver;
use PHPUnit\Framework\TestCase;

class PropertyExecutionReadinessResolverTest extends TestCase
{
    public function test_controller_repository_prefers_explicit_controller_repository(): void
    {
        $property = new WebProperty([
            'platform' => 'Astro',
        ]);
        $primaryRepository = new PropertyRepository([
            'repo_name' => 'marketing-site',
            'local_path' => '/Users/jasonhill/Projects/Business/websites/marketing-site',
            'framework' => 'Astro',
            'is_primary' => true,
            'is_controller' => false,
        ]);
        $controllerRepository = new PropertyRepository([
            'repo_name' => 'moveroo/controller-site',
            'local_path' => '/Users/jasonhill/Projects/Business/websites/controller-site',
            'framework' => 'Astro',
            'is_primary' => false,
            'is_controller' => true,
        ]);
        $property->setRelation('repositories', collect([$primaryRepository, $controllerRepository]));

        $resolver = new PropertyExecutionReadinessResolver;

        $selectedRepository = $resolver->controllerRepository($property);
        $selectedRepositorySummary = $resolver->controllerRepositorySummary($selectedRepository);

        $this->assertInstanceOf(PropertyRepository::class, $selectedRepository);
        $this->assertSame('moveroo/controller-site', $selectedRepository->repo_name);
        $this->assertSame(
            'moveroo/controller-site',
            $selectedRepositorySummary['controller_repo']
        );
    }

    public function test_execution_surface_uses_platform_profile_fallback_for_queue_resolution(): void
    {
        $property = new WebProperty([
            'platform' => null,
        ]);
        $repository = new PropertyRepository([
            'repo_name' => 'cartransport-site',
            'local_path' => '/Users/jasonhill/Projects/Business/websites/cartransport-site',
            'framework' => null,
            'is_primary' => true,
            'is_controller' => true,
        ]);
        $property->setRelation('repositories', collect([$repository]));

        $resolver = new PropertyExecutionReadinessResolver;

        $queueSummary = $resolver->executionReadinessForQueue($property, 'controlled', 'astro_marketing_managed');

        $this->assertSame('controlled', $queueSummary['control_state']);
        $this->assertSame('astro_repo_controlled', $queueSummary['execution_surface']);
        $this->assertTrue($queueSummary['fleet_managed']);
    }
}
