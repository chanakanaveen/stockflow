<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_manages_fixed_roles_scope_and_passwords_without_audit_secrets(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('System Admin');
        $store = Store::factory()->create();
        $data = ['name' => 'New Keeper', 'email' => 'new@stockflow.test', 'password' => 'test-only-secret-password', 'role' => 'Store Keeper', 'is_active' => true, 'store_ids' => [$store->id]];
        $this->actingAs($admin)->getJson('/api/v1/roles')->assertOk()->assertJsonCount(4, 'data');
        $id = $this->postJson('/api/v1/users', $data)->assertCreated()->assertJsonPath('data.roles.0', 'Store Keeper')->assertDontSee($data['password'])->json('data.id');
        $this->assertTrue(Hash::check($data['password'], User::findOrFail($id)->password));
        $this->assertStringNotContainsString($data['password'], DB::table('audit_logs')->value('metadata'));
        $data['role'] = 'Auditor';
        $data['store_ids'] = [];
        unset($data['password']);
        $this->patchJson('/api/v1/users/'.$id, $data)->assertOk()->assertJsonCount(0, 'data.assigned_store_ids')->assertJsonPath('data.roles.0', 'Auditor');
        $this->patchJson('/api/v1/users/'.$id, [...$data, 'role' => 'Custom'])->assertUnprocessable();
        $this->postJson('/api/v1/users', [...$data, 'password' => 'short'])->assertUnprocessable();
        $this->actingAs(User::findOrFail($id))->getJson('/api/v1/roles')->assertForbidden();
        $this->postJson('/api/v1/users', $data)->assertForbidden();
    }

    public function test_last_active_admin_cannot_be_removed(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('System Admin');
        $data = ['name' => $admin->name, 'email' => $admin->email, 'role' => 'Auditor', 'is_active' => true, 'store_ids' => []];
        $this->actingAs($admin)->patchJson('/api/v1/users/'.$admin->id, $data)->assertConflict()->assertJsonPath('error.code', 'LAST_ADMIN');
        $this->patchJson('/api/v1/users/'.$admin->id, [...$data, 'role' => 'System Admin', 'is_active' => false])->assertConflict();
        $this->assertTrue($admin->fresh()->hasRole('System Admin'));
    }
}
