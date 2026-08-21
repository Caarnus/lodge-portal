<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->string('profile_photo_status')->nullable();
            $table->text('profile_photo_error')->nullable();
            $table->string('profile_photo_original_name')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('people', fn (Blueprint $table) => $table->dropColumn([
            'profile_photo_status', 'profile_photo_error', 'profile_photo_original_name',
        ]));
    }
};
