<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('features', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('description');
        });

        Schema::table('feature_lodge', function (Blueprint $table) {
            $table->renameColumn('enabled', 'is_available');
            $table->boolean('is_enabled')->default(false)->after('is_available');
        });

        $permissionId = DB::table('permissions')->updateOrInsert(
            ['key' => 'lodge_modules.manage'],
            ['name' => 'Manage optional lodge modules', 'created_at' => now(), 'updated_at' => now()],
        );
        $permissionId = DB::table('permissions')->where('key', 'lodge_modules.manage')->value('id');

        DB::table('roles')
            ->where('name', 'Administrator')
            ->orderBy('id')
            ->pluck('id')
            ->each(fn (int $roleId) => DB::table('permission_role')->updateOrInsert([
                'permission_id' => $permissionId,
                'role_id' => $roleId,
            ]));
    }

    public function down(): void
    {
        Schema::table('feature_lodge', function (Blueprint $table) {
            $table->dropColumn('is_enabled');
            $table->renameColumn('is_available', 'enabled');
        });

        Schema::table('features', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
