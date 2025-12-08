<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TestController;
use App\Http\Controllers\PagesController;


Route::get('/', [PagesController::class, 'Home'])->name('home');

Route::get('/test/vatsim-api', [TestController::class, 'Job'])->name('vatsimapi');;