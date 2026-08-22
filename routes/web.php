<?php

use App\Http\Controllers\EventCategoryController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\EventOccurrenceController;
use App\Http\Controllers\EventReminderDeliveryController;
use App\Http\Controllers\EventReminderRuleController;
use App\Http\Controllers\EventReservationController;
use App\Http\Controllers\EventReservationFieldController;
use App\Http\Controllers\LodgeRoleController;
use App\Http\Controllers\LodgeSettingsController;
use App\Http\Controllers\MediaAssetController;
use App\Http\Controllers\MembershipController;
use App\Http\Controllers\OfficerController;
use App\Http\Controllers\PersonAccountController;
use App\Http\Controllers\PersonController;
use App\Http\Controllers\PersonPhotoController;
use App\Http\Controllers\PersonRelationshipController;
use App\Http\Controllers\Platform\AccountController;
use App\Http\Controllers\Platform\EventCategoryController as PlatformEventCategoryController;
use App\Http\Controllers\Platform\LodgeController;
use App\Http\Controllers\Platform\PersonMergeController;
use App\Http\Controllers\PublicEventCalendarController;
use App\Http\Controllers\PublicEventController;
use App\Http\Controllers\PublicEventReminderController;
use App\Http\Controllers\PublicEventReservationController;
use App\Http\Controllers\PublicEventVolunteerController;
use App\Http\Controllers\EventVolunteerController;
use App\Http\Controllers\EventVolunteerPositionController;
use App\Http\Controllers\PublicReminderUnsubscribeController;
use App\Http\Controllers\PublicReservationCancellationController;
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
    $user = request()->user();
    $commitments = \App\Models\EventVolunteerCommitment::query()->with(['position', 'occurrence.event', 'lodge'])
        ->where('user_id', $user->id)->where('person_id', $user->person_id)->where('status', \App\Enums\VolunteerCommitmentStatus::Committed)
        ->whereHas('position', fn ($query) => $query->where('is_active', true))
        ->whereHas('event', fn ($query) => $query->where('status', \App\Enums\EventStatus::Published))
        ->whereHas('occurrence', fn ($query) => $query->where('status', \App\Enums\EventOccurrenceStatus::Scheduled)->where('starts_at', '>', now()))
        ->get()->sortBy(fn ($commitment) => [$commitment->occurrence->starts_at->timestamp, $commitment->position->sort_order, $commitment->position->name])->values()->map(fn ($commitment) => ['id' => $commitment->id, 'position' => $commitment->position->name, 'event' => $commitment->event->title, 'lodge' => $commitment->lodge->name, 'starts_at' => $commitment->occurrence->starts_at, 'time_zone' => $commitment->event->time_zone, 'location' => $commitment->occurrence->location_name_override ?: $commitment->event->location_name, 'event_url' => route('public.events.show', [$commitment->lodge->slug, $commitment->occurrence->id])]);
    return Inertia::render('Dashboard', ['volunteerCommitments' => $commitments]);
})->middleware(['auth', 'verified', 'approved'])->name('dashboard');

Route::get('pending', fn () => Inertia::render('auth/Pending', []))->middleware('auth')->name('pending');
Route::get('l/{lodge:slug}', [PublicWebsiteController::class, 'home'])->name('public.website.home');
Route::get('l/{lodge:slug}/events', [PublicEventController::class, 'index'])->name('public.events.index');
Route::get('l/{lodge:slug}/calendar.ics', [PublicEventCalendarController::class, 'feed'])->name('public.calendar.feed');
Route::get('l/{lodge:slug}/events/{event}/series.ics', [PublicEventCalendarController::class, 'series'])->name('public.events.series-calendar');
Route::get('l/{lodge:slug}/events/{occurrence}.ics', [PublicEventCalendarController::class, 'occurrence'])->name('public.events.calendar');
Route::post('l/{lodge:slug}/events/{occurrence}/reservations', [PublicEventReservationController::class, 'store'])->middleware('throttle:10,1')->name('public.events.reservations.store');
Route::post('l/{lodge:slug}/events/{occurrence}/volunteer-commitments', [PublicEventVolunteerController::class, 'store'])->middleware(['auth', 'verified', 'approved', 'throttle:10,1'])->name('public.events.volunteer-commitments.store');
Route::patch('l/{lodge:slug}/events/{occurrence}/volunteer-commitments/{commitment}/withdraw', [PublicEventVolunteerController::class, 'withdraw'])->middleware(['auth', 'verified', 'approved', 'throttle:10,1'])->name('public.events.volunteer-commitments.withdraw');
Route::get('l/{lodge:slug}/reservations/cancel/{token}', [PublicReservationCancellationController::class, 'show'])->middleware('throttle:10,1')->name('public.reservations.cancel.show');
Route::post('l/{lodge:slug}/reservations/cancel/{token}', [PublicReservationCancellationController::class, 'store'])->middleware('throttle:10,1')->name('public.reservations.cancel');
Route::get('l/{lodge:slug}/reminders/unsubscribe/{token}', [PublicReminderUnsubscribeController::class, 'show'])->middleware('throttle:10,1')->name('public.reminders.unsubscribe.show');
Route::post('l/{lodge:slug}/reminders/unsubscribe/{token}', [PublicReminderUnsubscribeController::class, 'store'])->middleware('throttle:10,1')->name('public.reminders.unsubscribe');
Route::post('l/{lodge:slug}/events/{event}/reminders', [PublicEventReminderController::class, 'store'])->middleware('throttle:10,1')->name('public.events.reminders.store');
Route::get('l/{lodge:slug}/events/{occurrence}', [PublicEventController::class, 'show'])->name('public.events.show');
Route::get('l/{lodge:slug}/{pageSlug}', [PublicWebsiteController::class, 'page'])->name('public.website.page');
Route::middleware(['auth', 'verified', 'approved', 'admin-2fa'])->group(function () {
    Route::resource('platform/lodges', LodgeController::class)->except(['show', 'destroy'])->names('platform.lodges')->middleware('platform-admin');
    Route::get('platform/accounts', [AccountController::class, 'index'])->name('platform.accounts.index')->middleware('platform-admin');
    Route::delete('platform/accounts/{user}', [AccountController::class, 'destroy'])->name('platform.accounts.destroy')->middleware('platform-admin');
    Route::get('platform/event-categories', [PlatformEventCategoryController::class, 'index'])->name('platform.event-categories.index')->middleware('platform-admin');
    Route::post('platform/event-categories', [PlatformEventCategoryController::class, 'store'])->name('platform.event-categories.store')->middleware('platform-admin');
    Route::put('platform/event-categories/{eventCategory}', [PlatformEventCategoryController::class, 'update'])->name('platform.event-categories.update')->middleware('platform-admin');
    Route::post('platform/lodges/{lodge}/admins', [LodgeController::class, 'assignAdmin'])->name('platform.lodges.admins')->middleware('platform-admin');
    Route::put('platform/lodges/{lodge}/features', [LodgeController::class, 'features'])->name('platform.lodges.features')->middleware('platform-admin');
    Route::get('platform/people/merge', [PersonMergeController::class, 'create'])->name('platform.people.merge.create')->middleware('platform-admin');
    Route::post('platform/people/merge', [PersonMergeController::class, 'store'])->name('platform.people.merge.store')->middleware('platform-admin');
    Route::get('registrations', [RegistrationReviewController::class, 'index'])->name('registrations.index');
    Route::patch('registrations/{user}', [RegistrationReviewController::class, 'decide'])->name('registrations.decide');
    Route::get('lodges/{lodge}/settings', [LodgeSettingsController::class, 'edit'])->name('lodges.settings.edit');
    Route::put('lodges/{lodge}/settings', [LodgeSettingsController::class, 'update'])->name('lodges.settings.update');
    Route::get('lodges/{lodge}/event-categories', [EventCategoryController::class, 'edit'])->name('lodges.event-categories.edit');
    Route::put('lodges/{lodge}/event-categories', [EventCategoryController::class, 'update'])->name('lodges.event-categories.update');
    Route::get('lodges/{lodge}/events', [EventController::class, 'index'])->name('lodges.events.index');
    Route::get('lodges/{lodge}/events/create', [EventController::class, 'create'])->name('lodges.events.create');
    Route::post('lodges/{lodge}/events', [EventController::class, 'store'])->name('lodges.events.store');
    Route::get('lodges/{lodge}/events/{event}/edit', [EventController::class, 'edit'])->name('lodges.events.edit');
    Route::put('lodges/{lodge}/events/{event}', [EventController::class, 'update'])->name('lodges.events.update');
    Route::post('lodges/{lodge}/events/{event}/publish', [EventController::class, 'publish'])->name('lodges.events.publish');
    Route::post('lodges/{lodge}/events/{event}/cancel', [EventController::class, 'cancel'])->name('lodges.events.cancel');
    Route::post('lodges/{lodge}/events/{event}/archive', [EventController::class, 'archive'])->name('lodges.events.archive');
    Route::get('lodges/{lodge}/events/{event}/occurrences', [EventOccurrenceController::class, 'index'])->name('lodges.events.occurrences.index');
    Route::put('lodges/{lodge}/events/{event}/occurrences/{occurrence}', [EventOccurrenceController::class, 'update'])->name('lodges.events.occurrences.update');
    Route::post('lodges/{lodge}/events/{event}/occurrences/{occurrence}/cancel', [EventOccurrenceController::class, 'cancel'])->name('lodges.events.occurrences.cancel');
    Route::post('lodges/{lodge}/events/{event}/occurrences/{occurrence}/restore', [EventOccurrenceController::class, 'restore'])->name('lodges.events.occurrences.restore');
    Route::post('lodges/{lodge}/events/{event}/reminder-deliveries/{delivery}/retry', [EventReminderDeliveryController::class, 'retry'])->name('lodges.events.reminder-deliveries.retry');
    Route::post('lodges/{lodge}/events/{event}/reminder-rules', [EventReminderRuleController::class, 'store'])->name('lodges.events.reminder-rules.store');
    Route::delete('lodges/{lodge}/events/{event}/reminder-rules/{rule}', [EventReminderRuleController::class, 'destroy'])->name('lodges.events.reminder-rules.destroy');
    Route::get('lodges/{lodge}/events/{event}/occurrences/{occurrence}/reservations', [EventReservationController::class, 'index'])->name('lodges.events.occurrences.reservations.index');
    Route::post('lodges/{lodge}/events/{event}/volunteer-positions', [EventVolunteerPositionController::class, 'store'])->name('lodges.events.volunteer-positions.store');
    Route::put('lodges/{lodge}/events/{event}/volunteer-positions/{position}', [EventVolunteerPositionController::class, 'update'])->name('lodges.events.volunteer-positions.update');
    Route::patch('lodges/{lodge}/events/{event}/volunteer-positions/{position}/deactivate', [EventVolunteerPositionController::class, 'deactivate'])->name('lodges.events.volunteer-positions.deactivate');
    Route::delete('lodges/{lodge}/events/{event}/volunteer-positions/{position}', [EventVolunteerPositionController::class, 'destroy'])->name('lodges.events.volunteer-positions.destroy');
    Route::get('lodges/{lodge}/events/{event}/occurrences/{occurrence}/volunteers', [EventVolunteerController::class, 'index'])->name('lodges.events.occurrences.volunteers.index');
    Route::post('lodges/{lodge}/events/{event}/occurrences/{occurrence}/volunteers', [EventVolunteerController::class, 'store'])->name('lodges.events.occurrences.volunteers.store');
    Route::patch('lodges/{lodge}/events/{event}/occurrences/{occurrence}/volunteers/{commitment}/remove', [EventVolunteerController::class, 'remove'])->name('lodges.events.occurrences.volunteers.remove');
    Route::post('lodges/{lodge}/events/{event}/occurrences/{occurrence}/reservations/{reservation}/cancel', [EventReservationController::class, 'cancel'])->name('lodges.events.occurrences.reservations.cancel');
    Route::post('lodges/{lodge}/events/{event}/reservation-fields', [EventReservationFieldController::class, 'store'])->name('lodges.events.reservation-fields.store');
    Route::delete('lodges/{lodge}/events/{event}/reservation-fields/{field}', [EventReservationFieldController::class, 'destroy'])->name('lodges.events.reservation-fields.destroy');
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
