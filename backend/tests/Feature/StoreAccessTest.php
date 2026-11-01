<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_keeper_sees_only_assigned_store_and_cannot_enumerate_another(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Store Keeper');
        $allowed = Store::factory()->create();
        $other = Store::factory()->create();
        $user->stores()->attach($allowed);
        $this->actingAs($user)->getJson('/api/v1/stores')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $allowed->id);
        $this->getJson('/api/v1/stores/'.$allowed->id)->assertOk();
        $this->getJson('/api/v1/stores/'.$other->id)->assertNotFound();
        $this->getJson('/api/v1/users')->assertForbidden();
        $this->assertFalse($user->can('transfers.approve'));
    }

    public function test_admin_can_read_all_stores_and_users_without_store_assignments(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('System Admin');
        $store = Store::factory()->create();
        $this->actingAs($admin)->getJson('/api/v1/stores/'.$store->id)->assertOk();
        $this->getJson('/api/v1/users')->assertOk()->assertJsonMissingPath('data.0.password');
    }

    public function test_assignment_alone_does_not_grant_view_permission(): void
    {
        $user = User::factory()->create();
        $store = Store::factory()->create();
        $user->stores()->attach($store);
        $this->actingAs($user)->getJson('/api/v1/stores')->assertForbidden();
        $this->getJson('/api/v1/stores/'.$store->id)->assertForbidden();
    }

    public function test_auditor_is_read_only_and_pagination_is_bounded(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Auditor');
        $this->assertTrue($user->can('audit.view'));
        $this->assertFalse($user->can('inventory.receive'));
        $this->assertFalse($user->can('users.manage'));
        $this->actingAs($user)->getJson('/api/v1/stores?per_page=101')->assertUnprocessable();
    }
}
