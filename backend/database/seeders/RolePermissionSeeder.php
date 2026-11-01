<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $read = ['products.view', 'categories.view', 'stores.view', 'inventory.view', 'transfers.view', 'reports.view'];
        $operate = ['inventory.receive', 'inventory.issue', 'transfers.create', 'transfers.update', 'transfers.submit', 'transfers.cancel'];
        $approve = ['transfers.approve', 'transfers.reject', 'reports.export'];
        $admin = ['products.create', 'products.update', 'categories.create', 'categories.update', 'stores.create', 'stores.update', 'users.manage', 'audit.view'];
        $all = array_merge($read, $operate, $approve, $admin);
        foreach ($all as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach ([
            'System Admin' => $all,
            'Inventory Manager' => array_merge($read, $operate, $approve),
            'Store Keeper' => array_merge($read, $operate),
            'Auditor' => array_merge($read, ['reports.export', 'audit.view']),
        ] as $name => $permissions) {
            Role::findOrCreate($name, 'web')->syncPermissions($permissions);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
