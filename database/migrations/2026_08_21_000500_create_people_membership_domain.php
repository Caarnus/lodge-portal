<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->string('legal_first_name')->nullable();
            $table->string('legal_middle_name')->nullable();
            $table->string('legal_last_name')->nullable();
            $table->string('legal_suffix', 32)->nullable();
            $table->string('preferred_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('mailing_address_line_1')->nullable();
            $table->string('mailing_address_line_2')->nullable();
            $table->string('mailing_city')->nullable();
            $table->string('mailing_state', 2)->nullable();
            $table->string('mailing_postal_code', 16)->nullable();
            $table->date('birth_date')->nullable();
            $table->boolean('is_deceased')->default(false);
            $table->date('death_date')->nullable();
            $table->string('profile_photo_path')->nullable();
            $table->string('profile_photo_derivative_path')->nullable();
            $table->foreignId('merged_into_person_id')->nullable()->constrained('people')->nullOnDelete();
            $table->timestamp('merged_at')->nullable();
            $table->softDeletes();
        });

        $this->createReferenceTable('membership_types');
        $this->createReferenceTable('membership_statuses');
        $this->createReferenceTable('masonic_degrees');

        Schema::create('relationship_types', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('inverse_key');
            $table->string('inverse_name');
            $table->boolean('is_symmetric')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('officer_positions', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lodge_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->constrained()->restrictOnDelete();
            $table->foreignId('membership_type_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('membership_status_id')->constrained()->restrictOnDelete();
            $table->foreignId('masonic_degree_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('primary_lodge_number')->nullable();
            $table->string('member_number')->nullable();
            $table->date('entered_apprentice_date')->nullable();
            $table->date('fellow_craft_date')->nullable();
            $table->date('master_mason_date')->nullable();
            $table->date('affiliation_date')->nullable();
            $table->date('demit_withdrawal_date')->nullable();
            $table->date('end_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['lodge_id', 'person_id']);
            $table->unique(['id', 'lodge_id']);
            $table->index(['lodge_id', 'membership_status_id']);
        });

        Schema::create('person_relationships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owning_lodge_id')->constrained('lodges')->restrictOnDelete();
            $table->foreignId('person_one_id')->constrained('people')->restrictOnDelete();
            $table->foreignId('person_two_id')->constrained('people')->restrictOnDelete();
            $table->foreignId('relationship_type_id')->constrained()->restrictOnDelete();
            $table->timestamps();
            $table->unique(['person_one_id', 'person_two_id', 'relationship_type_id'], 'person_relationship_direction_unique');
            $table->index(['person_two_id', 'person_one_id']);
        });

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

        foreach (['membership_types', 'membership_statuses', 'masonic_degrees'] as $table) {
            DB::statement("CREATE UNIQUE INDEX {$table}_one_default ON {$table} (is_default) WHERE is_default = true");
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE person_relationships ADD CONSTRAINT person_relationship_distinct_people CHECK (person_one_id <> person_two_id)');
            DB::statement('ALTER TABLE officer_assignments ADD CONSTRAINT officer_assignment_valid_term CHECK (term_ends_on >= term_starts_on)');
            DB::statement('ALTER TABLE people ADD CONSTRAINT people_death_date_requires_deceased CHECK (death_date IS NULL OR is_deceased)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('officer_assignments');
        Schema::dropIfExists('person_relationships');
        Schema::dropIfExists('memberships');
        Schema::dropIfExists('officer_positions');
        Schema::dropIfExists('relationship_types');
        Schema::dropIfExists('masonic_degrees');
        Schema::dropIfExists('membership_statuses');
        Schema::dropIfExists('membership_types');

        Schema::table('people', function (Blueprint $table) {
            $table->dropConstrainedForeignId('merged_into_person_id');
            $table->dropColumn([
                'legal_first_name', 'legal_middle_name', 'legal_last_name', 'legal_suffix', 'preferred_name',
                'phone', 'mailing_address_line_1', 'mailing_address_line_2', 'mailing_city', 'mailing_state',
                'mailing_postal_code', 'birth_date', 'is_deceased', 'death_date', 'profile_photo_path',
                'profile_photo_derivative_path', 'merged_at', 'deleted_at',
            ]);
        });
    }

    private function createReferenceTable(string $name): void
    {
        Schema::create($name, function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }
};
