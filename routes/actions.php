<?php

declare(strict_types=1);

use CraftCms\GuestEntries\Http\Controllers\CreateGuestEntryController;
use Illuminate\Support\Facades\Route;

// Action routes are prefixed with the plugin handle, so this keeps the Craft 5 `actions/guest-entries/save` URL:
Route::middleware(['throttle:guest-entries'])->group(function () {
    Route::post('save', CreateGuestEntryController::class);
});
