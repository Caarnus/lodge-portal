<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('website_page_versions', function (Blueprint $table) {
            $table->boolean('is_navigation_container')->default(false)->after('show_in_navigation');
        });
    }

    public function down(): void
    {
        Schema::table('website_page_versions', function (Blueprint $table) {
            $table->dropColumn('is_navigation_container');
        });
    }
};
