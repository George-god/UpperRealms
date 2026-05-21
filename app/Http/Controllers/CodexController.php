<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Codex\CodexPresenter;
use App\Services\Codex\CodexUnlockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class CodexController extends Controller
{
    public function __construct(
        private readonly CodexUnlockService $unlockService,
        private readonly CodexPresenter $presenter,
    ) {}

    public function index(Request $request): View
    {
        $userId = (int) $request->user()->id;

        if (! Schema::hasTable('codex_entries')) {
            return view('game.codex', [
                'migrationRequired' => true,
                'codex' => null,
                'reveal' => [],
            ]);
        }

        $reveal = $this->unlockService->syncForUser($userId);
        $codex = $this->presenter->buildPageData($userId);

        return view('game.codex', [
            'migrationRequired' => false,
            'codex' => $codex,
            'reveal' => $reveal['newly_unlocked'] ?? [],
        ]);
    }

    public function sync(Request $request): JsonResponse
    {
        if (! Schema::hasTable('codex_entries')) {
            return response()->json(['success' => false, 'message' => 'Codex not installed.'], 503);
        }

        $userId = (int) $request->user()->id;
        $reveal = $this->unlockService->syncForUser($userId);
        $codex = $this->presenter->buildPageData($userId);

        return response()->json([
            'success' => true,
            'newly_unlocked' => $reveal['newly_unlocked'],
            'new_count' => $reveal['new_count'],
            'codex' => $codex,
        ]);
    }

    public function markSeen(Request $request): JsonResponse
    {
        if (! Schema::hasTable('user_codex_entries')) {
            return response()->json(['success' => false], 503);
        }

        $userId = (int) $request->user()->id;
        $entryId = $request->integer('entry_id') ?: null;
        $this->unlockService->markSeen($userId, $entryId);

        return response()->json([
            'success' => true,
            'new_count' => $this->unlockService->newCount($userId),
        ]);
    }
}
