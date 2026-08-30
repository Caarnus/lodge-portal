<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DirectoryController;
use App\Http\Controllers\EventCategoryController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\EventOccurrenceController;
use App\Http\Controllers\EventReminderDeliveryController;
use App\Http\Controllers\EventReminderRuleController;
use App\Http\Controllers\EventReservationController;
use App\Http\Controllers\EventReservationFieldController;
use App\Http\Controllers\EventVolunteerController;
use App\Http\Controllers\EventVolunteerPositionController;
use App\Http\Controllers\EventVolunteerReminderDeliveryController;
use App\Http\Controllers\FamilyNewsletterSubscriptionController;
use App\Http\Controllers\GalleryController;
use App\Http\Controllers\LodgeCommunicationController;
use App\Http\Controllers\LodgeCommunicationSettingController;
use App\Http\Controllers\LodgeRoleController;
use App\Http\Controllers\LodgeRitualController;
use App\Http\Controllers\LodgeSettingsController;
use App\Http\Controllers\MediaAssetController;
use App\Http\Controllers\MemberNewsletterController;
use App\Http\Controllers\MembershipCommunicationPreferenceController;
use App\Http\Controllers\MembershipController;
use App\Http\Controllers\NewsletterController;
use App\Http\Controllers\NewsletterDocumentController;
use App\Http\Controllers\NewsletterPostalController;
use App\Http\Controllers\OfficerController;
use App\Http\Controllers\PersonAccountController;
use App\Http\Controllers\PersonController;
use App\Http\Controllers\PersonPhotoController;
use App\Http\Controllers\PersonRelationshipController;
use App\Http\Controllers\Platform\AccountController;
use App\Http\Controllers\Platform\EventCategoryController as PlatformEventCategoryController;
use App\Http\Controllers\Platform\LodgeController;
use App\Http\Controllers\Platform\LodgeGroupController;
use App\Http\Controllers\Platform\LodgeGroupTypeController;
use App\Http\Controllers\Platform\PersonMergeController;
use App\Http\Controllers\Platform\RitualReferenceController;
use App\Http\Controllers\PublicCommunicationUnsubscribeController;
use App\Http\Controllers\PublicContactFormController;
use App\Http\Controllers\PublicEventCalendarController;
use App\Http\Controllers\PublicEventController;
use App\Http\Controllers\PublicEventReminderController;
use App\Http\Controllers\PublicEventReservationController;
use App\Http\Controllers\PublicEventVolunteerController;
use App\Http\Controllers\PublicFamilyNewsletterRequestController;
use App\Http\Controllers\PublicReminderUnsubscribeController;
use App\Http\Controllers\PublicReservationCancellationController;
use App\Http\Controllers\PublicWebsiteController;
use App\Http\Controllers\RegistrationReviewController;
use App\Http\Controllers\RitualController;
use App\Http\Controllers\RitualAssistanceController;
use App\Http\Controllers\WebsiteController;
use App\Http\Controllers\WebsiteSectionController;
use App\Models\Lodge;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'lodges' => Lodge::where('status', 'active')
            ->orderBy('name')
            ->get(['name', 'slug', 'number', 'city', 'state', 'logo_path', 'seal_path']),
    ]);
})->name('home');

Route::get('dashboard', DashboardController::class)->middleware(['auth', 'verified', 'approved'])->name('dashboard');
Route::middleware(['auth', 'verified', 'approved'])->group(function () {
    Route::get('ritual', [RitualController::class, 'index'])->name('ritual.index');
    Route::put('ritual/settings', [RitualController::class, 'updateSettings'])->name('ritual.settings.update');
    Route::put('ritual/parts/{ritualPart}', [RitualController::class, 'updatePart'])->name('ritual.parts.update');
    Route::put('ritual/availability', [RitualController::class, 'updateAvailability'])->name('ritual.availability.update');
});

Route::middleware(['auth', 'verified', 'approved'])->prefix('lodges/{lodge}')->name('lodges.directory.')->group(function () {
    Route::get('directory', [DirectoryController::class, 'index'])->middleware('throttle:60,1')->name('index');
    Route::get('directory/{person}/photo', [DirectoryController::class, 'photo'])->name('photo');
    Route::get('directory/{person}', [DirectoryController::class, 'show'])->name('show');
});
Route::middleware(['auth', 'verified', 'approved'])->prefix('lodges/{lodge}')->name('lodges.ritual-assistance.')->group(function () {
    Route::get('ritual-assistance', [RitualAssistanceController::class, 'index'])->middleware('throttle:60,1')->name('index');
    Route::get('ritual-assistance/{person}', [RitualAssistanceController::class, 'show'])->name('show');
});

Route::middleware(['auth', 'verified', 'approved'])->prefix('lodges/{lodge}')->name('lodges.newsletters.')->group(function () {
    Route::get('newsletters', [MemberNewsletterController::class, 'index'])->name('archive');
    Route::get('newsletters/{issue:slug}', [MemberNewsletterController::class, 'show'])->withoutScopedBindings()->where('issue', '^(?!manage$)[A-Za-z0-9-]+$')->name('show');
    Route::get('newsletters/{issue:slug}/cover', [MemberNewsletterController::class, 'cover'])->withoutScopedBindings()->name('cover');
    Route::get('newsletters/{issue:slug}/document', [MemberNewsletterController::class, 'document'])->withoutScopedBindings()->name('document');
});
Route::middleware(['auth', 'verified', 'approved'])->prefix('lodges/{lodge}')->name('lodges.communications.')->group(function () {
    Route::get('communications', [LodgeCommunicationController::class, 'archive'])->name('archive');
    Route::get('communications/{communication}', [LodgeCommunicationController::class, 'show'])->whereNumber('communication')->name('show');
});

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
Route::get('l/{lodge:slug}/galleries', [GalleryController::class, 'index'])->name('public.galleries.index');
Route::get('l/{lodge:slug}/galleries/{album:slug}', [GalleryController::class, 'show'])->withoutScopedBindings()->name('public.galleries.show');
Route::get('l/{lodge:slug}/galleries/{album:slug}/photos/{photo}', [GalleryController::class, 'photo'])->withoutScopedBindings()->name('public.galleries.photo');
Route::get('l/{lodge:slug}/newsletters/request', [PublicFamilyNewsletterRequestController::class, 'create'])->name('public.newsletters.request.create');
Route::post('l/{lodge:slug}/newsletters/request', [PublicFamilyNewsletterRequestController::class, 'store'])->middleware('throttle:10,1')->name('public.newsletters.request.store');
Route::get('l/{lodge:slug}/newsletters/request/verify/{token}', [PublicFamilyNewsletterRequestController::class, 'verify'])->middleware('throttle:10,1')->name('public.newsletters.request.verify.show');
Route::post('l/{lodge:slug}/newsletters/request/verify/{token}', [PublicFamilyNewsletterRequestController::class, 'confirm'])->middleware('throttle:10,1')->name('public.newsletters.request.verify');
Route::get('l/{lodge:slug}/newsletters/{issue:slug}', [PublicWebsiteController::class, 'newsletter'])->middleware(['auth', 'verified', 'approved'])->withoutScopedBindings()->name('public.newsletters.show');
Route::get('l/{lodge:slug}/communications/unsubscribe/{token}', [PublicCommunicationUnsubscribeController::class, 'show'])->middleware('throttle:10,1')->name('public.communications.unsubscribe.show');
Route::post('l/{lodge:slug}/communications/unsubscribe/{token}', [PublicCommunicationUnsubscribeController::class, 'store'])->middleware('throttle:10,1')->name('public.communications.unsubscribe');
Route::post('l/{lodge:slug}/contact', [PublicContactFormController::class, 'store'])->middleware('throttle:10,1')->name('public.contact.store');
Route::get('l/{lodge:slug}/{pageSlug}', [PublicWebsiteController::class, 'page'])->name('public.website.page');
Route::middleware(['auth', 'verified', 'approved', 'admin-2fa'])->group(function () {
    Route::prefix('lodges/{lodge}/communications/manage')->name('lodges.communications.')->group(function () {
        Route::get('/', [LodgeCommunicationController::class, 'index'])->name('index');
        Route::post('/', [LodgeCommunicationController::class, 'store'])->name('store');
        Route::put('{communication}', [LodgeCommunicationController::class, 'update'])->name('update');
        Route::delete('{communication}', [LodgeCommunicationController::class, 'destroy'])->name('destroy');
        Route::post('{communication}/send', [LodgeCommunicationController::class, 'send'])->name('send');
        Route::post('{communication}/duplicate', [LodgeCommunicationController::class, 'duplicate'])->name('duplicate');
    });
    Route::prefix('lodges/{lodge}/newsletter-recipients')->name('lodges.newsletter-recipients.')->group(function () {
        Route::get('/', [FamilyNewsletterSubscriptionController::class, 'index'])->name('index');
        Route::post('requests/{familyRequest}/approve', [FamilyNewsletterSubscriptionController::class, 'approve'])->name('requests.approve');
        Route::post('requests/{familyRequest}/reject', [FamilyNewsletterSubscriptionController::class, 'reject'])->name('requests.reject');
        Route::put('subscriptions/{subscription}', [FamilyNewsletterSubscriptionController::class, 'update'])->name('subscriptions.update');
    });
    Route::prefix('lodges/{lodge}/galleries/manage')->name('lodges.galleries.')->group(function () {
        Route::get('/', [GalleryController::class, 'manage'])->name('manage');
        Route::post('/', [GalleryController::class, 'store'])->name('store');
        Route::get('{album}/edit', [GalleryController::class, 'edit'])->name('edit');
        Route::put('{album}', [GalleryController::class, 'update'])->name('update');
        Route::post('{album}/photos', [GalleryController::class, 'addPhoto'])->name('photos.store');
        Route::put('{album}/photos/{photo}', [GalleryController::class, 'updatePhoto'])->name('photos.update');
        Route::delete('{album}/photos/{photo}', [GalleryController::class, 'removePhoto'])->name('photos.destroy');
        Route::post('{album}/publish', [GalleryController::class, 'publish'])->name('publish');
        Route::post('{album}/unpublish', [GalleryController::class, 'unpublish'])->name('unpublish');
        Route::delete('{album}', [GalleryController::class, 'destroy'])->name('destroy');
        Route::post('deleted/{albumId}/restore', [GalleryController::class, 'restore'])->name('restore');
    });
    Route::prefix('lodges/{lodge}/newsletters/manage')->name('lodges.newsletters.')->group(function () {
        Route::get('/', [NewsletterController::class, 'index'])->name('index');
        Route::post('/', [NewsletterController::class, 'store'])->name('store');
        Route::get('{issue}/edit', [NewsletterController::class, 'edit'])->name('edit');
        Route::put('{issue}', [NewsletterController::class, 'update'])->name('update');
        Route::get('{issue}/preview', [NewsletterController::class, 'preview'])->name('preview');
        Route::post('{issue}/publish', [NewsletterController::class, 'publish'])->name('publish');
        Route::post('{issue}/unpublish', [NewsletterController::class, 'unpublish'])->name('unpublish');
        Route::post('{issue}/distribute', [NewsletterController::class, 'distribute'])->name('distribute');
        Route::get('{issue}/postal.csv', [NewsletterPostalController::class, 'export'])->name('postal.export');
        Route::post('{issue}/postal/mailed', [NewsletterPostalController::class, 'mailed'])->name('postal.mailed');
        Route::get('{issue}/print', [NewsletterPostalController::class, 'print'])->name('print');
        Route::delete('{issue}', [NewsletterController::class, 'destroy'])->name('destroy');
        Route::post('deleted/{issueId}/restore', [NewsletterController::class, 'restore'])->name('restore');
        Route::post('documents', [NewsletterDocumentController::class, 'store'])->name('documents.store');
        Route::delete('documents/{document}', [NewsletterDocumentController::class, 'destroy'])->name('documents.destroy');
    });
    Route::resource('platform/lodges', LodgeController::class)->except(['show', 'destroy'])->names('platform.lodges')->middleware('platform-admin');
    Route::get('platform/accounts', [AccountController::class, 'index'])->name('platform.accounts.index')->middleware('platform-admin');
    Route::delete('platform/accounts/{user}', [AccountController::class, 'destroy'])->name('platform.accounts.destroy')->middleware('platform-admin');
    Route::get('platform/event-categories', [PlatformEventCategoryController::class, 'index'])->name('platform.event-categories.index')->middleware('platform-admin');
    Route::post('platform/event-categories', [PlatformEventCategoryController::class, 'store'])->name('platform.event-categories.store')->middleware('platform-admin');
    Route::put('platform/event-categories/{eventCategory}', [PlatformEventCategoryController::class, 'update'])->name('platform.event-categories.update')->middleware('platform-admin');
    Route::get('platform/lodge-groups', [LodgeGroupController::class, 'index'])->name('platform.lodge-groups.index')->middleware('platform-admin');
    Route::post('platform/lodge-groups', [LodgeGroupController::class, 'store'])->name('platform.lodge-groups.store')->middleware('platform-admin');
    Route::put('platform/lodge-groups/{lodgeGroup}', [LodgeGroupController::class, 'update'])->name('platform.lodge-groups.update')->middleware('platform-admin');
    Route::patch('platform/lodge-groups/{lodgeGroup}/archive', [LodgeGroupController::class, 'archive'])->name('platform.lodge-groups.archive')->middleware('platform-admin');
    Route::patch('platform/lodge-groups/{lodgeGroup}/restore', [LodgeGroupController::class, 'restore'])->name('platform.lodge-groups.restore')->middleware('platform-admin');
    Route::put('platform/lodge-groups/{lodgeGroup}/lodges', [LodgeGroupController::class, 'synchronizeLodges'])->name('platform.lodge-groups.lodges.update')->middleware('platform-admin');
    Route::get('platform/lodge-group-types', [LodgeGroupTypeController::class, 'index'])->name('platform.lodge-group-types.index')->middleware('platform-admin');
    Route::post('platform/lodge-group-types', [LodgeGroupTypeController::class, 'store'])->name('platform.lodge-group-types.store')->middleware('platform-admin');
    Route::put('platform/lodge-group-types/{lodgeGroupType}', [LodgeGroupTypeController::class, 'update'])->name('platform.lodge-group-types.update')->middleware('platform-admin');
    Route::patch('platform/lodge-group-types/{lodgeGroupType}/status', [LodgeGroupTypeController::class, 'status'])->name('platform.lodge-group-types.status')->middleware('platform-admin');
    Route::delete('platform/lodge-group-types/{lodgeGroupType}', [LodgeGroupTypeController::class, 'destroy'])->name('platform.lodge-group-types.destroy')->middleware('platform-admin');
    Route::get('platform/ritual-reference', [RitualReferenceController::class, 'index'])->name('platform.ritual-reference.index')->middleware('platform-admin');
    Route::post('platform/ritual-categories', [RitualReferenceController::class, 'storeCategory'])->name('platform.ritual-categories.store')->middleware('platform-admin');
    Route::put('platform/ritual-categories/{ritualCategory}', [RitualReferenceController::class, 'updateCategory'])->name('platform.ritual-categories.update')->middleware('platform-admin');
    Route::post('platform/ritual-parts', [RitualReferenceController::class, 'storePart'])->name('platform.ritual-parts.store')->middleware('platform-admin');
    Route::put('platform/ritual-parts/{ritualPart}', [RitualReferenceController::class, 'updatePart'])->name('platform.ritual-parts.update')->middleware('platform-admin');
    Route::post('platform/ritual-levels', [RitualReferenceController::class, 'storeLevel'])->name('platform.ritual-levels.store')->middleware('platform-admin');
    Route::put('platform/ritual-levels/{ritualProgramLevel}', [RitualReferenceController::class, 'updateLevel'])->name('platform.ritual-levels.update')->middleware('platform-admin');
    Route::post('platform/lodges/{lodge}/admins', [LodgeController::class, 'assignAdmin'])->name('platform.lodges.admins')->middleware('platform-admin');
    Route::put('platform/lodges/{lodge}/features', [LodgeController::class, 'features'])->name('platform.lodges.features')->middleware('platform-admin');
    Route::get('platform/people/merge', [PersonMergeController::class, 'create'])->name('platform.people.merge.create')->middleware('platform-admin');
    Route::post('platform/people/merge', [PersonMergeController::class, 'store'])->name('platform.people.merge.store')->middleware('platform-admin');
    Route::get('registrations', [RegistrationReviewController::class, 'index'])->name('registrations.index');
    Route::patch('registrations/{user}', [RegistrationReviewController::class, 'decide'])->name('registrations.decide');
    Route::get('lodges/{lodge}/settings', [LodgeSettingsController::class, 'edit'])->name('lodges.settings.edit');
    Route::put('lodges/{lodge}/settings', [LodgeSettingsController::class, 'update'])->name('lodges.settings.update');
    Route::get('lodges/{lodge}/communication-settings', [LodgeCommunicationSettingController::class, 'edit'])->name('lodges.communication-settings.edit');
    Route::put('lodges/{lodge}/communication-settings', [LodgeCommunicationSettingController::class, 'update'])->name('lodges.communication-settings.update');
    Route::put('lodges/{lodge}/memberships/{membership}/communication-preference', [MembershipCommunicationPreferenceController::class, 'update'])->name('lodges.memberships.communication-preference.update');
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
    Route::post('lodges/{lodge}/events/{event}/occurrences/{occurrence}/volunteer-reminders/{delivery}/retry', [EventVolunteerReminderDeliveryController::class, 'retry'])->name('lodges.events.occurrences.volunteer-reminders.retry');
    Route::post('lodges/{lodge}/events/{event}/occurrences/{occurrence}/reservations/{reservation}/cancel', [EventReservationController::class, 'cancel'])->name('lodges.events.occurrences.reservations.cancel');
    Route::post('lodges/{lodge}/events/{event}/reservation-fields', [EventReservationFieldController::class, 'store'])->name('lodges.events.reservation-fields.store');
    Route::delete('lodges/{lodge}/events/{event}/reservation-fields/{field}', [EventReservationFieldController::class, 'destroy'])->name('lodges.events.reservation-fields.destroy');
    Route::post('lodges/{lodge}/activate', function (Request $r, Lodge $lodge) {
        abort_unless($r->user()->is_platform_admin || DB::table('lodge_user_roles')
            ->where('user_id', $r->user()->id)->where('lodge_id', $lodge->id)->exists(), 403);
        $r->user()->update(['current_lodge_id' => $lodge->id]);

        return back();
    })->name('lodges.activate');

    Route::resource('lodges.people', PersonController::class)->only(['index', 'store', 'update']);
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
    Route::get('lodges/{lodge}/ritual-management', [LodgeRitualController::class, 'index'])->name('lodges.ritual-management.index');
    Route::put('lodges/{lodge}/ritual-management/{membership}', [LodgeRitualController::class, 'update'])->name('lodges.ritual-management.update');
    Route::get('lodges/{lodge}/roles', [LodgeRoleController::class, 'index'])->name('lodges.roles.index');
    Route::get('lodges/{lodge}/role-assignments', [LodgeRoleController::class, 'assignments'])->name('lodges.roles.assignments');
    Route::post('lodges/{lodge}/roles', [LodgeRoleController::class, 'store'])->name('lodges.roles.store');
    Route::put('lodges/{lodge}/roles/{role}', [LodgeRoleController::class, 'update'])->name('lodges.roles.update');
    Route::post('lodges/{lodge}/role-assignments', [LodgeRoleController::class, 'assign'])->name('lodges.roles.assign');
    Route::delete('lodges/{lodge}/role-assignments', [LodgeRoleController::class, 'unassign'])->name('lodges.roles.unassign');

    Route::prefix('lodges/{lodge}/website')->name('lodges.website.')->group(function () {
        Route::get('/', [WebsiteController::class, 'index'])->name('index');
        Route::post('pages', [WebsiteController::class, 'store'])->name('pages.store');
        Route::put('pages/navigation', [WebsiteController::class, 'reorderNavigation'])->name('pages.navigation.reorder');
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
        Route::put('media/{media}', [MediaAssetController::class, 'update'])->name('media.update');
        Route::post('media/{media}/retry', [MediaAssetController::class, 'retry'])->name('media.retry');
        Route::get('media/{media}/original', [MediaAssetController::class, 'original'])->name('media.original');
        Route::delete('media/{media}', [MediaAssetController::class, 'destroy'])->name('media.destroy');
        Route::post('template', [WebsiteController::class, 'applyTemplate'])->name('template');
        Route::put('branding', [WebsiteController::class, 'branding'])->name('branding');
    });
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
