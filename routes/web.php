<?php

use App\Http\Controllers\VideoController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/analyze', [VideoController::class, 'analyze'])->name('analyze')->middleware('throttle:10,1');
Route::post('/download', [VideoController::class, 'download'])->name('download')->middleware('throttle:5,1');
Route::get('/status/{jobId}', [VideoController::class, 'status'])->name('status');
