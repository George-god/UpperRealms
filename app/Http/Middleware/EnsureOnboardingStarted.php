<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Send cultivators who have not acknowledged the intro to /welcome.
 */
class EnsureOnboardingStarted
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user === null) {
            return $next($request);
        }

        if ($user->onboarding_completed_at !== null) {
            return $next($request);
        }

        if ((int) $user->onboarding_step === 0) {
            return redirect()->route('onboarding.welcome');
        }

        return $next($request);
    }
}
