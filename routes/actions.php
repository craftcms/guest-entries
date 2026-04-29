<?php

use CraftCms\GuestEntries\Http\Controllers\CreateGuestEntryController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:guest-entries'])->group(function () {
    Route::post('guest-entries/save', CreateGuestEntryController::class);
});
