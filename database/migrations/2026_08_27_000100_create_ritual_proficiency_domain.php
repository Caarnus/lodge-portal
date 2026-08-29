<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ritual_categories', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('masonic_degree_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('ritual_parts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ritual_category_id')->constrained()->restrictOnDelete();
            $table->string('key', 140)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('counts_toward_program')->default(false);
            $table->unsignedInteger('point_value')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index('ritual_category_id');
        });

        Schema::create('ritual_program_levels', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->string('name');
            $table->unsignedInteger('point_threshold');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('person_ritual_settings', function (Blueprint $table) {
            $table->foreignId('person_id')->primary()->constrained()->cascadeOnDelete();
            $table->string('visibility_scope', 24)->default('hidden');
            $table->string('public_availability_note', 500)->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('person_ritual_proficiencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ritual_part_id')->constrained()->restrictOnDelete();
            $table->string('status', 16)->default('not_known');
            $table->boolean('interested_in_learning')->default(false);
            $table->boolean('willing_to_assist')->default(false);
            $table->boolean('performed_for_credit')->default(false);
            $table->date('first_marked_proficient_on')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['person_id', 'ritual_part_id']);
        });

        Schema::create('person_ritual_availabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->string('daypart', 16);
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
            $table->unique(['person_id', 'day_of_week', 'daypart']);
        });

        Schema::create('person_ritual_level_achievements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ritual_program_level_id')->constrained()->restrictOnDelete();
            $table->timestampTz('achieved_at');
            $table->unsignedInteger('point_total_at_achievement');
            $table->string('level_name_snapshot');
            $table->unsignedInteger('threshold_snapshot');
            $table->timestamps();
            $table->unique(['person_id', 'ritual_program_level_id']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE UNIQUE INDEX ritual_parts_category_lower_name_unique ON ritual_parts (ritual_category_id, lower(name))');
            DB::statement("ALTER TABLE ritual_parts ADD CONSTRAINT ritual_parts_point_value_check CHECK ((counts_toward_program AND point_value IS NOT NULL AND point_value > 0) OR (NOT counts_toward_program AND point_value IS NULL))");
            DB::statement('ALTER TABLE ritual_program_levels ADD CONSTRAINT ritual_program_levels_threshold_check CHECK (point_threshold > 0)');
            DB::statement("ALTER TABLE person_ritual_settings ADD CONSTRAINT person_ritual_settings_visibility_scope_check CHECK (visibility_scope IN ('hidden', 'own_lodge', 'participating_lodges'))");
            DB::statement("ALTER TABLE person_ritual_proficiencies ADD CONSTRAINT person_ritual_proficiencies_status_check CHECK (status IN ('not_known', 'learning', 'proficient'))");
            DB::statement('ALTER TABLE person_ritual_proficiencies ADD CONSTRAINT person_ritual_proficiencies_willingness_check CHECK (NOT willing_to_assist OR status = \'proficient\')');
            DB::statement('ALTER TABLE person_ritual_availabilities ADD CONSTRAINT person_ritual_availabilities_day_of_week_check CHECK (day_of_week BETWEEN 1 AND 7)');
            DB::statement("ALTER TABLE person_ritual_availabilities ADD CONSTRAINT person_ritual_availabilities_daypart_check CHECK (daypart IN ('morning', 'afternoon', 'evening'))");
        }

        $now = now();
        DB::table('permissions')->insertOrIgnore([
            'key' => 'ritual.search',
            'name' => 'Search ritual assistance',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $permissionId = DB::table('permissions')->where('key', 'ritual.search')->value('id');
        foreach (DB::table('roles')->where('is_system', true)->whereIn('name', ['Administrator', 'Officer', 'Member'])->pluck('id') as $roleId) {
            DB::table('permission_role')->insertOrIgnore(['permission_id' => $permissionId, 'role_id' => $roleId]);
        }
    }

    public function down(): void
    {
        DB::table('permissions')->where('key', 'ritual.search')->delete();
        Schema::dropIfExists('person_ritual_level_achievements');
        Schema::dropIfExists('person_ritual_availabilities');
        Schema::dropIfExists('person_ritual_proficiencies');
        Schema::dropIfExists('person_ritual_settings');
        Schema::dropIfExists('ritual_program_levels');
        Schema::dropIfExists('ritual_parts');
        Schema::dropIfExists('ritual_categories');
    }
};
