<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'view dashboard',
            'view audit logs',
            'manage users',
            'assign roles',
            'delete users',
            'view articles',
            'create articles',
            'edit articles',
            'edit own articles',
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
            'manage contacts',
            'manage newsletter',
            'manage settings',
            'manage featured sections',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
    }
}
