<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('website_page_versions', function (Blueprint $table) {
            $table->string('navigation_visibility', 16)->default('public')->after('show_in_navigation');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE website_page_versions ADD CONSTRAINT website_page_versions_navigation_visibility_check CHECK (navigation_visibility IN ('public', 'masons', 'lodge'))");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE website_page_versions DROP CONSTRAINT IF EXISTS website_page_versions_navigation_visibility_check');
        }

        Schema::table('website_page_versions', function (Blueprint $table) {
            $table->dropColumn('navigation_visibility');
        });
    }
};
