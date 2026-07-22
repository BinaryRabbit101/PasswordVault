<?php

use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::redirect('dashboard', '/vault')->name('dashboard');
});

require __DIR__.'/vault.php';
require __DIR__.'/settings.php';
