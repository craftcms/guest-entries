<?php

use CraftCms\GuestEntries\Http\Controllers\SaveController;
use Illuminate\Support\Facades\Route;

// -> actions/guest-entries/save (the URL that front-end forms already post to)
Route::post('save', SaveController::class);
