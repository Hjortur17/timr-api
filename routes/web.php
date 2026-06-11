<?php

use App\Http\Controllers\Auth\LoginLinkController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// One-time, expiring login link emailed by the dashboard-handoff flow.
Route::get('/login-link/{user}', LoginLinkController::class)
    ->name('auth.login-link')
    ->middleware('signed');
