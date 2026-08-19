<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\PortalController;
use App\Http\Controllers\Web\NotificationController;
use App\Http\Controllers\Web\PlatformSettingsController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SubscriptionController;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});
Route::get('/subscriptions/paystack/callback', [SubscriptionController::class, 'callback'])->name('subscriptions.callback');
Route::post('/webhooks/paystack', [SubscriptionController::class, 'webhook'])->name('webhooks.paystack');

Route::get('/dashboard', DashboardController::class)->middleware(['auth', 'subscription.ready', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/subscriptions/{order}/checkout', [SubscriptionController::class, 'checkout'])->name('subscriptions.checkout');
});

Route::middleware(['auth', 'subscription.ready'])->group(function () {
    Route::get('/portal/{section}', [PortalController::class, 'index'])->whereIn('section', ['organizations','people','visitors','invitations','entry-exit','students','pickup','reports','audit-logs','vts','pbs'])->name('portal.index');
    Route::post('/portal/vts/vehicles', [PortalController::class, 'storeVehicle'])->name('portal.vehicles.store');
    Route::post('/portal/organizations', [PortalController::class, 'storeOrganization'])->name('portal.organizations.store');
    Route::post('/portal/people', [PortalController::class, 'storePerson'])->name('portal.people.store');
    Route::post('/portal/visitors', [PortalController::class, 'storeVisitor'])->name('portal.visitors.store');
    Route::post('/portal/invitations', [PortalController::class, 'storeInvitation'])->name('portal.invitations.store');
    Route::post('/portal/students', [PortalController::class, 'storeStudent'])->name('portal.students.store');
    Route::post('/portal/pickup', [PortalController::class, 'storePickup'])->name('portal.pickup.store');
    Route::post('/portal/entry-exit/verify', [PortalController::class, 'verifyAccess'])->name('portal.access.verify');
    Route::post('/portal/pbs/incidents', [PortalController::class, 'triggerPanic'])->name('portal.panic.store');
    Route::patch('/portal/pbs/incidents/{incident}', [PortalController::class, 'updatePanic'])->name('portal.panic.update');
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::put('/platform/settings/mapbox', [PlatformSettingsController::class, 'updateMapbox'])->name('platform.settings.mapbox');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
