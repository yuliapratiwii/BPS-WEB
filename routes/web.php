<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DatasetController;
use App\Http\Controllers\DinamisController;

Route::get('/', [DashboardController::class, 'welcome'])->name('home');
Route::get('/publikasi/{id}', [DashboardController::class, 'show'])->name('publications.show');
Route::get('/press-release/{id}', [DashboardController::class, 'showPressRelease'])->name('pressreleases.show');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
});
Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');


Route::get('/dataset', [DatasetController::class, 'index'])->name('dataset.index');
Route::get('/dataset/{id}', [DatasetController::class, 'show'])->name('dataset.show');

Route::prefix('data-dinamis')->name('dinamis.')->group(function () {
    Route::get('/', [DinamisController::class, 'index'])->name('index');
    Route::get('/subjects', [DinamisController::class, 'subjects'])->name('subjects');
    Route::get('/variables', [DinamisController::class, 'variables'])->name('variables');
    Route::get('/filter-options', [DinamisController::class, 'filterOptions'])->name('filter-options');
    Route::get('/query', [DinamisController::class, 'runQuery'])->name('query');
    Route::get('/export', [DinamisController::class, 'export'])->name('export');
});
