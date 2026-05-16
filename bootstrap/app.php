<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'game.admin' => \App\Http\Middleware\EnsureGameAdmin::class,
            'onboarding' => \App\Http\Middleware\EnsureOnboardingStarted::class,
            'story.intro' => \App\Http\Middleware\EnsureStoryIntroComplete::class,
        ]);
        $middleware->validateCsrfTokens(except: [
            'game/classic/*',
            'game/pages/*',
            'game/controllers/*',
            'game/admin/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
