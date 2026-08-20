<?php

use App\Http\Controllers\LodgeSettingsController;
use App\Http\Controllers\Platform\LodgeController;
use App\Http\Controllers\RegistrationReviewController;
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
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
