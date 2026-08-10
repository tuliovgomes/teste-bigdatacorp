<?php

use App\Http\Controllers\DashBoardController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\ImportController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashBoardController::class, 'index'])->name('dashboard');

    Route::post('/import', [ImportController::class, 'index'])->name('import');
    Route::get('/export', [ExportController::class, 'index'])->name('export');
});

require __DIR__.'/settings.php';
