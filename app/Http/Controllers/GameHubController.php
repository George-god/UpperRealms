<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class GameHubController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        return view('game.hub', [
            'onboardingActive' => $user->onboarding_completed_at === null,
        ]);
    }
}
