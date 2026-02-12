<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class HorizonAccessGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_horizon_gate_denies_non_allowlisted_user_in_non_local_environment(): void
    {
        config()->set('app.env', 'production');
        config()->set('horizon.allowed_emails', ['ops@example.com']);

        $user = User::factory()->create([
            'email' => 'dev@example.com',
        ]);

        $this->assertFalse(Gate::forUser($user)->allows('viewHorizon'));
    }

    public function test_horizon_gate_allows_allowlisted_user_in_non_local_environment(): void
    {
        config()->set('app.env', 'production');

        $user = User::factory()->create([
            'email' => 'ops@example.com',
        ]);

        config()->set('horizon.allowed_emails', ['OPS@EXAMPLE.COM']);

        $this->assertTrue(Gate::forUser($user)->allows('viewHorizon'));
    }

    public function test_horizon_gate_allows_authenticated_user_in_local_environment(): void
    {
        config()->set('app.env', 'local');
        config()->set('horizon.allowed_emails', []);

        $user = User::factory()->create();

        $this->assertTrue(Gate::forUser($user)->allows('viewHorizon'));
    }
}
