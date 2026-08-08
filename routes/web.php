<?php

use App\Http\Controllers\CooperativeController;
use App\Http\Controllers\FarmerController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\EnsureCooperativeActive;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

Route::middleware(['auth', EnsureCooperativeActive::class])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    Route::middleware('role:super_admin')->group(function () {
        Route::get('cooperatives', [CooperativeController::class, 'index'])
            ->name('cooperatives.index');
        Route::post('cooperatives', [CooperativeController::class, 'store'])
            ->name('cooperatives.store');
        Route::patch('cooperatives/{cooperative}', [CooperativeController::class, 'update'])
            ->name('cooperatives.update');
        Route::put('cooperatives/{cooperative}/suspension', [CooperativeController::class, 'suspend'])
            ->name('cooperatives.suspend');
        Route::delete('cooperatives/{cooperative}/suspension', [CooperativeController::class, 'reactivate'])
            ->name('cooperatives.reactivate');
    });

    Route::middleware('role:super_admin,coop_admin')->group(function () {
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::post('users', [UserController::class, 'store'])->name('users.store');
        Route::patch('users/{user}', [UserController::class, 'update'])->name('users.update');
    });

    Route::middleware('role:coop_admin,technician')->group(function () {
        Route::resource('farmers', FarmerController::class)->except(['destroy']);
    });
});

require __DIR__.'/settings.php';
