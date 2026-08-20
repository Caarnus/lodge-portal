<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->unique(['id', 'lodge_id'], 'roles_id_lodge_unique');
        });

        Schema::table('lodge_user_roles', function (Blueprint $table) {
            $table->dropForeign(['role_id']);
            $table->foreign(['role_id', 'lodge_id'], 'role_assignment_lodge_foreign')
                ->references(['id', 'lodge_id'])
                ->on('roles')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('lodge_user_roles', function (Blueprint $table) {
            $table->dropForeign('role_assignment_lodge_foreign');
            $table->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->dropUnique('roles_id_lodge_unique');
        });
    }
};
