<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_volunteer_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id');
            $table->foreignId('lodge_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_occurrence_id')->nullable();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->unsignedInteger('needed_count');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->foreign(['event_id', 'lodge_id'], 'event_volunteer_position_event_lodge_foreign')->references(['id', 'lodge_id'])->on('events')->cascadeOnDelete();
            $table->foreign(['event_occurrence_id', 'event_id', 'lodge_id'], 'event_volunteer_position_occurrence_event_lodge_foreign')->references(['id', 'event_id', 'lodge_id'])->on('event_occurrences')->cascadeOnDelete();
            $table->unique(['id', 'event_id', 'lodge_id']);
            $table->index(['event_id', 'event_occurrence_id', 'is_active', 'sort_order']);
        });
        Schema::create('event_volunteer_commitments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_volunteer_position_id');
            $table->foreignId('event_occurrence_id');
            $table->foreignId('event_id');
            $table->foreignId('lodge_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('person_id')->constrained()->restrictOnDelete();
            $table->string('status')->default('committed');
            $table->timestampTz('committed_at');
            $table->timestampTz('withdrawn_at')->nullable();
            $table->timestampTz('administratively_removed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('removed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->foreign(['event_occurrence_id', 'event_id', 'lodge_id'], 'event_volunteer_commitment_occurrence_event_lodge_foreign')->references(['id', 'event_id', 'lodge_id'])->on('event_occurrences')->cascadeOnDelete();
            $table->foreign(['event_volunteer_position_id', 'event_id', 'lodge_id'], 'event_volunteer_commitment_position_event_lodge_foreign')->references(['id', 'event_id', 'lodge_id'])->on('event_volunteer_positions')->cascadeOnDelete();
            $table->unique(['id', 'event_volunteer_position_id', 'event_occurrence_id', 'event_id', 'lodge_id']);
            $table->index(['event_occurrence_id', 'status']);
            $table->index(['person_id', 'status', 'event_occurrence_id']);
            $table->index(['lodge_id', 'status']);
        });
        Schema::create('event_volunteer_reminder_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_volunteer_commitment_id')->unique();
            $table->foreignId('event_volunteer_position_id');
            $table->foreignId('event_occurrence_id');
            $table->foreignId('event_id');
            $table->foreignId('lodge_id')->constrained()->cascadeOnDelete();
            $table->string('recipient_email')->nullable();
            $table->string('normalized_recipient_email')->nullable();
            $table->timestampTz('due_at');
            $table->string('status')->default('pending');
            $table->string('skip_reason')->nullable();
            $table->timestampTz('claimed_at')->nullable();
            $table->timestampTz('attempted_at')->nullable();
            $table->timestampTz('sent_at')->nullable();
            $table->timestampTz('skipped_at')->nullable();
            $table->timestampTz('failed_at')->nullable();
            $table->string('last_error', 1000)->nullable();
            $table->timestamps();
            $table->foreign(['event_volunteer_commitment_id', 'event_volunteer_position_id', 'event_occurrence_id', 'event_id', 'lodge_id'], 'event_volunteer_delivery_commitment_chain_foreign')->references(['id', 'event_volunteer_position_id', 'event_occurrence_id', 'event_id', 'lodge_id'])->on('event_volunteer_commitments')->cascadeOnDelete();
            $table->index(['status', 'due_at']);
            $table->index(['event_occurrence_id', 'status']);
        });
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE event_volunteer_positions ADD CONSTRAINT event_volunteer_positions_needed_count_check CHECK (needed_count > 0)');
            DB::statement("CREATE UNIQUE INDEX event_volunteer_commitments_active_unique ON event_volunteer_commitments (event_volunteer_position_id, event_occurrence_id, person_id) WHERE status = 'committed'");
            DB::statement("ALTER TABLE event_volunteer_commitments ADD CONSTRAINT event_volunteer_commitments_status_check CHECK ((status = 'committed' AND withdrawn_at IS NULL AND administratively_removed_at IS NULL) OR (status = 'withdrawn' AND withdrawn_at IS NOT NULL) OR (status = 'administratively_removed' AND administratively_removed_at IS NOT NULL))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('event_volunteer_reminder_deliveries');
        Schema::dropIfExists('event_volunteer_commitments');
        Schema::dropIfExists('event_volunteer_positions');
    }
};
