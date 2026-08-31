<?php

use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\ProfilePhotoController;
use App\Http\Controllers\Settings\ProfileSettingsController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['auth', 'verified', 'approved'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::put('settings/profile/directory-privacy', [ProfileSettingsController::class, 'updateDirectoryPrivacy'])->name('profile.directory-privacy.update');
    Route::put('settings/profile/memberships/{membership}/communication-preference', [ProfileSettingsController::class, 'updateCommunicationPreference'])->name('profile.communication-preference.update');
    Route::post('settings/profile/photo', [ProfilePhotoController::class, 'store'])->name('profile.photo.store');
    Route::get('settings/profile/photo', [ProfilePhotoController::class, 'show'])->name('profile.photo.show');
    Route::delete('settings/profile/photo', [ProfilePhotoController::class, 'destroy'])->name('profile.photo.destroy');

    Route::get('settings/password', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('settings/password', [PasswordController::class, 'update'])->name('password.update');

    Route::get('settings/appearance', function () {
        return Inertia::render('settings/Appearance', []);
    })->name('appearance');

    Route::get('settings/two-factor', function () {
        return Inertia::render('settings/TwoFactor', [
            'enabled' => (bool)request()->user()->two_factor_secret,
            'confirmed' => (bool)request()->user()->two_factor_confirmed_at,
            'required' => config('security.admin_2fa_required'),
        ]);
    })->name('two-factor.settings');
});
