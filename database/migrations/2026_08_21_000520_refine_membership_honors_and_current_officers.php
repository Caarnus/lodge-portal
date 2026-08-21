<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('memberships', function (Blueprint $table) {
            $table->boolean('is_award_of_gold')->default(false)->after('member_number');
        });

        Schema::create('past_master_terms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lodge_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->timestamps();
            $table->unique(['lodge_id', 'person_id', 'year']);
            $table->index(['lodge_id', 'year']);
        });

        Schema::dropIfExists('officer_assignments');
        Schema::create('officer_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lodge_id')->constrained()->cascadeOnDelete();
            $table->foreignId('membership_id');
            $table->foreignId('officer_position_id')->constrained()->restrictOnDelete();
            $table->boolean('is_public')->default(true);
            $table->boolean('show_email')->default(false);
            $table->boolean('show_phone')->default(false);
            $table->timestamps();
            $table->foreign(['membership_id', 'lodge_id'], 'officer_assignment_membership_lodge_foreign')
                ->references(['id', 'lodge_id'])->on('memberships')->cascadeOnDelete();
            $table->unique(['lodge_id', 'officer_position_id']);
            $table->unique(['lodge_id', 'membership_id', 'officer_position_id'], 'officer_assignment_member_position_unique');
            $table->index(['lodge_id', 'is_public']);
        });

        $peopleView = DB::table('permissions')->where('key', 'people.view')->value('id');
        $relationshipsView = DB::table('permissions')->where('key', 'relationships.view')->value('id');
        $memberRoleIds = DB::table('roles')->where('name', 'Member')->where('is_system', true)->pluck('id');
        foreach ($memberRoleIds as $roleId) {
            foreach (array_filter([$peopleView, $relationshipsView]) as $permissionId) {
                DB::table('permission_role')->insertOrIgnore(['permission_id' => $permissionId, 'role_id' => $roleId]);
            }
        }
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')->whereIn('key', ['people.view', 'relationships.view'])->pluck('id');
        $memberRoleIds = DB::table('roles')->where('name', 'Member')->where('is_system', true)->pluck('id');
        DB::table('permission_role')->whereIn('permission_id', $permissionIds)->whereIn('role_id', $memberRoleIds)->delete();

        Schema::dropIfExists('officer_assignments');
        Schema::create('officer_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lodge_id')->constrained()->cascadeOnDelete();
            $table->foreignId('membership_id');
            $table->foreignId('officer_position_id')->constrained()->restrictOnDelete();
            $table->date('term_starts_on');
            $table->date('term_ends_on');
            $table->string('term_label')->nullable();
            $table->boolean('is_public')->default(true);
            $table->boolean('show_email')->default(false);
            $table->boolean('show_phone')->default(false);
            $table->timestamps();
            $table->foreign(['membership_id', 'lodge_id'], 'officer_assignment_membership_lodge_foreign')
                ->references(['id', 'lodge_id'])->on('memberships')->cascadeOnDelete();
            $table->unique(['membership_id', 'officer_position_id', 'term_starts_on', 'term_ends_on'], 'officer_assignment_term_unique');
            $table->index(['lodge_id', 'is_public', 'term_starts_on', 'term_ends_on'], 'officer_assignment_public_term_index');
        });
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE officer_assignments ADD CONSTRAINT officer_assignment_valid_term CHECK (term_ends_on >= term_starts_on)');
        }

        Schema::dropIfExists('past_master_terms');
        Schema::table('memberships', fn (Blueprint $table) => $table->dropColumn('is_award_of_gold'));
    }
};
