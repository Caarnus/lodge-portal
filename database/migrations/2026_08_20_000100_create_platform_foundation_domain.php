<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lodges', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('number');
            $t->string('slug')->unique();
            $t->string('city');
            $t->string('state', 2);
            $t->string('jurisdiction');
            $t->string('physical_address');
            $t->string('mailing_address')->nullable();
            $t->string('meeting_location')->nullable();
            $t->string('timezone')->default('America/Chicago');
            $t->string('public_email');
            $t->string('public_phone')->nullable();
            $t->string('status')->default('active');
            $t->string('logo_path')->nullable();
            $t->string('primary_color', 7)->default('#1E3A5F');
            $t->string('secondary_color', 7)->default('#D4AF37');
            $t->timestamps();
        });
        Schema::create('people', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('email')->nullable()->unique();
            $t->timestamps();
        });
        Schema::table('users', function (Blueprint $t) {
            $t->foreignId('person_id')->nullable()->unique()->constrained()->nullOnDelete();
            $t->foreignId('home_lodge_id')->nullable()->constrained('lodges')->nullOnDelete();
            $t->foreignId('current_lodge_id')->nullable()->constrained('lodges')->nullOnDelete();
            $t->boolean('is_platform_admin')->default(false);
            $t->string('approval_status')->default('pending');
            $t->timestamp('approved_at')->nullable();
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->text('rejection_reason')->nullable();
            $t->text('two_factor_secret')->nullable();
            $t->text('two_factor_recovery_codes')->nullable();
            $t->timestamp('two_factor_confirmed_at')->nullable();
        });
        Schema::create('permissions', function (Blueprint $t) {
            $t->id();
            $t->string('key')->unique();
            $t->string('name');
            $t->timestamps();
        });
        Schema::create('roles', function (Blueprint $t) {
            $t->id();
            $t->foreignId('lodge_id')->nullable()->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->boolean('is_system')->default(false);
            $t->timestamps();
            $t->unique(['lodge_id', 'name']);
        });
        Schema::create('permission_role', function (Blueprint $t) {
            $t->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $t->foreignId('role_id')->constrained()->cascadeOnDelete();
            $t->primary(['permission_id', 'role_id']);
        });
        Schema::create('lodge_user_roles', function (Blueprint $t) {
            $t->id();
            $t->foreignId('lodge_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('role_id')->constrained()->cascadeOnDelete();
            $t->timestamps();
            $t->unique(['lodge_id', 'user_id', 'role_id']);
        });
        Schema::create('features', function (Blueprint $t) {
            $t->id();
            $t->string('key')->unique();
            $t->string('name');
            $t->text('description')->nullable();
            $t->timestamps();
        });
        Schema::create('feature_lodge', function (Blueprint $t) {
            $t->foreignId('feature_id')->constrained()->cascadeOnDelete();
            $t->foreignId('lodge_id')->constrained()->cascadeOnDelete();
            $t->boolean('enabled')->default(false);
            $t->timestamps();
            $t->primary(['feature_id', 'lodge_id']);
        });
        Schema::create('audit_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('lodge_id')->nullable()->constrained()->nullOnDelete();
            $t->string('action');
            $t->nullableMorphs('subject');
            $t->json('before')->nullable();
            $t->json('after')->nullable();
            $t->ipAddress('ip_address')->nullable();
            $t->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
        Schema::dropIfExists('feature_lodge');
        Schema::dropIfExists('features');
        Schema::dropIfExists('lodge_user_roles');
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('permissions');
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['person_id', 'home_lodge_id', 'current_lodge_id', 'is_platform_admin', 'approval_status', 'approved_at', 'approved_by', 'rejection_reason', 'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at']));
        Schema::dropIfExists('people');
        Schema::dropIfExists('lodges');
    }
};
