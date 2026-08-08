<?php

use App\Http\Controllers\CampaignController;
use App\Http\Controllers\CooperativeController;
use App\Http\Controllers\CropController;
use App\Http\Controllers\CropCycleController;
use App\Http\Controllers\FarmerController;
use App\Http\Controllers\PlotController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VarietyController;
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

        // Référentiel commun des cultures et des variétés.
        Route::get('crops', [CropController::class, 'index'])->name('crops.index');
        Route::post('crops', [CropController::class, 'store'])->name('crops.store');
        Route::patch('crops/{crop}', [CropController::class, 'update'])->name('crops.update');
        Route::post('varieties', [VarietyController::class, 'store'])->name('varieties.store');
        Route::patch('varieties/{variety}', [VarietyController::class, 'update'])
            ->name('varieties.update');
    });

    Route::middleware('role:super_admin,coop_admin')->group(function () {
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::post('users', [UserController::class, 'store'])->name('users.store');
        Route::patch('users/{user}', [UserController::class, 'update'])->name('users.update');
    });

    Route::middleware('role:coop_admin')->group(function () {
        Route::post('campaigns', [CampaignController::class, 'store'])->name('campaigns.store');
        Route::patch('campaigns/{campaign}', [CampaignController::class, 'update'])
            ->name('campaigns.update');
    });

    Route::middleware('role:coop_admin,technician')->group(function () {
        Route::resource('farmers', FarmerController::class)->except(['destroy']);
        Route::resource('plots', PlotController::class)->except(['destroy']);

        // Les techniciens lisent les campagnes pour ouvrir un cycle ;
        // seul l'administrateur de coopérative les crée et les modifie.
        Route::get('campaigns', [CampaignController::class, 'index'])->name('campaigns.index');

        Route::get('cycles', [CropCycleController::class, 'index'])->name('cycles.index');
        Route::get('cycles/create', [CropCycleController::class, 'create'])->name('cycles.create');
        Route::post('cycles', [CropCycleController::class, 'store'])->name('cycles.store');
        Route::get('cycles/{cycle}', [CropCycleController::class, 'show'])->name('cycles.show');
        Route::post('cycles/{cycle}/cloture', [CropCycleController::class, 'close'])
            ->name('cycles.close');
        Route::post('cycles/{cycle}/abandon', [CropCycleController::class, 'abandon'])
            ->name('cycles.abandon');
    });
});

require __DIR__.'/settings.php';
