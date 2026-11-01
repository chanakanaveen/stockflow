<?php

namespace Database\Seeders;

use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $password = config('demo.password');
        if (! app()->environment('local', 'testing') || ! config('demo.enabled') || ! is_string($password) || strlen($password) < 12) {
            throw new RuntimeException('Demo seeding requires local/testing, DEMO_ENABLED=true and a DEMO_PASSWORD of at least 12 characters.');
        }
        DB::transaction(function () use ($password): void {
            $this->call(RolePermissionSeeder::class);
            $stores = collect(['MAIN' => 'Main Warehouse', 'DOWNTOWN' => 'Downtown Branch', 'LAKESIDE' => 'Lakeside Branch'])
                ->map(fn (string $name, string $code) => Store::firstOrCreate(['code' => $code], ['name' => $name, 'is_active' => true]));
            foreach (['admin' => 'System Admin', 'manager' => 'Inventory Manager', 'keeper' => 'Store Keeper', 'auditor' => 'Auditor'] as $alias => $role) {
                $user = User::firstOrCreate(['email' => $alias.'@stockflow.test'], ['name' => 'Demo '.$role, 'password' => $password]);
                $user->syncRoles([$role]);
                $user->stores()->sync($alias === 'keeper' ? [$stores['MAIN']->id] : $stores->pluck('id')->all());
            }
        });
    }
}
