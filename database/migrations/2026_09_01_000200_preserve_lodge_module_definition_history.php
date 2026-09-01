<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feature_lodge', function (Blueprint $table) {
            // Retiring is the supported lifecycle, but an accidental definition delete must
            // never cascade through preserved lodge preferences and audit references.
            $table->dropForeign(['feature_id']);
            $table->foreign('feature_id')->references('id')->on('features')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('feature_lodge', function (Blueprint $table) {
            $table->dropForeign(['feature_id']);
            $table->foreign('feature_id')->references('id')->on('features')->cascadeOnDelete();
        });
    }
};
