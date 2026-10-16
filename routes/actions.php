<?php

declare(strict_types=1);

use CraftCms\GuestEntries\Http\Controllers\SaveController;
use Illuminate\Support\Facades\Route;

// -> actions/guest-entries/save
Route::post('save', SaveController::class);
