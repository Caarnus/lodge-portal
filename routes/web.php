<?php

use App\Http\Controllers\LodgeSettingsController;
use App\Http\Controllers\MediaAssetController;
use App\Http\Controllers\Platform\LodgeController;
use App\Http\Controllers\PublicWebsiteController;
use App\Http\Controllers\RegistrationReviewController;
use App\Http\Controllers\WebsiteController;
use App\Http\Controllers\WebsiteSectionController;
use App\Models\Lodge;
use Illuminate\Http\Request;
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
    Route::get('registrations', [RegistrationReviewController::class, 'index'])->name('registrations.index');
    Route::patch('registrations/{user}', [RegistrationReviewController::class, 'decide'])->name('registrations.decide');
    Route::get('lodges/{lodge}/settings', [LodgeSettingsController::class, 'edit'])->name('lodges.settings.edit');
    Route::put('lodges/{lodge}/settings', [LodgeSettingsController::class, 'update'])->name('lodges.settings.update');
    Route::post('lodges/{lodge}/activate', function (Request $r, Lodge $lodge) {
        abort_unless($r->user()->hasLodgePermission($lodge, 'lodge.manage'), 403);
        $r->user()->update(['current_lodge_id' => $lodge->id]);

        return back();
    })->name('lodges.activate');

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
