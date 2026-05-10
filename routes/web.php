<?php

use App\Http\Controllers\GameHubController;
use App\Http\Controllers\LegacyAssetController;
use App\Http\Controllers\LegacyPhpController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PveAttackController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check() ? redirect()->route('game.hub') : redirect()->route('login');
});

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/welcome', [OnboardingController::class, 'show'])->name('onboarding.welcome');
    Route::post('/welcome/begin', [OnboardingController::class, 'begin'])->name('onboarding.begin');
    Route::post('/welcome/skip', [OnboardingController::class, 'skip'])->name('onboarding.skip');
    Route::post('/onboarding/complete', [OnboardingController::class, 'complete'])->name('onboarding.complete');
});

Route::get('/dashboard', function () {
    return redirect()->route('game.hub');
})->middleware(['auth', 'onboarding'])->name('dashboard');

$legacyJs = static function (string $file) {
    $path = base_path('legacy/'.$file);
    if (! is_file($path)) {
        abort(404);
    }

    return response()->file($path, [
        'Content-Type' => 'application/javascript; charset=UTF-8',
    ]);
};

Route::middleware('web')->group(function () use ($legacyJs) {
    Route::get('/cultivation.js', fn () => $legacyJs('cultivation.js'))->name('game.cultivation-js');
    Route::get('/game/cultivation.js', fn () => $legacyJs('cultivation.js'));
    Route::get('/game/inventory.js', fn () => $legacyJs('inventory.js'));
    Route::get('/game/explore_encounter.js', fn () => $legacyJs('explore_encounter.js'));
    Route::get('/game/pve.js', fn () => $legacyJs('pve.js'));

    Route::get('/game/assets/{path}', LegacyAssetController::class)
        ->where('path', '[A-Za-z0-9_\-/\.]+')
        ->name('game.legacy-assets');

    $legacyPage = '[A-Za-z0-9_\.]+';

    Route::any('/game/classic/{page}', [LegacyPhpController::class, 'pages'])
        ->where('page', $legacyPage)
        ->name('game.classic');

    Route::any('/game/pages/{page}', [LegacyPhpController::class, 'pages'])
        ->where('page', $legacyPage);

    Route::any('/game/controllers/{page}', [LegacyPhpController::class, 'controllers'])
        ->where('page', $legacyPage);

    Route::any('/game/admin/{page}', [LegacyPhpController::class, 'admin'])
        ->where('page', $legacyPage);
});

Route::middleware(['web', 'auth', 'onboarding'])->group(function () {
    Route::get('/game', GameHubController::class)->name('game.hub');
    Route::post('/game/pve-attack', [PveAttackController::class, 'store'])
        ->name('game.pve-attack');
});

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
