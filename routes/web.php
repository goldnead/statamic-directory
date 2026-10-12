<?php

use Goldnead\Directory\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::prefix('directory/profiles')->name('directory.profiles.')->group(function (): void {
    Route::get('/', [ProfileController::class, 'index'])->name('index');
    Route::get('/{entryId}', [ProfileController::class, 'show'])->name('show');
    Route::patch('/{entryId}', [ProfileController::class, 'update'])->name('update');
});
