<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GymController;

Route::post('/upload/xlsx', [GymController::class, 'uploadXlsx']);
Route::get('/exercises/today', [GymController::class, 'today']);
Route::post('/logs/bulk', [GymController::class, 'saveBulk']);