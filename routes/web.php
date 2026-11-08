<?php

declare(strict_types=1);

use CraftCms\GuestEntries\Http\Controllers\CreateGuestEntryController;
use CraftCms\GuestEntries\Settings;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:guest-entries'])->group(function () {
    Route::post(app(Settings::class)->endpoint, CreateGuestEntryController::class);
});
