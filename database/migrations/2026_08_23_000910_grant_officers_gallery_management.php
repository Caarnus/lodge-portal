<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $permissionId = DB::table('permissions')->where('key', 'galleries.manage')->value('id');

        if ($permissionId) {
            foreach (DB::table('roles')->where('name', 'Officer')->where('is_system', true)->pluck('id') as $roleId) {
                DB::table('permission_role')->insertOrIgnore(['permission_id' => $permissionId, 'role_id' => $roleId]);
            }
        }
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('key', 'galleries.manage')->value('id');

        if ($permissionId) {
            DB::table('permission_role')->where('permission_id', $permissionId)
                ->whereIn('role_id', DB::table('roles')->where('name', 'Officer')->where('is_system', true)->pluck('id'))
                ->delete();
        }
    }
};
