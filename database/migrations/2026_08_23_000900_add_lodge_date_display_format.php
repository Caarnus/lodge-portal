<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lodges', function (Blueprint $table) {
            $table->string('date_display_format', 32)->default('month_year')->after('timezone');
        });
    }

    public function down(): void
    {
        Schema::table('lodges', function (Blueprint $table) {
            $table->dropColumn('date_display_format');
        });
    }
};
