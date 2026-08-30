<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lodges', function (Blueprint $table) {
            $table->string('meeting_schedule')->nullable()->after('meeting_location');
        });

        Schema::create('lodge_group_types', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('lodge_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lodge_group_type_id')->constrained()->restrictOnDelete();
            $table->string('name')->unique();
            $table->string('slug', 100)->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('has_public_landing_page')->default(false);
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['is_active', 'archived_at', 'has_public_landing_page']);
            $table->index('lodge_group_type_id');
        });

        Schema::create('lodge_group_memberships', function (Blueprint $table) {
            $table->foreignId('lodge_group_id')->constrained()->restrictOnDelete();
            $table->foreignId('lodge_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->primary(['lodge_group_id', 'lodge_id']);
            $table->index('lodge_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lodge_group_memberships');
        Schema::dropIfExists('lodge_groups');
        Schema::dropIfExists('lodge_group_types');

        Schema::table('lodges', function (Blueprint $table) {
            $table->dropColumn('meeting_schedule');
        });
    }
};
