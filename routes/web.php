<?php

use CraftCms\GuestEntries\Http\Controllers\CreateGuestEntryController;
use CraftCms\GuestEntries\Plugin;
use Illuminate\Support\Facades\Route;

/** @var CraftCms\GuestEntries\Settings $settings */
$settings = Plugin::getInstance()->getSettings();

Route::post($settings->endpoint, CreateGuestEntryController::class);
