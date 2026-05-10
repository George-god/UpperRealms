<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OnboardingController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        if ($user->onboarding_completed_at !== null) {
            return redirect()->route('game.hub');
        }

        return view('onboarding.welcome');
    }

    public function begin(Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($user->onboarding_completed_at === null && (int) $user->onboarding_step === 0) {
            $user->forceFill(['onboarding_step' => 1])->save();
        }

        return redirect()->route('game.hub');
    }

    public function skip(Request $request): RedirectResponse
    {
        $user = $request->user();
        $user->forceFill([
            'onboarding_step' => 99,
            'onboarding_completed_at' => now(),
        ])->save();

        return redirect()->route('game.hub');
    }

    public function complete(Request $request): RedirectResponse
    {
        $user = $request->user();
        $user->forceFill([
            'onboarding_step' => 99,
            'onboarding_completed_at' => now(),
        ])->save();

        return redirect()->route('game.hub')->with('status', __('Orientation complete — the full realm is yours.'));
    }
}
