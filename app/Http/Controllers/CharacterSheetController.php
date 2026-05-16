<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Responses\GameJson;
use App\Services\Attributes\CharacterBuildService;
use App\Services\StatCalculator;
use App\Services\StatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CharacterSheetController extends Controller
{
    public function __construct(
        private readonly CharacterBuildService $buildService,
        private readonly StatService $statService,
        private readonly StatCalculator $statCalculator,
    ) {}

    public function show(Request $request): View
    {
        $userId = (int) $request->user()->id;
        try {
            $build = $this->buildService->getBuildState($userId);
            $combat = $this->statService->calculateFinalStats($userId);
            $breakdown = $this->statCalculator->getCombatStatBreakdown($userId);
        } catch (\Throwable $e) {
            report($e);

            return view('game.character-sheet', [
                'build' => null,
                'combat' => null,
                'breakdown' => null,
                'final' => [],
                'migrationRequired' => true,
            ]);
        }

        return view('game.character-sheet', [
            'build' => $build,
            'combat' => $combat,
            'breakdown' => $breakdown,
            'final' => $combat['final'] ?? [],
            'migrationRequired' => false,
        ]);
    }

    public function allocate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'stat' => ['required', 'string', 'max:32'],
            'points' => ['sometimes', 'integer', 'min:1', 'max:20'],
        ]);

        $result = $this->buildService->allocate(
            (int) $request->user()->id,
            $validated['stat'],
            (int) ($validated['points'] ?? 1),
        );

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    public function specialization(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'specialization' => ['nullable', 'string', 'max:32'],
        ]);

        $result = $this->buildService->setSpecialization(
            (int) $request->user()->id,
            $validated['specialization'] ?? null,
        );

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    public function bodyType(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'body_type' => ['nullable', 'string', 'max:48'],
        ]);

        $result = $this->buildService->setBodyType(
            (int) $request->user()->id,
            $validated['body_type'] ?? null,
        );

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    public function respec(Request $request): JsonResponse
    {
        $result = $this->buildService->respec((int) $request->user()->id);

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    public function stats(Request $request, StatService $stats, StatCalculator $calculator): JsonResponse
    {
        $userId = (int) $request->user()->id;

        try {
            $combat = $stats->calculateFinalStats($userId);
            $breakdown = $calculator->getCombatStatBreakdown($userId);

            return GameJson::success([
                'final' => $combat['final'] ?? [],
                'base' => $combat['base'] ?? [],
                'modifiers' => $combat['modifiers'] ?? [],
                'breakdown' => $breakdown,
            ]);
        } catch (\Throwable $e) {
            report($e);

            return GameJson::error(__('Could not load combat stats.'), 500);
        }
    }
}
