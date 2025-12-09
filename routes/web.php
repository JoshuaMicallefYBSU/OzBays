<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TestController;
use App\Http\Controllers\PagesController;
use App\Http\Controllers\MapController;


Route::get('/', [PagesController::class, 'Home'])->name('home');

Route::get('/map', [MapController::class, 'index']);

Route::get('/test/vatsim-api', [TestController::class, 'Job'])->name('vatsimapi');;