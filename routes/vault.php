<?php

use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\Vault\ExportController;
use App\Http\Controllers\Vault\ImportController;
use App\Http\Controllers\Vault\ItemController;
use App\Http\Controllers\Vault\VaultController;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('vault', [ItemController::class, 'index'])->name('vault.index');

    Route::get('vault/import', [ImportController::class, 'create'])->name('vault.import.create');
    Route::post('vault/import/preview', [ImportController::class, 'preview'])->name('vault.import.preview');
    Route::post('vault/import', [ImportController::class, 'store'])->name('vault.import.store');
    Route::delete('vault/import', [ImportController::class, 'cancel'])->name('vault.import.cancel');

    Route::get('vault/export', [ExportController::class, 'download'])
        ->middleware(RequirePassword::class)
        ->name('vault.export');

    Route::post('items', [ItemController::class, 'store'])->name('items.store');
    Route::put('items/{item}', [ItemController::class, 'update'])->name('items.update');
    Route::delete('items/{item}', [ItemController::class, 'destroy'])->name('items.destroy');
    Route::get('items/{item}/secrets', [ItemController::class, 'secrets'])->name('items.secrets');

    Route::post('vaults', [VaultController::class, 'store'])->name('vaults.store');
    Route::put('vaults/{vault}', [VaultController::class, 'update'])->name('vaults.update');

    Route::post('push/subscribe', [PushSubscriptionController::class, 'store'])->name('push.subscribe');
    Route::delete('push/subscribe', [PushSubscriptionController::class, 'destroy'])->name('push.unsubscribe');
});
