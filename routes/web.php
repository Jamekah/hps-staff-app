<?php

use App\Livewire\Users\ManageUsers;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('auth')->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::view('profile', 'profile')->name('profile');

    Route::get('users', ManageUsers::class)
        ->middleware('can:manage-users')
        ->name('users.index');
});

require __DIR__.'/auth.php';
