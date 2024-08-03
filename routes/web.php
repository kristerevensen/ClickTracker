<?php

use App\Http\Controllers\ClickTrackerController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/{linkToken}', [ClickTrackerController::class, 'index'])->name('track.index');
Route::get('/error/invalid-link', function () {
    return view('invalid-link');
})->name('invalid.link');


