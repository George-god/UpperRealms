<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\DungeonService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DungeonController extends Controller
{
    public function index(Request $request, DungeonService $dungeons): View
    {
        $userId = (int) $request->user()->id;
        $data = $dungeons->getDungeonsForUser($userId);

        return view('game.dungeons-index', [
            'dungeons' => $data['dungeons'] ?? [],
            'runsRemaining' => (int) ($data['daily_runs_remaining'] ?? 0),
            'userRealmId' => (int) ($data['user_realm_id'] ?? 1),
            'highlightId' => (int) $request->query('highlight', 0),
            'breadcrumbs' => [
                ['label' => __('Game'), 'href' => route('game.hub')],
                ['label' => __('Dungeons')],
            ],
        ]);
    }

    public function show(Request $request, DungeonService $dungeons, int $dungeon): View|RedirectResponse
    {
        $userId = (int) $request->user()->id;
        $row = $dungeons->getDungeonById($dungeon);
        if (! $row) {
            return redirect()->route('game.dungeons.index')
                ->with('error', __('Dungeon not found.'));
        }

        $data = $dungeons->getDungeonsForUser($userId);
        $locked = ($data['user_realm_id'] ?? 1) < (int) $row['min_realm_id'];
        $activeRun = $dungeons->getActiveRunForUser($userId, $dungeon);
        $runsRemaining = $dungeons->getDailyRunsRemaining($userId);
        $stagePreview = $dungeons->getStagePreview($activeRun ?: $row);

        $battleResult = null;
        $battleFlash = $request->session()->pull('dungeon_battle_flash');
        if (is_array($battleFlash) && ! empty($battleFlash['battle']) && is_array($battleFlash['battle'])) {
            $battleResult = [
                'battle' => $battleFlash['battle'],
                'stage_name' => (string) ($battleFlash['stage_name'] ?? 'Battle'),
                'rewards' => $battleFlash['rewards'] ?? null,
                'completed' => (bool) ($battleFlash['completed'] ?? false),
            ];
        }

        $dungeonBattleJson = null;
        $pulseRoomIndex = null;
        if ($battleResult && ! empty($battleResult['battle']) && is_array($battleResult['battle'])) {
            $dungeonBattleJson = [
                'battle' => $battleResult['battle'],
                'stage_name' => $battleResult['stage_name'] ?? 'Battle',
                'rewards' => $battleResult['rewards'] ?? null,
                'playerName' => (string) ($request->user()->username ?? $request->user()->name ?? 'Cultivator'),
                'regionName' => (string) ($row['region_name'] ?? ''),
                'dungeonName' => (string) ($row['name'] ?? ''),
            ];
            $sn = (string) ($battleResult['stage_name'] ?? '');
            if (preg_match('/Stage\s*3|Boss\b/i', $sn)) {
                $pulseRoomIndex = 3;
            } elseif (preg_match('/Stage\s*2|Elite/i', $sn)) {
                $pulseRoomIndex = 2;
            } else {
                $pulseRoomIndex = 1;
            }
        }

        $nextRoomIndex = 1;
        if ($activeRun) {
            $nextRoomIndex = min(3, (int) $activeRun['progress'] + 1);
        }

        return view('game.dungeon-run', [
            'dungeon' => $row,
            'dungeonId' => $dungeon,
            'locked' => $locked,
            'activeRun' => $activeRun,
            'runsRemaining' => $runsRemaining,
            'stagePreview' => $stagePreview,
            'battleResult' => $battleResult,
            'dungeonBattleJson' => $dungeonBattleJson,
            'pulseRoomIndex' => $pulseRoomIndex,
            'nextRoomIndex' => $nextRoomIndex,
            'breadcrumbs' => [
                ['label' => __('Game'), 'href' => route('game.hub')],
                ['label' => __('Dungeons'), 'href' => route('game.dungeons.index')],
                ['label' => (string) ($row['name'] ?? __('Run'))],
            ],
        ]);
    }

    public function start(Request $request, DungeonService $dungeons, int $dungeon): RedirectResponse
    {
        $userId = (int) $request->user()->id;
        $result = $dungeons->startRun($userId, $dungeon);

        if (! $result['success']) {
            return redirect()->route('game.dungeon.show', ['dungeon' => $dungeon])
                ->with('error', $result['message'] ?? __('Could not start run.'));
        }

        return redirect()->route('game.dungeon.show', ['dungeon' => $dungeon])
            ->with('status', $result['message'] ?? __('Dungeon run started.'));
    }

    public function advance(Request $request, DungeonService $dungeons, int $dungeon): RedirectResponse
    {
        $validated = $request->validate([
            'run_id' => ['required', 'integer', 'min:1'],
        ]);

        $userId = (int) $request->user()->id;
        $result = $dungeons->advanceRun($userId, (int) $validated['run_id']);

        if (! $result['success']) {
            return redirect()->route('game.dungeon.show', ['dungeon' => $dungeon])
                ->with('error', $result['message'] ?? __('Advance failed.'));
        }

        $redirect = redirect()->route('game.dungeon.show', ['dungeon' => $dungeon])
            ->with('status', $result['message'] ?? __('Stage resolved.'));

        if (! empty($result['battle']) && is_array($result['battle'])) {
            $redirect->with('dungeon_battle_flash', [
                'battle' => $result['battle'],
                'stage_name' => $result['stage_name'] ?? 'Battle',
                'rewards' => $result['rewards'] ?? null,
                'completed' => ! empty($result['completed']),
            ]);
        }

        return $redirect;
    }
}
