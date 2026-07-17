<?php

use App\Http\Controllers\DeviceTokenController;
use App\Http\Controllers\DocumentDownloadController;
use App\Livewire\Announcements\AnnouncementsPage;
use App\Livewire\Calendar\EventsCalendar;
use App\Livewire\Documents\SharedFolder;
use App\Livewire\Gym\GymSchedulePage;
use App\Livewire\Notifications\NotificationsPage;
use App\Livewire\Users\ManageUsers;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/calendar');

// Firebase messaging service worker — served from the web root (required for
// push scope) with its config injected from env, so nothing is hardcoded.
Route::get('firebase-messaging-sw.js', fn () => response()
    ->view('firebase-messaging-sw')
    ->header('Content-Type', 'application/javascript'))
    ->name('firebase.sw');

Route::middleware('auth')->group(function () {
    Route::get('calendar', EventsCalendar::class)->name('calendar');

    // Legacy Breeze route name; the calendar is the real landing page.
    Route::redirect('dashboard', 'calendar')->name('dashboard');

    Route::get('gym', GymSchedulePage::class)->name('gym');

    Route::get('announcements', AnnouncementsPage::class)->name('announcements');

    Route::get('files', SharedFolder::class)->name('documents.index');

    Route::get('documents/{document}/download', DocumentDownloadController::class)
        ->name('documents.download');

    Route::get('notifications', NotificationsPage::class)->name('notifications');

    Route::post('api/device-tokens', [DeviceTokenController::class, 'store'])
        ->name('device-tokens.store');
    Route::delete('api/device-tokens', [DeviceTokenController::class, 'destroy'])
        ->name('device-tokens.destroy');

    Route::view('profile', 'profile')->name('profile');

    Route::get('users', ManageUsers::class)
        ->middleware('can:manage-users')
        ->name('users.index');
});

require __DIR__.'/auth.php';
