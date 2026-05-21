<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Story\StoryIntroService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Routes new cultivators through the Spirit Veil prologue before the open realm.
 */
class EnsureStoryIntroComplete
{
    public function __construct(
        private readonly StoryIntroService $story,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user === null) {
            return $next($request);
        }

        if ((int) $user->onboarding_step === 0) {
            return $next($request);
        }

        if ($this->story->isComplete((int) $user->id)) {
            return $next($request);
        }

        if ($request->routeIs('story.*') || $request->routeIs('onboarding.*')) {
            return $next($request);
        }

        return redirect()->route('story.intro');
    }
}
