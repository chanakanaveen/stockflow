<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_seed_creates_roles_but_no_demo_accounts(): void
    {
        config(['demo.enabled' => false]);
        $this->seed(DatabaseSeeder::class);
        $this->assertDatabaseCount('roles', 4);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_opt_in_demo_is_repeatable_and_scopes_keeper(): void
    {
        config(['demo.enabled' => true, 'demo.password' => 'local-test-only-password']);
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);
        $this->assertDatabaseCount('users', 4);
        $this->assertDatabaseCount('stores', 3);
        $this->assertSame(1, User::where('email', 'keeper@stockflow.test')->firstOrFail()->stores()->count());
    }

    public function test_demo_seeder_rejects_production_even_when_enabled(): void
    {
        config(['demo.enabled' => true, 'demo.password' => 'local-test-only-password']);
        $this->app->instance('env', 'production');
        $this->expectException(RuntimeException::class);
        $this->app->make(DemoSeeder::class)->run();
    }
}
