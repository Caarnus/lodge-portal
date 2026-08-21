<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_categories', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('event_category_lodge', function (Blueprint $table) {
            $table->foreignId('event_category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lodge_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['event_category_id', 'lodge_id']);
        });

        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lodge_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_category_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('slug');
            $table->string('status')->default('draft');
            $table->string('title');
            $table->longText('description')->nullable();
            $table->string('location_name')->nullable();
            $table->text('location_details')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('time_zone');
            $table->timestampTz('first_starts_at');
            $table->unsignedInteger('duration_minutes');
            $table->text('rrule')->nullable();
            $table->string('visibility')->default('public');
            $table->string('required_qualification')->nullable();
            $table->boolean('allows_cross_lodge_reservations')->default(false);
            $table->boolean('reservations_enabled')->default(false);
            $table->boolean('guest_reservations_enabled')->default(false);
            $table->unsignedInteger('capacity')->nullable();
            $table->unsignedInteger('maximum_party_size')->nullable();
            $table->boolean('reminders_enabled')->default(true);
            $table->boolean('guest_reminders_enabled')->default(true);
            $table->foreignId('cover_media_asset_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->timestampTz('occurrences_generated_through')->nullable();
            $table->timestampTz('published_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->timestampTz('archived_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['lodge_id', 'slug']);
            $table->unique(['id', 'lodge_id']);
            $table->index(['lodge_id', 'status', 'published_at']);
        });

        Schema::create('event_occurrences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id');
            $table->foreignId('lodge_id')->constrained()->cascadeOnDelete();
            $table->string('recurrence_key');
            $table->timestampTz('original_starts_at');
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');
            $table->string('status')->default('scheduled');
            $table->string('title_override')->nullable();
            $table->longText('description_override')->nullable();
            $table->string('location_name_override')->nullable();
            $table->text('location_details_override')->nullable();
            $table->string('contact_name_override')->nullable();
            $table->string('contact_email_override')->nullable();
            $table->string('contact_phone_override')->nullable();
            $table->timestampTz('overridden_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->timestamps();
            $table->foreign(['event_id', 'lodge_id'], 'event_occurrence_event_lodge_foreign')
                ->references(['id', 'lodge_id'])->on('events')->cascadeOnDelete();
            $table->unique(['event_id', 'recurrence_key']);
            $table->unique(['id', 'event_id', 'lodge_id']);
            $table->index(['lodge_id', 'status', 'starts_at']);
        });

        Schema::create('event_reservation_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id');
            $table->foreignId('lodge_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('label');
            $table->text('help_text')->nullable();
            $table->string('type');
            $table->boolean('is_required')->default(false);
            $table->json('options')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->foreign(['event_id', 'lodge_id'], 'event_reservation_field_event_lodge_foreign')
                ->references(['id', 'lodge_id'])->on('events')->cascadeOnDelete();
            $table->unique(['event_id', 'key']);
        });

        Schema::create('event_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_occurrence_id');
            $table->foreignId('event_id');
            $table->foreignId('lodge_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('person_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('email');
            $table->string('normalized_email');
            $table->string('phone')->nullable();
            $table->unsignedInteger('party_size')->default(1);
            $table->json('responses')->nullable();
            $table->string('status')->default('confirmed');
            $table->string('cancellation_token_hash', 64)->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->timestamps();
            $table->foreign(['event_occurrence_id', 'event_id', 'lodge_id'], 'event_reservation_occurrence_event_lodge_foreign')
                ->references(['id', 'event_id', 'lodge_id'])->on('event_occurrences')->cascadeOnDelete();
            $table->index(['event_occurrence_id', 'status']);
        });

        Schema::create('event_reminder_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id');
            $table->foreignId('lodge_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('offset_minutes');
            $table->timestamps();
            $table->foreign(['event_id', 'lodge_id'], 'event_reminder_rule_event_lodge_foreign')
                ->references(['id', 'lodge_id'])->on('events')->cascadeOnDelete();
            $table->unique(['event_id', 'offset_minutes']);
        });

        Schema::create('event_reminder_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id');
            $table->foreignId('lodge_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_occurrence_id')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('person_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name')->nullable();
            $table->string('email');
            $table->string('normalized_email');
            $table->string('status')->default('active');
            $table->string('unsubscribe_token_hash', 64)->nullable();
            $table->timestampTz('unsubscribed_at')->nullable();
            $table->timestamps();
            $table->foreign(['event_id', 'lodge_id'], 'event_reminder_subscription_event_lodge_foreign')
                ->references(['id', 'lodge_id'])->on('events')->cascadeOnDelete();
            $table->foreign(['event_occurrence_id', 'event_id', 'lodge_id'], 'event_reminder_subscription_occurrence_event_lodge_foreign')
                ->references(['id', 'event_id', 'lodge_id'])->on('event_occurrences')->cascadeOnDelete();
            $table->index(['event_id', 'event_occurrence_id', 'status']);
        });

        Schema::create('event_reminder_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_reminder_subscription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_reminder_rule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_occurrence_id');
            $table->foreignId('event_id');
            $table->foreignId('lodge_id')->constrained()->cascadeOnDelete();
            $table->string('normalized_email');
            $table->timestampTz('due_at');
            $table->string('status')->default('pending');
            $table->timestampTz('claimed_at')->nullable();
            $table->timestampTz('sent_at')->nullable();
            $table->timestampTz('skipped_at')->nullable();
            $table->timestampTz('failed_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->foreign(['event_occurrence_id', 'event_id', 'lodge_id'], 'event_reminder_delivery_occurrence_event_lodge_foreign')
                ->references(['id', 'event_id', 'lodge_id'])->on('event_occurrences')->cascadeOnDelete();
            $table->unique(['event_reminder_subscription_id', 'event_occurrence_id', 'event_reminder_rule_id'], 'event_reminder_delivery_subscription_occurrence_rule_unique');
            $table->unique(['event_id', 'event_occurrence_id', 'event_reminder_rule_id', 'normalized_email'], 'event_reminder_delivery_recipient_unique');
            $table->index(['status', 'due_at']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("CREATE UNIQUE INDEX event_reservations_active_email_unique ON event_reservations (event_occurrence_id, normalized_email) WHERE status = 'confirmed'");
            DB::statement("CREATE UNIQUE INDEX event_reminder_subscriptions_active_occurrence_email_unique ON event_reminder_subscriptions (event_occurrence_id, normalized_email) WHERE status = 'active' AND event_occurrence_id IS NOT NULL");
            DB::statement("CREATE UNIQUE INDEX event_reminder_subscriptions_active_series_email_unique ON event_reminder_subscriptions (event_id, normalized_email) WHERE status = 'active' AND event_occurrence_id IS NULL");
            DB::statement('ALTER TABLE events ADD CONSTRAINT events_reservation_capacity_check CHECK ((reservations_enabled = false) OR capacity IS NOT NULL AND capacity > 0)');
            DB::statement('ALTER TABLE events ADD CONSTRAINT events_guest_reservation_visibility_check CHECK ((guest_reservations_enabled = false) OR visibility = \'public\')');
            DB::statement('ALTER TABLE events ADD CONSTRAINT events_cross_lodge_reservation_visibility_check CHECK ((allows_cross_lodge_reservations = false) OR visibility = \'masons\')');
            DB::statement('ALTER TABLE events ADD CONSTRAINT events_guest_reminder_visibility_check CHECK ((guest_reminders_enabled = false) OR visibility = \'public\')');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('event_reminder_deliveries');
        Schema::dropIfExists('event_reminder_subscriptions');
        Schema::dropIfExists('event_reminder_rules');
        Schema::dropIfExists('event_reservations');
        Schema::dropIfExists('event_reservation_fields');
        Schema::dropIfExists('event_occurrences');
        Schema::dropIfExists('events');
        Schema::dropIfExists('event_category_lodge');
        Schema::dropIfExists('event_categories');
    }
};
