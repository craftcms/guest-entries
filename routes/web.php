<?php

use CraftCms\GuestEntries\Http\Controllers\CreateGuestEntryController;
use CraftCms\GuestEntries\Plugin;
use CraftCms\GuestEntries\Settings;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:guest-entries'])->group(function () {
    /** @var Settings $settings */
    $settings = Plugin::getInstance()->getSettings();

    Route::post($settings->endpoint, CreateGuestEntryController::class);
});
