<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lodges', function (Blueprint $table) {
            $table->string('seal_path')->nullable();
            $table->string('tag_line')->nullable();
        });

        Schema::create('website_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lodge_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['id', 'lodge_id']);
        });

        Schema::create('website_page_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lodge_id')->constrained()->cascadeOnDelete();
            $table->foreignId('website_page_id');
            $table->string('status', 16)->default('draft');
            $table->string('title');
            $table->string('slug');
            $table->boolean('is_home')->default(false);
            $table->boolean('show_in_navigation')->default(true);
            $table->unsignedInteger('navigation_order')->default(0);
            $table->foreignId('navigation_parent_page_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(['id', 'lodge_id']);
            $table->foreign(['website_page_id', 'lodge_id'])->references(['id', 'lodge_id'])->on('website_pages')->cascadeOnDelete();
            $table->foreign(['navigation_parent_page_id', 'lodge_id'], 'website_versions_parent_lodge_foreign')->references(['id', 'lodge_id'])->on('website_pages')->restrictOnDelete();
            $table->index(['lodge_id', 'status']);
        });

        Schema::create('media_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lodge_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('original_name');
            $table->string('original_path');
            $table->string('derivative_path')->nullable();
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('alt_text');
            $table->string('visibility', 16)->default('public');
            $table->string('processing_status', 16)->default('pending');
            $table->text('processing_error')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->boolean('is_platform_shared')->default(false);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['id', 'lodge_id']);
            $table->index(['lodge_id', 'processing_status']);
        });

        Schema::create('website_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lodge_id')->constrained()->cascadeOnDelete();
            $table->foreignId('website_page_version_id');
            $table->string('type', 40);
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('configuration');
            $table->timestamps();
            $table->foreign(['website_page_version_id', 'lodge_id'], 'website_sections_version_lodge_foreign')->references(['id', 'lodge_id'])->on('website_page_versions')->cascadeOnDelete();
            $table->unique(['website_page_version_id', 'sort_order']);
        });

        DB::statement("CREATE UNIQUE INDEX website_versions_current_unique ON website_page_versions (website_page_id, status) WHERE status IN ('draft', 'published')");
        DB::statement("CREATE UNIQUE INDEX website_versions_slug_unique ON website_page_versions (lodge_id, status, slug) WHERE status IN ('draft', 'published')");
        DB::statement("CREATE UNIQUE INDEX website_versions_home_unique ON website_page_versions (lodge_id, status) WHERE is_home AND status IN ('draft', 'published')");
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE media_assets ADD CONSTRAINT media_assets_visibility_check CHECK (visibility IN ('public', 'private'))");
            DB::statement('ALTER TABLE media_assets ADD CONSTRAINT media_assets_owner_check CHECK ((lodge_id IS NOT NULL AND NOT is_platform_shared) OR (lodge_id IS NULL AND is_platform_shared))');
        }

        $now = now();
        DB::table('permissions')->insertOrIgnore([
            ['key' => 'website.manage', 'name' => 'Manage lodge website', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'website.publish', 'name' => 'Publish lodge website', 'created_at' => $now, 'updated_at' => $now],
        ]);
        $permissionIds = DB::table('permissions')->whereIn('key', ['website.manage', 'website.publish'])->pluck('id');
        foreach (DB::table('roles')->where('name', 'Administrator')->pluck('id') as $roleId) {
            foreach ($permissionIds as $permissionId) {
                DB::table('permission_role')->insertOrIgnore(['permission_id' => $permissionId, 'role_id' => $roleId]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('website_sections');
        Schema::dropIfExists('media_assets');
        Schema::dropIfExists('website_page_versions');
        Schema::dropIfExists('website_pages');
        Schema::table('lodges', fn (Blueprint $table) => $table->dropColumn(['seal_path', 'tag_line']));
        DB::table('permissions')->whereIn('key', ['website.manage', 'website.publish'])->delete();
    }
};
