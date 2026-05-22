<?php

use App\Http\Controllers\CharacterSheetController;
use App\Http\Controllers\CodexController;
use App\Http\Controllers\DungeonController;
use App\Http\Controllers\GameHubController;
use App\Http\Controllers\LegacyAssetController;
use App\Http\Controllers\LegacyPhpController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\StoryIntroController;
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

    $legacyPage = '[A-Za-z0-9_]+';

    Route::any('/game/classic/{page}', [LegacyPhpController::class, 'pages'])
        ->where('page', $legacyPage)
        ->name('game.classic');

    Route::any('/game/pages/{page}', [LegacyPhpController::class, 'pages'])
        ->where('page', $legacyPage);

    Route::any('/game/controllers/{page}', [LegacyPhpController::class, 'controllers'])
        ->where('page', $legacyPage);
});

Route::middleware(['web', 'auth', 'game.admin'])->group(function () {
    $legacyPage = '[A-Za-z0-9_]+';

    Route::any('/game/admin/{page}', [LegacyPhpController::class, 'admin'])
        ->where('page', $legacyPage);
});

Route::middleware(['web', 'auth', 'onboarding'])->group(function () {
    Route::get('/game/story', [StoryIntroController::class, 'show'])->name('story.intro');
    Route::post('/game/story/action', [StoryIntroController::class, 'action'])->name('story.action');
    Route::post('/game/story/skip', [StoryIntroController::class, 'skip'])->name('story.skip');

    Route::middleware('story.intro')->group(function () {
        Route::get('/game', GameHubController::class)->name('game.hub');

    Route::get('/game/codex', [CodexController::class, 'index'])->name('game.codex');
    Route::post('/game/codex/sync', [CodexController::class, 'sync'])->name('game.codex.sync');
    Route::post('/game/codex/seen', [CodexController::class, 'markSeen'])->name('game.codex.seen');

    Route::get('/game/character', [CharacterSheetController::class, 'show'])->name('game.character');
    Route::get('/game/character/stats', [CharacterSheetController::class, 'stats'])->name('game.character.stats');
    Route::post('/game/character/allocate', [CharacterSheetController::class, 'allocate'])->name('game.character.allocate');
    Route::post('/game/character/specialization', [CharacterSheetController::class, 'specialization'])->name('game.character.specialization');
    Route::post('/game/character/body-type', [CharacterSheetController::class, 'bodyType'])->name('game.character.body-type');
    Route::post('/game/character/respec', [CharacterSheetController::class, 'respec'])->name('game.character.respec');
    Route::post('/game/pve-attack', [PveAttackController::class, 'store'])
        ->name('game.pve-attack');

    Route::get('/game/dungeons', [DungeonController::class, 'index'])->name('game.dungeons.index');
    Route::get('/game/dungeon/{dungeon}', [DungeonController::class, 'show'])
        ->whereNumber('dungeon')
        ->name('game.dungeon.show');
    Route::post('/game/dungeon/{dungeon}/start', [DungeonController::class, 'start'])
        ->whereNumber('dungeon')
        ->name('game.dungeon.start');
    Route::post('/game/dungeon/{dungeon}/advance', [DungeonController::class, 'advance'])
        ->whereNumber('dungeon')
        ->name('game.dungeon.advance');
    });
});

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
