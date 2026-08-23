<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lodge_communications', function (Blueprint $table) {
            $table->string('audience_mode', 16)->default('all')->after('body_html');
            $table->json('degree_keys')->nullable()->after('audience_mode');
            $table->json('membership_status_keys')->nullable()->after('degree_keys');
            $table->json('membership_ids')->nullable()->after('membership_status_keys');
            $table->json('relation_person_ids')->nullable()->after('membership_ids');
        });
        Schema::table('communication_deliveries', function (Blueprint $table) {
            $table->foreignId('person_id')->nullable()->after('family_newsletter_subscription_id')->constrained()->restrictOnDelete();
        });
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE communication_deliveries DROP CONSTRAINT communication_deliveries_source_check');
            DB::statement('ALTER TABLE communication_deliveries ADD CONSTRAINT communication_deliveries_source_check CHECK ((membership_id IS NOT NULL AND family_newsletter_subscription_id IS NULL AND person_id IS NULL) OR (membership_id IS NULL AND family_newsletter_subscription_id IS NOT NULL AND person_id IS NULL) OR (membership_id IS NULL AND family_newsletter_subscription_id IS NULL AND person_id IS NOT NULL))');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE communication_deliveries DROP CONSTRAINT communication_deliveries_source_check');
            DB::statement('ALTER TABLE communication_deliveries ADD CONSTRAINT communication_deliveries_source_check CHECK ((membership_id IS NOT NULL AND family_newsletter_subscription_id IS NULL) OR (membership_id IS NULL AND family_newsletter_subscription_id IS NOT NULL))');
        }
        Schema::table('communication_deliveries', fn (Blueprint $table) => $table->dropConstrainedForeignId('person_id'));
        Schema::table('lodge_communications', fn (Blueprint $table) => $table->dropColumn(['audience_mode', 'degree_keys', 'membership_status_keys', 'membership_ids', 'relation_person_ids']));
    }
};
