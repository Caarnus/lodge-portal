<?php

use App\Http\Controllers\LodgeRoleController;
use App\Http\Controllers\LodgeSettingsController;
use App\Http\Controllers\MediaAssetController;
use App\Http\Controllers\MembershipController;
use App\Http\Controllers\OfficerController;
use App\Http\Controllers\PersonAccountController;
use App\Http\Controllers\PersonController;
use App\Http\Controllers\PersonPhotoController;
use App\Http\Controllers\PersonRelationshipController;
use App\Http\Controllers\Platform\LodgeController;
use App\Http\Controllers\Platform\PersonMergeController;
use App\Http\Controllers\PublicWebsiteController;
use App\Http\Controllers\RegistrationReviewController;
use App\Http\Controllers\WebsiteController;
use App\Http\Controllers\WebsiteSectionController;
use App\Models\Lodge;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', []);
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard', []);
})->middleware(['auth', 'verified', 'approved'])->name('dashboard');

Route::get('pending', fn () => Inertia::render('auth/Pending', []))->middleware('auth')->name('pending');
Route::get('l/{lodge:slug}', [PublicWebsiteController::class, 'home'])->name('public.website.home');
Route::get('l/{lodge:slug}/{pageSlug}', [PublicWebsiteController::class, 'page'])->name('public.website.page');
Route::middleware(['auth', 'verified', 'approved', 'admin-2fa'])->group(function () {
    Route::resource('platform/lodges', LodgeController::class)->except(['show', 'destroy'])->names('platform.lodges')->middleware('platform-admin');
    Route::post('platform/lodges/{lodge}/admins', [LodgeController::class, 'assignAdmin'])->name('platform.lodges.admins')->middleware('platform-admin');
    Route::put('platform/lodges/{lodge}/features', [LodgeController::class, 'features'])->name('platform.lodges.features')->middleware('platform-admin');
    Route::get('platform/people/merge', [PersonMergeController::class, 'create'])->name('platform.people.merge.create')->middleware('platform-admin');
    Route::post('platform/people/merge', [PersonMergeController::class, 'store'])->name('platform.people.merge.store')->middleware('platform-admin');
    Route::get('registrations', [RegistrationReviewController::class, 'index'])->name('registrations.index');
    Route::patch('registrations/{user}', [RegistrationReviewController::class, 'decide'])->name('registrations.decide');
    Route::get('lodges/{lodge}/settings', [LodgeSettingsController::class, 'edit'])->name('lodges.settings.edit');
    Route::put('lodges/{lodge}/settings', [LodgeSettingsController::class, 'update'])->name('lodges.settings.update');
    Route::post('lodges/{lodge}/activate', function (Request $r, Lodge $lodge) {
        abort_unless($r->user()->is_platform_admin || DB::table('lodge_user_roles')
            ->where('user_id', $r->user()->id)->where('lodge_id', $lodge->id)->exists(), 403);
        $r->user()->update(['current_lodge_id' => $lodge->id]);

        return back();
    })->name('lodges.activate');

    Route::resource('lodges.people', PersonController::class)->only(['index', 'create', 'store', 'edit', 'update']);
    Route::put('lodges/{lodge}/memberships/{membership}', [MembershipController::class, 'update'])->name('lodges.memberships.update');
    Route::patch('lodges/{lodge}/memberships/{membership}/end', [MembershipController::class, 'end'])->name('lodges.memberships.end');
    Route::post('lodges/{lodge}/memberships/{membership}/past-master-terms', [MembershipController::class, 'addPastMasterTerm'])->name('lodges.memberships.past-master-terms.store');
    Route::delete('lodges/{lodge}/memberships/{membership}/past-master-terms/{term}', [MembershipController::class, 'removePastMasterTerm'])->name('lodges.memberships.past-master-terms.destroy');
    Route::post('lodges/{lodge}/people/{person}/relationships', [PersonRelationshipController::class, 'store'])->name('lodges.relationships.store');
    Route::put('lodges/{lodge}/relationships/{relationship}', [PersonRelationshipController::class, 'update'])->name('lodges.relationships.update');
    Route::delete('lodges/{lodge}/relationships/{relationship}', [PersonRelationshipController::class, 'destroy'])->name('lodges.relationships.destroy');
    Route::post('lodges/{lodge}/people/{person}/account', [PersonAccountController::class, 'store'])->name('lodges.people.account.store');
    Route::delete('lodges/{lodge}/people/{person}/access', [PersonAccountController::class, 'revoke'])->name('lodges.people.access.revoke');
    Route::post('lodges/{lodge}/people/{person}/photo', [PersonPhotoController::class, 'store'])->name('lodges.people.photo.store');
    Route::get('lodges/{lodge}/people/{person}/photo', [PersonPhotoController::class, 'show'])->name('lodges.people.photo.show');
    Route::resource('lodges.officers', OfficerController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::get('lodges/{lodge}/roles', [LodgeRoleController::class, 'index'])->name('lodges.roles.index');
    Route::get('lodges/{lodge}/role-assignments', [LodgeRoleController::class, 'assignments'])->name('lodges.roles.assignments');
    Route::post('lodges/{lodge}/roles', [LodgeRoleController::class, 'store'])->name('lodges.roles.store');
    Route::put('lodges/{lodge}/roles/{role}', [LodgeRoleController::class, 'update'])->name('lodges.roles.update');
    Route::post('lodges/{lodge}/role-assignments', [LodgeRoleController::class, 'assign'])->name('lodges.roles.assign');
    Route::delete('lodges/{lodge}/role-assignments', [LodgeRoleController::class, 'unassign'])->name('lodges.roles.unassign');

    Route::prefix('lodges/{lodge}/website')->name('lodges.website.')->group(function () {
        Route::get('/', [WebsiteController::class, 'index'])->name('index');
        Route::post('pages', [WebsiteController::class, 'store'])->name('pages.store');
        Route::get('pages/{page}/edit', [WebsiteController::class, 'edit'])->name('pages.edit');
        Route::put('pages/{page}', [WebsiteController::class, 'update'])->name('pages.update');
        Route::delete('pages/{page}', [WebsiteController::class, 'destroy'])->name('pages.destroy');
        Route::post('deleted-pages/{pageId}/restore', [WebsiteController::class, 'restore'])->name('pages.restore');
        Route::post('pages/{page}/publish', [WebsiteController::class, 'publish'])->name('pages.publish');
        Route::post('pages/{page}/unpublish', [WebsiteController::class, 'unpublish'])->name('pages.unpublish');
        Route::get('pages/{page}/preview', [PublicWebsiteController::class, 'preview'])->name('pages.preview');
        Route::post('pages/{page}/sections', [WebsiteSectionController::class, 'store'])->name('sections.store');
        Route::put('pages/{page}/sections/{section}', [WebsiteSectionController::class, 'update'])->name('sections.update');
        Route::patch('pages/{page}/sections/{section}/move', [WebsiteSectionController::class, 'move'])->name('sections.move');
        Route::delete('pages/{page}/sections/{section}', [WebsiteSectionController::class, 'destroy'])->name('sections.destroy');
        Route::post('media', [MediaAssetController::class, 'store'])->name('media.store');
        Route::post('media/{media}/retry', [MediaAssetController::class, 'retry'])->name('media.retry');
        Route::get('media/{media}/original', [MediaAssetController::class, 'original'])->name('media.original');
        Route::delete('media/{media}', [MediaAssetController::class, 'destroy'])->name('media.destroy');
        Route::post('template', [WebsiteController::class, 'applyTemplate'])->name('template');
        Route::put('branding', [WebsiteController::class, 'branding'])->name('branding');
    });
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
