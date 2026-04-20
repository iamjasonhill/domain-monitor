<?php

namespace Tests\Feature;

use App\Models\DetectedIssueVerification;
use App\Models\Domain;
use App\Models\WebProperty;
use App\Services\DetectedIssueIdentityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RenameWebPropertySlugCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_previews_the_slug_rename_without_writing_changes(): void
    {
        $domain = Domain::factory()->create([
            'domain' => 'movinginsurance.com.au',
            'is_active' => true,
        ]);

        $property = WebProperty::factory()->create([
            'slug' => 'moving-insurance',
            'name' => 'Moving Insurance',
            'primary_domain_id' => $domain->id,
        ]);

        $oldIssueId = app(DetectedIssueIdentityService::class)->makeIssueId(
            $domain->id,
            $property->slug,
            'page_with_redirect_in_sitemap'
        );

        DetectedIssueVerification::create([
            'issue_id' => $oldIssueId,
            'property_slug' => $property->slug,
            'domain' => $domain->domain,
            'issue_class' => 'page_with_redirect_in_sitemap',
            'status' => 'verified_fixed_pending_recrawl',
            'verification_source' => 'test',
            'verified_at' => now(),
        ]);

        $this->artisan('web-properties:rename-slug', [
            'from' => 'moving-insurance',
            'to' => 'movinginsurance-com-au',
            '--dry-run' => true,
        ])
            ->expectsOutput('Property: Moving Insurance (moving-insurance)')
            ->expectsOutput('Replacement slug: movinginsurance-com-au')
            ->expectsOutput('Detected issue verifications to retag: 1')
            ->expectsOutput('Detected issue IDs that can be repaired: 1')
            ->expectsOutput('Dry run complete. No changes were written.')
            ->assertSuccessful();

        $this->assertDatabaseHas('web_properties', [
            'id' => $property->id,
            'slug' => 'moving-insurance',
        ]);

        $this->assertDatabaseHas('detected_issue_verifications', [
            'property_slug' => 'moving-insurance',
            'issue_id' => $oldIssueId,
        ]);
    }

    public function test_it_renames_the_slug_and_repairs_detected_issue_verification_ids(): void
    {
        $domain = Domain::factory()->create([
            'domain' => 'moveroo.com.au',
            'is_active' => true,
        ]);

        $property = WebProperty::factory()->create([
            'slug' => 'moveroo-website',
            'name' => 'Moveroo Website',
            'primary_domain_id' => $domain->id,
        ]);

        $oldIssueId = app(DetectedIssueIdentityService::class)->makeIssueId(
            $domain->id,
            $property->slug,
            'seo.broken_links'
        );

        DetectedIssueVerification::create([
            'issue_id' => $oldIssueId,
            'property_slug' => $property->slug,
            'domain' => $domain->domain,
            'issue_class' => 'seo.broken_links',
            'status' => 'verified_fixed_pending_recrawl',
            'verification_source' => 'test',
            'verification_notes' => ['updated during slug rename'],
            'verified_at' => now(),
        ]);

        $this->artisan('web-properties:rename-slug', [
            'from' => 'moveroo-website',
            'to' => 'moveroo-com-au',
        ])
            ->expectsOutput('Property: Moveroo Website (moveroo-website)')
            ->expectsOutput('Replacement slug: moveroo-com-au')
            ->expectsOutput('Detected issue verifications to retag: 1')
            ->expectsOutput('Detected issue IDs that can be repaired: 1')
            ->expectsOutput('Web property slug rename complete.')
            ->expectsOutput('New slug: moveroo-com-au')
            ->assertSuccessful();

        $newIssueId = app(DetectedIssueIdentityService::class)->makeIssueId(
            $domain->id,
            'moveroo-com-au',
            'seo.broken_links'
        );

        $this->assertDatabaseMissing('web_properties', [
            'id' => $property->id,
            'slug' => 'moveroo-website',
        ]);

        $this->assertDatabaseHas('web_properties', [
            'id' => $property->id,
            'slug' => 'moveroo-com-au',
        ]);

        $this->assertDatabaseMissing('detected_issue_verifications', [
            'issue_id' => $oldIssueId,
            'property_slug' => 'moveroo-website',
        ]);

        $this->assertDatabaseHas('detected_issue_verifications', [
            'issue_id' => $newIssueId,
            'property_slug' => 'moveroo-com-au',
            'domain' => 'moveroo.com.au',
            'issue_class' => 'seo.broken_links',
        ]);
    }
}
