<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\PveAttackRequest;
use App\Http\Responses\GameJson;
use App\Services\CombatService;

class PveAttackController extends Controller
{
    public function store(PveAttackRequest $request, CombatService $combat)
    {
        $userId = (int) $request->user()->id;
        $npcId = (int) $request->validated('npc_id');
        $useDao = (bool) $request->boolean('use_dao_techniques', true);

        $result = $combat->runPveAttack($userId, $npcId, $useDao);

        if (empty($result['success'])) {
            return GameJson::error((string) ($result['error'] ?? 'Battle failed.'), 400);
        }

        return GameJson::success([
            'winner' => $result['winner'],
            'battle_log' => $result['battle_log'],
            'user_chi_after' => $result['user_chi_after'],
            'user_max_chi' => $result['user_max_chi'],
            'npc_hp_max' => (int) $result['npc_hp_max'],
            'chi_reward' => (int) $result['chi_reward'],
            'npc_name' => $result['npc_name'],
            'dropped_item' => $result['dropped_item'] ?? null,
            'herb_dropped' => $result['herb_dropped'] ?? null,
            'material_dropped' => $result['material_dropped'] ?? null,
            'rune_fragment_dropped' => $result['rune_fragment_dropped'] ?? null,
            'gold_gained' => (int) ($result['gold_gained'] ?? 0),
            'spirit_stone_gained' => (int) ($result['spirit_stone_gained'] ?? 0),
        ]);
    }
}
