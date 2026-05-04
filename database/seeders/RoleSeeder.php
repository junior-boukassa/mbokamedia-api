<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $allPermissions = Permission::query()->pluck('name')->all();
        $adminPermissions = array_values(array_diff($allPermissions, config('admin.admin_restricted_permissions', [])));
        $editorPermissions = [
            'view dashboard',
            'view articles',
            'create articles',
            'edit articles',
            'delete articles',
            'view categories',
            'manage categories',
            'view tags',
            'manage tags',
            'view videos',
            'create videos',
            'edit videos',
            'delete videos',
            'view breaking news',
            'manage breaking news',
            'manage media',
            'manage featured sections',
        ];
        $journalistPermissions = [
            'view dashboard',
            'view articles',
            'create articles',
            'edit own articles',
            'view categories',
            'view tags',
            'manage media',
        ];

        $roles = [
            'super_admin' => $allPermissions,
            'admin' => $adminPermissions,
            'editor' => $editorPermissions,
            'journalist' => $journalistPermissions,
            'community_manager' => [
                'view dashboard',
                'view breaking news',
                'manage breaking news',
            'manage contacts',
            'manage advertising requests',
            'manage newsletter',
            'manage featured sections',
            'manage media',
            ],
        ];

        foreach ($roles as $roleName => $permissions) {
            $role = Role::findOrCreate($roleName, 'web');
            $role->syncPermissions($permissions);
        }
    }
}
