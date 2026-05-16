<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Responses\GameJson;
use App\Services\Story\StoryIntroService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StoryIntroController extends Controller
{
    public function __construct(
        private readonly StoryIntroService $story,
    ) {}

    public function show(Request $request): View|RedirectResponse
    {
        $userId = (int) $request->user()->id;

        if ($this->story->isComplete($userId)) {
            return redirect()->route('game.hub');
        }

        $scene = $this->story->getScene($userId);
        $step = $scene['step'];
        $type = (string) ($step['type'] ?? 'narrative');

        return view(match ($type) {
            'cinematic' => 'story.cinematic',
            'dream' => 'story.dream',
            'combat' => 'story.combat',
            default => 'story.scene',
        }, $scene);
    }

    public function action(Request $request): JsonResponse|RedirectResponse
    {
        $userId = (int) $request->user()->id;
        $validated = $request->validate([
            'action' => ['required', 'string', 'max:64'],
            'target' => ['nullable', 'string', 'max:64'],
        ]);

        $result = $this->story->handleAction(
            $userId,
            $validated['action'],
            ['target' => $validated['target'] ?? null],
        );

        if ($request->expectsJson()) {
            if (! $result['success']) {
                return GameJson::error((string) ($result['message'] ?? 'Action failed.'));
            }

            return GameJson::success([
                'redirect' => $result['redirect'] ?? route('story.intro'),
                'combat' => $result['combat'] ?? null,
                'message' => $result['message'] ?? '',
            ]);
        }

        if (! $result['success']) {
            return back()->with('story_error', $result['message'] ?? '');
        }

        return redirect($result['redirect'] ?? route('story.intro'))
            ->with('story_status', $result['message'] ?? null);
    }

    public function skip(Request $request): RedirectResponse
    {
        $this->story->skipIntro((int) $request->user()->id);

        return redirect()->route('game.hub')->with('status', __('You slip past the veil of memory—the realm opens before you.'));
    }
}
