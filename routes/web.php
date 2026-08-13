<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ImagesController;
use App\Http\Controllers\CoinsController;
use App\Http\Controllers\ArbitrageController;
use App\Http\Controllers\ArbitrageLogsController;
use App\Http\Controllers\UsersController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

// Auth

Route::get('login', [AuthenticatedSessionController::class, 'create'])
    ->name('login')
    ->middleware('guest');

Route::post('login', [AuthenticatedSessionController::class, 'store'])
    ->name('login.store')
    ->middleware('guest');

Route::delete('logout', [AuthenticatedSessionController::class, 'destroy'])
    ->name('logout');

// Dashboard

Route::get('/', [DashboardController::class, 'index'])
    ->name('dashboard')
    ->middleware('auth');

// Users

Route::get('users', [UsersController::class, 'index'])
    ->name('users')
    ->middleware('auth');

Route::get('users/create', [UsersController::class, 'create'])
    ->name('users.create')
    ->middleware('auth');

Route::post('users', [UsersController::class, 'store'])
    ->name('users.store')
    ->middleware('auth');

Route::get('users/{user}/edit', [UsersController::class, 'edit'])
    ->name('users.edit')
    ->middleware('auth');

Route::put('users/{user}', [UsersController::class, 'update'])
    ->name('users.update')
    ->middleware('auth');

Route::delete('users/{user}', [UsersController::class, 'destroy'])
    ->name('users.destroy')
    ->middleware('auth');

Route::put('users/{user}/restore', [UsersController::class, 'restore'])
    ->name('users.restore')
    ->middleware('auth');

// Coins

Route::get('coins', [CoinsController::class, 'index'])
    ->name('coins')
    ->middleware('auth');

Route::get('coins/create', [CoinsController::class, 'create'])
    ->name('coins.create')
    ->middleware('auth');

Route::post('coins', [CoinsController::class, 'store'])
    ->name('coins.store')
    ->middleware('auth');

Route::get('coins/{coin}/edit', [CoinsController::class, 'edit'])
    ->name('coins.edit')
    ->middleware('auth');

Route::put('coins/{coin}', [CoinsController::class, 'update'])
    ->name('coins.update')
    ->middleware('auth');

Route::delete('coins/{coin}', [CoinsController::class, 'destroy'])
    ->name('coins.destroy')
    ->middleware('auth');

Route::put('coins/{coin}/restore', [CoinsController::class, 'restore'])
    ->name('coins.restore')
    ->middleware('auth');

// Arbitrage

Route::get('arbitrages', [ArbitrageController::class, 'index'])
    ->name('arbitrages')
    ->middleware('auth');

Route::get('arbitrages/create', [ArbitrageController::class, 'create'])
    ->name('arbitrages.create')
    ->middleware('auth');

Route::post('arbitrages', [ArbitrageController::class, 'store'])
    ->name('arbitrages.store')
    ->middleware('auth');

Route::get('arbitrages/{arbitrage}/edit', [ArbitrageController::class, 'edit'])
    ->name('arbitrages.edit')
    ->middleware('auth');

    Route::put('arbitrages/{arbitrage}', [ArbitrageController::class, 'update'])
    ->name('arbitrages.update')
    ->middleware('auth');
    
// Arbitrage Logs

Route::get('arbitrage-logs', [ArbitrageLogsController::class, 'index'])
    ->name('arbitrage-logs.index')
    ->middleware('auth');

Route::post('arbitrage-logs/export', [ArbitrageLogsController::class, 'export'])
    ->name('arbitrage-logs.export')
    ->middleware('auth');

// Images

Route::get('/img/{path}', [ImagesController::class, 'show'])
    ->where('path', '.*')
    ->name('image');
