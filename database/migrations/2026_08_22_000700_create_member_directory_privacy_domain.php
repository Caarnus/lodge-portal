<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('person_directory_privacy_settings', function (Blueprint $table) {
            $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            $table->string('scope')->default('own_lodge');
            $table->boolean('show_email')->default(false);
            $table->boolean('show_phone')->default(false);
            $table->boolean('show_address')->default(false);
            $table->boolean('show_profile_photo')->default(false);
            $table->boolean('show_degree')->default(false);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->primary('person_id');
        });

        Schema::create('membership_communication_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('membership_id');
            $table->foreignId('lodge_id');
            $table->boolean('receives_lodge_email')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique('membership_id');
            $table->unique(['membership_id', 'lodge_id']);
            $table->index('lodge_id');
            $table->foreign(['membership_id', 'lodge_id'], 'membership_communication_preference_membership_lodge_foreign')
                ->references(['id', 'lodge_id'])->on('memberships')->cascadeOnDelete();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE person_directory_privacy_settings ADD CONSTRAINT person_directory_privacy_settings_scope_check CHECK (scope IN ('hidden', 'own_lodge', 'participating_lodges'))");
        }

        $timestamp = now();
        DB::table('people')->whereNull('deleted_at')->orderBy('id')->chunkById(500, function ($people) use ($timestamp) {
            DB::table('person_directory_privacy_settings')->insertOrIgnore($people->map(fn ($person) => [
                'person_id' => $person->id,
                'scope' => 'own_lodge',
                'show_email' => false,
                'show_phone' => false,
                'show_address' => false,
                'show_profile_photo' => false,
                'show_degree' => false,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ])->all());
        });
        DB::table('memberships')->orderBy('id')->chunkById(500, function ($memberships) use ($timestamp) {
            DB::table('membership_communication_preferences')->insertOrIgnore($memberships->map(fn ($membership) => [
                'membership_id' => $membership->id,
                'lodge_id' => $membership->lodge_id,
                'receives_lodge_email' => true,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ])->all());
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_communication_preferences');
        Schema::dropIfExists('person_directory_privacy_settings');
    }
};
