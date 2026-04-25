<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $this->normalizePermission('assign permissions', 'assign roles');
        $this->ensurePermission('delete users');

        $roleMap = [
            'Super Admin' => 'super_admin',
            'Admin' => 'admin',
            'Éditeur' => 'editor',
            'Editeur' => 'editor',
            'Journaliste / Rédacteur' => 'journalist',
            'Journaliste' => 'journalist',
            'Community Manager' => 'community_manager',
        ];

        foreach ($roleMap as $legacyName => $normalizedName) {
            $this->normalizeRole($legacyName, $normalizedName);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $this->normalizePermission('assign roles', 'assign permissions');
        $this->normalizeRole('super_admin', 'Super Admin');
        $this->normalizeRole('admin', 'Admin');
        $this->normalizeRole('editor', 'Éditeur');
        $this->normalizeRole('journalist', 'Journaliste');
        $this->normalizeRole('community_manager', 'Community Manager');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function ensurePermission(string $permissionName): void
    {
        $existingPermission = DB::table('permissions')
            ->where('name', $permissionName)
            ->where('guard_name', 'web')
            ->first();

        if ($existingPermission !== null) {
            return;
        }

        DB::table('permissions')->insert([
            'name' => $permissionName,
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function normalizePermission(string $legacyName, string $normalizedName): void
    {
        $legacyPermission = DB::table('permissions')
            ->where('name', $legacyName)
            ->where('guard_name', 'web')
            ->first();

        if ($legacyPermission === null) {
            return;
        }

        $normalizedPermission = DB::table('permissions')
            ->where('name', $normalizedName)
            ->where('guard_name', 'web')
            ->first();

        if ($normalizedPermission === null) {
            DB::table('permissions')
                ->where('id', $legacyPermission->id)
                ->update([
                    'name' => $normalizedName,
                    'updated_at' => now(),
                ]);

            return;
        }

        $rolePermissionLinks = DB::table('role_has_permissions')
            ->where('permission_id', $legacyPermission->id)
            ->get(['role_id']);

        foreach ($rolePermissionLinks as $link) {
            DB::table('role_has_permissions')->insertOrIgnore([
                'permission_id' => $normalizedPermission->id,
                'role_id' => $link->role_id,
            ]);
        }

        $modelPermissionLinks = DB::table('model_has_permissions')
            ->where('permission_id', $legacyPermission->id)
            ->get(['model_id', 'model_type']);

        foreach ($modelPermissionLinks as $link) {
            DB::table('model_has_permissions')->insertOrIgnore([
                'permission_id' => $normalizedPermission->id,
                'model_id' => $link->model_id,
                'model_type' => $link->model_type,
            ]);
        }

        DB::table('role_has_permissions')->where('permission_id', $legacyPermission->id)->delete();
        DB::table('model_has_permissions')->where('permission_id', $legacyPermission->id)->delete();
        DB::table('permissions')->where('id', $legacyPermission->id)->delete();
    }

    private function normalizeRole(string $legacyName, string $normalizedName): void
    {
        $legacyRole = DB::table('roles')
            ->where('name', $legacyName)
            ->where('guard_name', 'web')
            ->first();

        if ($legacyRole === null) {
            return;
        }

        $normalizedRole = DB::table('roles')
            ->where('name', $normalizedName)
            ->where('guard_name', 'web')
            ->first();

        if ($normalizedRole === null) {
            DB::table('roles')
                ->where('id', $legacyRole->id)
                ->update([
                    'name' => $normalizedName,
                    'updated_at' => now(),
                ]);

            return;
        }

        $modelRoleLinks = DB::table('model_has_roles')
            ->where('role_id', $legacyRole->id)
            ->get(['model_id', 'model_type']);

        foreach ($modelRoleLinks as $link) {
            DB::table('model_has_roles')->insertOrIgnore([
                'role_id' => $normalizedRole->id,
                'model_id' => $link->model_id,
                'model_type' => $link->model_type,
            ]);
        }

        $rolePermissionLinks = DB::table('role_has_permissions')
            ->where('role_id', $legacyRole->id)
            ->get(['permission_id']);

        foreach ($rolePermissionLinks as $link) {
            DB::table('role_has_permissions')->insertOrIgnore([
                'permission_id' => $link->permission_id,
                'role_id' => $normalizedRole->id,
            ]);
        }

        DB::table('model_has_roles')->where('role_id', $legacyRole->id)->delete();
        DB::table('role_has_permissions')->where('role_id', $legacyRole->id)->delete();
        DB::table('roles')->where('id', $legacyRole->id)->delete();
    }
};
