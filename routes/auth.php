<?php

use App\Http\Controllers\Authentication\AuthenticationController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthenticationController::class, 'store'])
    ->middleware('guest')
    ->name('login');