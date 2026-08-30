<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_occurrences', function (Blueprint $table) {
            $table->index(['status', 'starts_at'], 'event_occurrences_discovery_status_starts_index');
        });

        Schema::table('lodges', function (Blueprint $table) {
            $table->index(['status', 'name', 'number'], 'lodges_discovery_status_name_number_index');
        });

        Schema::table('memberships', function (Blueprint $table) {
            $table->index(['person_id', 'membership_status_id', 'end_date', 'lodge_id'], 'memberships_active_person_lodge_index');
        });
    }

    public function down(): void
    {
        Schema::table('memberships', function (Blueprint $table) {
            $table->dropIndex('memberships_active_person_lodge_index');
        });

        Schema::table('lodges', function (Blueprint $table) {
            $table->dropIndex('lodges_discovery_status_name_number_index');
        });

        Schema::table('event_occurrences', function (Blueprint $table) {
            $table->dropIndex('event_occurrences_discovery_status_starts_index');
        });
    }
};
