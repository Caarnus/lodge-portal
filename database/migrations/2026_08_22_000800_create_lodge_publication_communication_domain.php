<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('membership_communication_preferences', function (Blueprint $table) {
            $table->boolean('receives_print_newsletter')->default(false);
        });

        Schema::table('media_assets', function (Blueprint $table) {
            $table->string('private_derivative_path')->nullable()->after('derivative_path');
        });

        DB::table('membership_communication_preferences')->update(['receives_print_newsletter' => false]);

        Schema::create('newsletter_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lodge_id')->constrained()->cascadeOnDelete();
            $table->string('slug', 160);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['id', 'lodge_id']);
        });

        Schema::create('newsletter_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lodge_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('original_name');
            $table->string('storage_path');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size');
            $table->string('sha256', 64);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['id', 'lodge_id']);
        });

        Schema::create('newsletter_issue_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lodge_id')->constrained()->cascadeOnDelete();
            $table->foreignId('newsletter_issue_id');
            $table->string('status', 16)->default('draft');
            $table->string('title');
            $table->date('publication_date')->nullable();
            $table->foreignId('cover_media_asset_id')->nullable();
            $table->longText('body_html')->nullable();
            $table->foreignId('newsletter_document_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('published_at')->nullable();
            $table->timestamps();
            $table->unique(['id', 'lodge_id']);
            $table->foreign(['newsletter_issue_id', 'lodge_id'], 'newsletter_issue_versions_issue_lodge_foreign')
                ->references(['id', 'lodge_id'])->on('newsletter_issues')->cascadeOnDelete();
            $table->foreign(['cover_media_asset_id', 'lodge_id'], 'newsletter_issue_versions_cover_lodge_foreign')
                ->references(['id', 'lodge_id'])->on('media_assets')->restrictOnDelete();
            $table->foreign(['newsletter_document_id', 'lodge_id'], 'newsletter_issue_versions_document_lodge_foreign')
                ->references(['id', 'lodge_id'])->on('newsletter_documents')->restrictOnDelete();
            $table->index(['lodge_id', 'status']);
        });

        Schema::create('gallery_albums', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lodge_id')->constrained()->cascadeOnDelete();
            $table->string('slug', 160);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['id', 'lodge_id']);
        });

        Schema::create('gallery_album_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lodge_id')->constrained()->cascadeOnDelete();
            $table->foreignId('gallery_album_id');
            $table->string('status', 16)->default('draft');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('visibility', 16)->default('public');
            $table->foreignId('cover_photo_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('published_at')->nullable();
            $table->timestamps();
            $table->unique(['id', 'lodge_id']);
            $table->foreign(['gallery_album_id', 'lodge_id'], 'gallery_album_versions_album_lodge_foreign')
                ->references(['id', 'lodge_id'])->on('gallery_albums')->cascadeOnDelete();
            $table->index(['lodge_id', 'status', 'visibility']);
        });

        Schema::create('gallery_album_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lodge_id')->constrained()->cascadeOnDelete();
            $table->foreignId('gallery_album_version_id');
            $table->foreignId('media_asset_id');
            $table->text('caption')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['id', 'gallery_album_version_id', 'lodge_id']);
            $table->unique(['gallery_album_version_id', 'sort_order']);
            $table->foreign(['gallery_album_version_id', 'lodge_id'], 'gallery_album_photos_version_lodge_foreign')
                ->references(['id', 'lodge_id'])->on('gallery_album_versions')->cascadeOnDelete();
            $table->foreign(['media_asset_id', 'lodge_id'], 'gallery_album_photos_media_lodge_foreign')
                ->references(['id', 'lodge_id'])->on('media_assets')->restrictOnDelete();
        });

        Schema::table('gallery_album_versions', function (Blueprint $table) {
            $table->foreign(['cover_photo_id', 'id', 'lodge_id'], 'gallery_album_versions_cover_photo_version_lodge_foreign')
                ->references(['id', 'gallery_album_version_id', 'lodge_id'])->on('gallery_album_photos')->restrictOnDelete();
        });

        Schema::create('lodge_communications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lodge_id')->constrained()->cascadeOnDelete();
            $table->string('status', 24)->default('draft');
            $table->string('subject');
            $table->longText('body_html');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('last_edited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('send_requested_at')->nullable();
            $table->timestampTz('sent_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['id', 'lodge_id']);
            $table->index(['lodge_id', 'status']);
        });

        Schema::create('lodge_communication_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lodge_id')->constrained()->cascadeOnDelete()->unique();
            $table->string('sender_display_name')->nullable();
            $table->string('reply_to_email')->nullable();
            $table->string('secretary_email')->nullable();
            $table->string('newsletter_contact_email')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('family_newsletter_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lodge_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recipient_person_id')->constrained('people')->restrictOnDelete();
            $table->foreignId('sponsoring_person_id')->constrained('people')->restrictOnDelete();
            $table->foreignId('person_relationship_id')->constrained('person_relationships')->restrictOnDelete();
            $table->boolean('receives_email')->default(false);
            $table->boolean('receives_print')->default(false);
            $table->string('status', 16)->default('active');
            $table->string('consent_source', 80);
            $table->timestampTz('requested_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('administrative_note')->nullable();
            $table->timestampTz('unsubscribed_at')->nullable();
            $table->timestamps();
            $table->unique(['id', 'lodge_id']);
            $table->index(['lodge_id', 'status']);
        });

        Schema::create('family_newsletter_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lodge_id')->constrained()->cascadeOnDelete();
            $table->boolean('receives_email')->default(false);
            $table->boolean('receives_print')->default(false);
            $table->string('requester_name');
            $table->string('requester_email')->nullable();
            $table->string('mailing_address_line_1')->nullable();
            $table->string('mailing_address_line_2')->nullable();
            $table->string('mailing_city')->nullable();
            $table->string('mailing_state', 2)->nullable();
            $table->string('mailing_postal_code', 16)->nullable();
            $table->string('claimed_relationship', 120)->nullable();
            $table->string('claimed_related_member_name')->nullable();
            $table->string('status', 24)->default('pending_review');
            $table->string('email_verification_token_hash', 64)->nullable();
            $table->timestampTz('email_verification_expires_at')->nullable();
            $table->string('request_ip', 45)->nullable();
            $table->string('request_user_agent', 1000)->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->foreignId('family_newsletter_subscription_id')->nullable();
            $table->timestamps();
            $table->foreign(['family_newsletter_subscription_id', 'lodge_id'], 'family_newsletter_requests_subscription_lodge_foreign')
                ->references(['id', 'lodge_id'])->on('family_newsletter_subscriptions')->nullOnDelete();
            $table->index(['lodge_id', 'status']);
            $table->index('email_verification_token_hash');
        });

        Schema::create('communication_distribution_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lodge_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 24);
            $table->foreignId('newsletter_issue_version_id')->nullable();
            $table->foreignId('lodge_communication_id')->nullable();
            $table->string('status', 32)->default('preparing');
            $table->string('idempotency_key', 100)->unique();
            $table->unsignedInteger('email_recipient_count')->default(0);
            $table->unsignedInteger('postal_recipient_count')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->foreignId('initiated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['id', 'lodge_id']);
            $table->foreign(['newsletter_issue_version_id', 'lodge_id'], 'communication_runs_newsletter_version_lodge_foreign')
                ->references(['id', 'lodge_id'])->on('newsletter_issue_versions')->restrictOnDelete();
            $table->foreign(['lodge_communication_id', 'lodge_id'], 'communication_runs_message_lodge_foreign')
                ->references(['id', 'lodge_id'])->on('lodge_communications')->restrictOnDelete();
            $table->index(['lodge_id', 'status']);
        });

        Schema::create('communication_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lodge_id')->constrained()->cascadeOnDelete();
            $table->foreignId('communication_distribution_run_id');
            $table->string('channel', 16);
            $table->foreignId('membership_id')->nullable();
            $table->foreignId('family_newsletter_subscription_id')->nullable();
            $table->string('recipient_name');
            $table->string('recipient_email')->nullable();
            $table->string('normalized_recipient_email')->nullable();
            $table->string('mailing_address_line_1')->nullable();
            $table->string('mailing_address_line_2')->nullable();
            $table->string('mailing_city')->nullable();
            $table->string('mailing_state', 2)->nullable();
            $table->string('mailing_postal_code', 16)->nullable();
            $table->string('status', 16)->default('pending');
            $table->string('skip_reason')->nullable();
            $table->timestampTz('claimed_at')->nullable();
            $table->timestampTz('attempted_at')->nullable();
            $table->timestampTz('sent_at')->nullable();
            $table->timestampTz('prepared_at')->nullable();
            $table->timestampTz('mailed_at')->nullable();
            $table->string('last_error', 1000)->nullable();
            $table->string('provider_message_id')->nullable();
            $table->string('unsubscribe_token_hash', 64)->nullable();
            $table->timestamps();
            $table->foreign(['communication_distribution_run_id', 'lodge_id'], 'communication_deliveries_run_lodge_foreign')
                ->references(['id', 'lodge_id'])->on('communication_distribution_runs')->cascadeOnDelete();
            $table->foreign(['membership_id', 'lodge_id'], 'communication_deliveries_membership_lodge_foreign')
                ->references(['id', 'lodge_id'])->on('memberships')->restrictOnDelete();
            $table->foreign(['family_newsletter_subscription_id', 'lodge_id'], 'communication_deliveries_subscription_lodge_foreign')
                ->references(['id', 'lodge_id'])->on('family_newsletter_subscriptions')->restrictOnDelete();
            $table->index(['status', 'claimed_at']);
            $table->index('unsubscribe_token_hash');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE UNIQUE INDEX newsletter_issues_lodge_slug_unique ON newsletter_issues (lodge_id, slug) WHERE deleted_at IS NULL');
            DB::statement("CREATE UNIQUE INDEX newsletter_issue_versions_current_unique ON newsletter_issue_versions (newsletter_issue_id, status) WHERE status IN ('draft', 'published')");
            DB::statement('CREATE UNIQUE INDEX gallery_albums_lodge_slug_unique ON gallery_albums (lodge_id, slug) WHERE deleted_at IS NULL');
            DB::statement("CREATE UNIQUE INDEX gallery_album_versions_current_unique ON gallery_album_versions (gallery_album_id, status) WHERE status IN ('draft', 'published')");
            DB::statement("CREATE UNIQUE INDEX family_newsletter_subscriptions_active_unique ON family_newsletter_subscriptions (lodge_id, recipient_person_id) WHERE status = 'active'");
            DB::statement('CREATE UNIQUE INDEX communication_deliveries_member_channel_unique ON communication_deliveries (communication_distribution_run_id, membership_id, channel) WHERE membership_id IS NOT NULL');
            DB::statement('CREATE UNIQUE INDEX communication_deliveries_subscription_channel_unique ON communication_deliveries (communication_distribution_run_id, family_newsletter_subscription_id, channel) WHERE family_newsletter_subscription_id IS NOT NULL');
            DB::statement("ALTER TABLE newsletter_issue_versions ADD CONSTRAINT newsletter_issue_versions_status_check CHECK (status IN ('draft', 'published', 'archived'))");
            DB::statement("ALTER TABLE gallery_album_versions ADD CONSTRAINT gallery_album_versions_status_check CHECK (status IN ('draft', 'published', 'archived'))");
            DB::statement("ALTER TABLE gallery_album_versions ADD CONSTRAINT gallery_album_versions_visibility_check CHECK (visibility IN ('public', 'masons', 'lodge'))");
            DB::statement("ALTER TABLE lodge_communications ADD CONSTRAINT lodge_communications_status_check CHECK (status IN ('draft', 'sending', 'sent', 'cancelled'))");
            DB::statement('ALTER TABLE family_newsletter_subscriptions ADD CONSTRAINT family_newsletter_subscriptions_channels_check CHECK (receives_email OR receives_print)');
            DB::statement("ALTER TABLE family_newsletter_subscriptions ADD CONSTRAINT family_newsletter_subscriptions_status_check CHECK (status IN ('active', 'unsubscribed', 'inactive'))");
            DB::statement('ALTER TABLE family_newsletter_requests ADD CONSTRAINT family_newsletter_requests_channels_check CHECK (receives_email OR receives_print)');
            DB::statement("ALTER TABLE family_newsletter_requests ADD CONSTRAINT family_newsletter_requests_status_check CHECK (status IN ('pending_verification', 'pending_review', 'approved', 'rejected', 'expired'))");
            DB::statement("ALTER TABLE communication_distribution_runs ADD CONSTRAINT communication_distribution_runs_source_check CHECK ((kind = 'newsletter' AND newsletter_issue_version_id IS NOT NULL AND lodge_communication_id IS NULL) OR (kind = 'general_message' AND newsletter_issue_version_id IS NULL AND lodge_communication_id IS NOT NULL))");
            DB::statement("ALTER TABLE communication_distribution_runs ADD CONSTRAINT communication_distribution_runs_status_check CHECK (status IN ('preparing', 'ready', 'sending', 'completed', 'completed_with_failures', 'cancelled'))");
            DB::statement('ALTER TABLE communication_deliveries ADD CONSTRAINT communication_deliveries_source_check CHECK ((membership_id IS NOT NULL AND family_newsletter_subscription_id IS NULL) OR (membership_id IS NULL AND family_newsletter_subscription_id IS NOT NULL))');
            DB::statement("ALTER TABLE communication_deliveries ADD CONSTRAINT communication_deliveries_channel_check CHECK (channel IN ('email', 'postal'))");
            DB::statement("ALTER TABLE communication_deliveries ADD CONSTRAINT communication_deliveries_status_check CHECK (status IN ('pending', 'claimed', 'sent', 'failed', 'skipped', 'prepared', 'mailed'))");
        }

        $now = now();
        $permissions = [
            'newsletters.manage' => 'Manage newsletter drafts and documents',
            'newsletters.publish' => 'Publish lodge newsletters',
            'galleries.manage' => 'Manage gallery drafts and photos',
            'galleries.publish' => 'Publish lodge galleries',
            'communications.send' => 'Send lodge communications',
            'communications.settings' => 'Manage lodge communication settings',
            'communications.recipients' => 'Manage newsletter recipients and print preferences',
        ];
        foreach ($permissions as $key => $name) {
            DB::table('permissions')->insertOrIgnore([
                'key' => $key,
                'name' => $name,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        $permissionIds = DB::table('permissions')->whereIn('key', array_keys($permissions))->pluck('id');
        foreach (DB::table('roles')->where('name', 'Administrator')->pluck('id') as $roleId) {
            foreach ($permissionIds as $permissionId) {
                DB::table('permission_role')->insertOrIgnore(['permission_id' => $permissionId, 'role_id' => $roleId]);
            }
        }
        $sendPermissionId = DB::table('permissions')->where('key', 'communications.send')->value('id');
        foreach (DB::table('roles')->where('name', 'Officer')->pluck('id') as $roleId) {
            DB::table('permission_role')->insertOrIgnore(['permission_id' => $sendPermissionId, 'role_id' => $roleId]);
        }
    }

    public function down(): void
    {
        DB::table('permissions')->whereIn('key', [
            'newsletters.manage',
            'newsletters.publish',
            'galleries.manage',
            'galleries.publish',
            'communications.send',
            'communications.settings',
            'communications.recipients',
        ])->delete();
        Schema::dropIfExists('communication_deliveries');
        Schema::dropIfExists('communication_distribution_runs');
        Schema::dropIfExists('family_newsletter_requests');
        Schema::dropIfExists('family_newsletter_subscriptions');
        Schema::dropIfExists('lodge_communication_settings');
        Schema::dropIfExists('lodge_communications');
        Schema::table('gallery_album_versions', function (Blueprint $table) {
            $table->dropForeign('gallery_album_versions_cover_photo_version_lodge_foreign');
        });
        Schema::dropIfExists('gallery_album_photos');
        Schema::dropIfExists('gallery_album_versions');
        Schema::dropIfExists('gallery_albums');
        Schema::dropIfExists('newsletter_issue_versions');
        Schema::dropIfExists('newsletter_documents');
        Schema::dropIfExists('newsletter_issues');
        Schema::table('media_assets', function (Blueprint $table) {
            $table->dropColumn('private_derivative_path');
        });
        Schema::table('membership_communication_preferences', function (Blueprint $table) {
            $table->dropColumn('receives_print_newsletter');
        });
    }
};
