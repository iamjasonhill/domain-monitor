<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\Subdomain;
use App\Services\DomainSubdomainService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DomainSubdomainServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_save_subdomain_creates_new_subdomain(): void
    {
        $domain = Domain::factory()->create([
            'domain' => 'example.com',
        ]);

        $result = app(DomainSubdomainService::class)->saveSubdomain(
            $domain,
            'api',
            'API endpoint'
        );

        $this->assertTrue($result['ok']);
        $this->assertDatabaseHas('subdomains', [
            'domain_id' => $domain->id,
            'subdomain' => 'api',
            'full_domain' => 'api.example.com',
            'notes' => 'API endpoint',
            'is_active' => 1,
        ]);
    }

    public function test_save_subdomain_returns_duplicate_error_for_existing_name(): void
    {
        $domain = Domain::factory()->create([
            'domain' => 'example.com',
        ]);

        Subdomain::create([
            'domain_id' => $domain->id,
            'subdomain' => 'api',
            'full_domain' => 'api.example.com',
            'is_active' => true,
        ]);

        $result = app(DomainSubdomainService::class)->saveSubdomain(
            $domain,
            'api'
        );

        $this->assertFalse($result['ok']);
        $this->assertSame('A subdomain with this name already exists.', $result['error']);
    }

    public function test_save_subdomain_updates_existing_record(): void
    {
        $domain = Domain::factory()->create([
            'domain' => 'example.com',
        ]);

        $subdomain = Subdomain::create([
            'domain_id' => $domain->id,
            'subdomain' => 'api',
            'full_domain' => 'api.example.com',
            'notes' => null,
            'is_active' => true,
        ]);

        $result = app(DomainSubdomainService::class)->saveSubdomain(
            $domain,
            'backend',
            'Renamed service',
            $subdomain->id
        );

        $this->assertTrue($result['ok']);
        $this->assertDatabaseHas('subdomains', [
            'id' => $subdomain->id,
            'subdomain' => 'backend',
            'full_domain' => 'backend.example.com',
            'notes' => 'Renamed service',
        ]);
    }

    public function test_delete_subdomain_removes_existing_subdomain(): void
    {
        $domain = Domain::factory()->create([
            'domain' => 'example.com',
        ]);

        $subdomain = Subdomain::create([
            'domain_id' => $domain->id,
            'subdomain' => 'api',
            'full_domain' => 'api.example.com',
            'is_active' => true,
        ]);

        $result = app(DomainSubdomainService::class)->deleteSubdomain($domain, $subdomain->id);

        $this->assertTrue($result['ok']);
        $this->assertSoftDeleted('subdomains', ['id' => $subdomain->id]);
    }
}
