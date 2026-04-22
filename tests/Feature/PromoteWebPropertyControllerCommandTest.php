<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\PropertyRepository;
use App\Models\WebProperty;
use App\Models\WebPropertyDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class PromoteWebPropertyControllerCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_reports_the_planned_controller_promotion_without_writing_changes(): void
    {
        $domain = Domain::factory()->create([
            'domain' => 'wemove.com.au',
            'is_active' => true,
        ]);

        $property = WebProperty::factory()->create([
            'slug' => 'wemove-com-au',
            'name' => 'wemove.com.au',
            'property_type' => 'marketing_site',
            'status' => 'active',
            'platform' => 'WordPress',
            'target_platform' => 'Astro',
            'primary_domain_id' => $domain->id,
            'production_url' => 'https://wemove.com.au',
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $domain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        PropertyRepository::create([
            'web_property_id' => $property->id,
            'repo_name' => '_wp-house',
            'repo_provider' => 'local_only',
            'local_path' => '/Users/jasonhill/Projects/Business/websites/_wp-house',
            'framework' => 'WordPress',
            'is_primary' => true,
            'notes' => 'Mapped from shared WordPress house control surface.',
        ]);

        $exitCode = Artisan::call('web-properties:promote-controller', [
            'slug' => 'wemove-com-au',
            '--repo-name' => 'MM-wemove.com.au',
            '--repo-url' => 'https://github.com/iamjasonhill/MM-wemove.git',
            '--local-path' => '/Users/jasonhill/Projects/Business/websites/MM-wemove.com.au',
            '--framework' => 'Astro',
            '--platform' => 'Astro',
            '--target-platform' => 'Astro',
            '--deployment-provider' => 'vercel',
            '--deployment-project-name' => 'mm-wemove',
            '--dry-run' => true,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertDatabaseCount('property_repositories', 1);
        $this->assertDatabaseHas('property_repositories', [
            'web_property_id' => $property->id,
            'repo_name' => '_wp-house',
            'is_primary' => true,
            'is_controller' => false,
        ]);
        $this->assertDatabaseMissing('property_repositories', [
            'web_property_id' => $property->id,
            'repo_name' => 'MM-wemove.com.au',
        ]);
        $this->assertDatabaseHas('web_properties', [
            'id' => $property->id,
            'platform' => 'WordPress',
            'target_platform' => 'Astro',
        ]);

        $output = Artisan::output();
        $this->assertStringContainsString('Property: wemove.com.au (wemove-com-au)', $output);
        $this->assertStringContainsString('Promoted controller: MM-wemove.com.au -> /Users/jasonhill/Projects/Business/websites/MM-wemove.com.au', $output);
        $this->assertStringContainsString('Repositories to demote:', $output);
        $this->assertStringContainsString('Dry run complete. No changes were written.', $output);
    }

    public function test_it_promotes_an_astro_controller_and_demotes_older_repositories(): void
    {
        $domain = Domain::factory()->create([
            'domain' => 'discountbackloading.com.au',
            'is_active' => true,
        ]);

        $property = WebProperty::factory()->create([
            'slug' => 'discountbackloading-com-au',
            'name' => 'discountbackloading.com.au',
            'property_type' => 'marketing_site',
            'status' => 'active',
            'platform' => 'WordPress',
            'target_platform' => 'Astro',
            'primary_domain_id' => $domain->id,
            'production_url' => 'https://discountbackloading.com.au',
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $domain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        $legacyRepository = PropertyRepository::create([
            'web_property_id' => $property->id,
            'repo_name' => '_wp-house',
            'repo_provider' => 'local_only',
            'local_path' => '/Users/jasonhill/Projects/Business/websites/_wp-house',
            'framework' => 'WordPress',
            'is_primary' => true,
            'notes' => 'Mapped from shared WordPress house control surface.',
        ]);

        $exitCode = Artisan::call('web-properties:promote-controller', [
            'slug' => 'discountbackloading-com-au',
            '--repo-name' => 'MM-discountbackloading.com.au',
            '--repo-url' => 'https://github.com/iamjasonhill/MM-discountbackloading.git',
            '--local-path' => '/Users/jasonhill/Projects/Business/websites/MM-discountbackloading.com.au',
            '--framework' => 'Astro',
            '--default-branch' => 'main',
            '--deployment-branch' => 'main',
            '--deployment-provider' => 'vercel',
            '--deployment-project-name' => 'mm-discountbackloading',
            '--platform' => 'Astro',
            '--target-platform' => 'Astro',
            '--notes' => 'Canonical live Astro controller verified from the site cutover record.',
            '--demoted-note' => 'Retained as historical WordPress control surface after verified Astro cutover.',
            '--record-astro-cutover' => true,
            '--astro-cutover-at' => '2026-04-18T00:00:00+00:00',
        ]);

        $this->assertSame(0, $exitCode);

        $legacyRepository->refresh();
        $this->assertFalse($legacyRepository->is_primary);
        $this->assertFalse($legacyRepository->is_controller);
        $this->assertSame(
            'Retained as historical WordPress control surface after verified Astro cutover.',
            $legacyRepository->notes
        );

        $astroRepository = PropertyRepository::query()
            ->where('web_property_id', $property->id)
            ->where('repo_name', 'MM-discountbackloading.com.au')
            ->firstOrFail();

        $this->assertSame('github', $astroRepository->repo_provider);
        $this->assertSame('https://github.com/iamjasonhill/MM-discountbackloading.git', $astroRepository->repo_url);
        $this->assertSame('/Users/jasonhill/Projects/Business/websites/MM-discountbackloading.com.au', $astroRepository->local_path);
        $this->assertSame('Astro', $astroRepository->framework);
        $this->assertSame('main', $astroRepository->default_branch);
        $this->assertSame('main', $astroRepository->deployment_branch);
        $this->assertSame('vercel', $astroRepository->deployment_provider);
        $this->assertSame('mm-discountbackloading', $astroRepository->deployment_project_name);
        $this->assertTrue($astroRepository->is_primary);
        $this->assertTrue($astroRepository->is_controller);

        $property->refresh();
        $this->assertSame('Astro', $property->platform);
        $this->assertSame('Astro', $property->target_platform);
        $this->assertNotNull($property->astro_cutover_at);
        $this->assertSame('2026-04-18', $property->astro_cutover_at?->toDateString());

        $summary = $property->fresh()->brainSummary(includeFullExternalLinks: false);
        $this->assertSame('MM-discountbackloading.com.au', $summary['controller_repo']);
        $this->assertSame('/Users/jasonhill/Projects/Business/websites/MM-discountbackloading.com.au', $summary['controller_local_path']);
        $this->assertSame('astro_repo_controlled', $summary['execution_surface']);

        $output = Artisan::output();
        $this->assertStringContainsString('Controller promotion complete.', $output);
        $this->assertStringContainsString('Controller repo: MM-discountbackloading.com.au', $output);
        $this->assertStringContainsString('Execution surface: astro_repo_controlled', $output);
    }
}
