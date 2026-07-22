<?php

use App\Http\Controllers\Api\LookupController;
use Illuminate\Support\Facades\Route;

// Token-authenticated lookup for the iOS Shortcut / widget (see docs/ios-shortcut.md).
Route::get('lookup', LookupController::class)
    ->middleware('throttle:30,1')
    ->name('api.lookup');
