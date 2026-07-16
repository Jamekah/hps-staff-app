<?php

use App\Http\Controllers\DocumentDownloadController;
use App\Livewire\Announcements\AnnouncementsPage;
use App\Livewire\Calendar\EventsCalendar;
use App\Livewire\Documents\SharedFolder;
use App\Livewire\Gym\GymSchedulePage;
use App\Livewire\Users\ManageUsers;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/calendar');

Route::middleware('auth')->group(function () {
    Route::get('calendar', EventsCalendar::class)->name('calendar');

    // Legacy Breeze route name; the calendar is the real landing page.
    Route::redirect('dashboard', 'calendar')->name('dashboard');

    Route::get('gym', GymSchedulePage::class)->name('gym');

    Route::get('announcements', AnnouncementsPage::class)->name('announcements');

    Route::get('files', SharedFolder::class)->name('documents.index');

    Route::get('documents/{document}/download', DocumentDownloadController::class)
        ->name('documents.download');

    Route::view('profile', 'profile')->name('profile');

    Route::get('users', ManageUsers::class)
        ->middleware('can:manage-users')
        ->name('users.index');
});

require __DIR__.'/auth.php';
