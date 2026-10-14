<?php

use Goldnead\Directory\Http\Controllers\Cp\DirectoryController;
use Illuminate\Support\Facades\Route;

Route::prefix('directory')->name('directory.')->group(function (): void {
    Route::get('/', [DirectoryController::class, 'index'])->name('index');
    Route::get('rebuild-status', [DirectoryController::class, 'status'])->name('rebuild-status');
    Route::post('rebuild', [DirectoryController::class, 'rebuild'])->name('rebuild');
    Route::post('profiles/{id}/rebuild', [DirectoryController::class, 'rebuildProfile'])->name('profiles.rebuild')->whereNumber('id');
    Route::post('profiles/{id}/unbind', [DirectoryController::class, 'unbind'])->name('profiles.unbind')->whereNumber('id');
});
