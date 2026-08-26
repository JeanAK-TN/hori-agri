<?php

use App\Http\Controllers\CampaignController;
use App\Http\Controllers\CropController;
use App\Http\Controllers\CropCycleController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExploitationController;
use App\Http\Controllers\FarmerController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\HarvestController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\OperationController;
use App\Http\Controllers\PlotController;
use App\Http\Controllers\RecommendationController;
use App\Http\Controllers\RuleController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VarietyController;
use App\Http\Middleware\EnsureExploitationActive;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', EnsureExploitationActive::class])->group(function () {
    // La racine mène chacun à son premier écran (SPEC-CORRECTION 9).
    Route::get('/', HomeController::class)->name('home');

    Route::middleware('role:super_admin')->group(function () {
        Route::get('exploitations', [ExploitationController::class, 'index'])
            ->name('exploitations.index');
        Route::post('exploitations', [ExploitationController::class, 'store'])
            ->name('exploitations.store');
        Route::patch('exploitations/{exploitation}', [ExploitationController::class, 'update'])
            ->name('exploitations.update');
        Route::put('exploitations/{exploitation}/suspension', [ExploitationController::class, 'suspend'])
            ->name('exploitations.suspend');
        Route::delete('exploitations/{exploitation}/suspension', [ExploitationController::class, 'reactivate'])
            ->name('exploitations.reactivate');

        // Référentiel commun des cultures et des variétés.
        Route::get('crops', [CropController::class, 'index'])->name('crops.index');
        Route::post('crops', [CropController::class, 'store'])->name('crops.store');
        Route::patch('crops/{crop}', [CropController::class, 'update'])->name('crops.update');
        Route::post('varieties', [VarietyController::class, 'store'])->name('varieties.store');
        Route::patch('varieties/{variety}', [VarietyController::class, 'update'])
            ->name('varieties.update');
    });

    Route::middleware('role:super_admin,exploitation_admin')->group(function () {
        // Base de règles, avec l'origine de chaque seuil (SPEC section 11).
        Route::get('regles', [RuleController::class, 'index'])->name('rules.index');
        Route::patch('regles/{rule}', [RuleController::class, 'update'])->name('rules.update');

        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::post('users', [UserController::class, 'store'])->name('users.store');
        Route::patch('users/{user}', [UserController::class, 'update'])->name('users.update');
    });

    Route::middleware('role:exploitation_admin')->group(function () {
        // Tableau de bord de l'exploitation et sa synthèse de campagne
        // (SPEC section 12).
        Route::get('tableau-de-bord', [DashboardController::class, 'index'])
            ->name('exploitation-dashboard.index');
        Route::get('tableau-de-bord/export', [DashboardController::class, 'export'])
            ->name('exploitation-dashboard.export');

        Route::post('campaigns', [CampaignController::class, 'store'])->name('campaigns.store');
        Route::patch('campaigns/{campaign}', [CampaignController::class, 'update'])
            ->name('campaigns.update');
    });

    Route::middleware('role:exploitation_admin,technician')->group(function () {
        Route::resource('farmers', FarmerController::class)->except(['destroy']);
        Route::resource('plots', PlotController::class)->except(['destroy']);

        // Les techniciens lisent les campagnes pour ouvrir un cycle ;
        // seul l'administrateur d'exploitation les crée et les modifie.
        Route::get('campaigns', [CampaignController::class, 'index'])->name('campaigns.index');

        Route::get('cycles', [CropCycleController::class, 'index'])->name('cycles.index');
        Route::get('cycles/create', [CropCycleController::class, 'create'])->name('cycles.create');
        Route::post('cycles', [CropCycleController::class, 'store'])->name('cycles.store');
        Route::get('cycles/{cycle}', [CropCycleController::class, 'show'])->name('cycles.show');
        Route::post('cycles/{cycle}/cloture', [CropCycleController::class, 'close'])
            ->name('cycles.close');
        Route::post('cycles/{cycle}/abandon', [CropCycleController::class, 'abandon'])
            ->name('cycles.abandon');

        // Dépôt d'un fichier et rapport d'import (SPEC section 11).
        Route::get('imports', [ImportController::class, 'index'])->name('imports.index');
        Route::post('imports', [ImportController::class, 'store'])->name('imports.store');
    });

    // La restitution : le technicien lit celles des producteurs qu'il
    // suit, seul le producteur concerné répond (SPEC sections 10.2 et
    // 11.2). L'administrateur d'exploitation passe par le tableau de
    // bord, pas par cet écran.
    Route::middleware('role:technician,farmer')->group(function () {
        Route::get('recommandations', [RecommendationController::class, 'index'])
            ->name('recommendations.index');
    });

    Route::middleware('role:farmer')->group(function () {
        Route::patch('recommandations/{recommendation}', [RecommendationController::class, 'update'])
            ->name('recommendations.update');
        Route::post('recommandations/{recommendation}/retour', [FeedbackController::class, 'store'])
            ->name('feedbacks.store');
    });

    // L'itinéraire technique est saisi par le technicien (SPEC section 5).
    // Les policies refusent aussi la saisie sur un cycle terminé.
    Route::middleware('role:technician')->group(function () {
        Route::post('cycles/{cycle}/operations', [OperationController::class, 'store'])
            ->name('operations.store');
        Route::patch('cycles/{cycle}/operations/{operation}', [OperationController::class, 'update'])
            ->name('operations.update');
        Route::delete('cycles/{cycle}/operations/{operation}', [OperationController::class, 'destroy'])
            ->name('operations.destroy');

        Route::post('cycles/{cycle}/harvests', [HarvestController::class, 'store'])
            ->name('harvests.store');
        Route::patch('cycles/{cycle}/harvests/{harvest}', [HarvestController::class, 'update'])
            ->name('harvests.update');
        Route::delete('cycles/{cycle}/harvests/{harvest}', [HarvestController::class, 'destroy'])
            ->name('harvests.destroy');
    });
});

require __DIR__.'/settings.php';
