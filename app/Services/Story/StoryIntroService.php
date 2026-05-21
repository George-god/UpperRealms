<?php

declare(strict_types=1);

namespace App\Services\Story;

use App\Models\User;
use App\Models\UserStoryProgress;
use App\Services\CombatService;
use App\Support\PdoDatabase;
use Illuminate\Support\Facades\Schema;
use PDO;
use PDOException;

/**
 * Narrative intro progression only — does not alter cultivation/combat rules.
 */
final class StoryIntroService
{
    /**
     * @return array{progress: UserStoryProgress, step: array<string, mixed>, step_key: string}
     */
    public function getScene(int $userId): array
    {
        $progress = $this->progressFor($userId);
        $key = (string) $progress->current_step;
        $step = $this->stepConfig($key);

        return [
            'progress' => $progress,
            'step_key' => $key,
            'step' => $step,
            'chapter' => config('story.chapter_title'),
            'location' => config('story.locations.'.($step['location'] ?? 'spirit_veil_forest'), []),
            'flags' => $progress->flags ?? [],
        ];
    }

    public function isComplete(int $userId): bool
    {
        if (! Schema::hasTable('user_story_progress')) {
            return true;
        }

        return $this->progressFor($userId)->isComplete();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{success: bool, message?: string, redirect?: string, combat?: array<string, mixed>}
     */
    public function handleAction(int $userId, string $actionKey, array $payload = []): array
    {
        if (! Schema::hasTable('user_story_progress')) {
            return ['success' => false, 'message' => 'Story system not installed.'];
        }

        $progress = $this->progressFor($userId);
        if ($progress->isComplete()) {
            return ['success' => true, 'redirect' => route('game.hub')];
        }

        $stepKey = (string) $progress->current_step;
        $step = $this->stepConfig($stepKey);

        if ($stepKey === 'inspect_objects' && $actionKey === 'inspect') {
            return $this->handleInspect($progress, (string) ($payload['target'] ?? ''));
        }

        if ($stepKey === 'fight_wolf' && $actionKey === 'combat') {
            return $this->handleTutorialCombat($userId);
        }

        $actions = $step['actions'] ?? [];
        $action = collect($actions)->firstWhere('key', $actionKey);
        if ($action === null) {
            return ['success' => false, 'message' => 'Unknown action.'];
        }

        if (! empty($action['requires_inspected'])) {
            $inspected = $progress->flags['inspected'] ?? [];
            foreach ($action['requires_inspected'] as $required) {
                if (empty($inspected[$required])) {
                    return ['success' => false, 'message' => 'Examine everything in the mist first.'];
                }
            }
        }

        if (! empty($action['complete'])) {
            return $this->completeIntro($userId);
        }

        $next = $action['next'] ?? null;
        if ($next === null || $next === '') {
            return $this->completeIntro($userId);
        }

        $this->advanceTo($progress, (string) $next);

        return ['success' => true, 'redirect' => route('story.intro')];
    }

    /**
     * @return array{success: bool, message?: string, redirect?: string, combat?: array<string, mixed>}
     */
    private function handleInspect(UserStoryProgress $progress, string $target): array
    {
        if ($target === '') {
            return ['success' => false, 'message' => 'Nothing to inspect.'];
        }

        $flags = $progress->flags ?? [];
        $inspected = $flags['inspected'] ?? [];
        $inspected[$target] = true;
        $flags['inspected'] = $inspected;
        $progress->flags = $flags;
        $progress->save();

        return ['success' => true, 'redirect' => route('story.intro')];
    }

    /**
     * @return array{success: bool, message?: string, redirect?: string, combat?: array<string, mixed>}
     */
    private function handleTutorialCombat(int $userId): array
    {
        $npcId = $this->resolveTutorialNpcId();
        if ($npcId === null) {
            $progress = $this->progressFor($userId);
            $this->advanceTo($progress, 'qi_condensation');

            return [
                'success' => true,
                'redirect' => route('story.intro'),
                'message' => 'The wolf falters and fades (tutorial adversary unavailable).',
            ];
        }

        $combat = app(CombatService::class)->runPveAttack($userId, $npcId, false);
        if (empty($combat['success'])) {
            return ['success' => false, 'message' => (string) ($combat['error'] ?? 'The battle could not begin.')];
        }

        $won = ($combat['winner'] ?? '') === 'user';
        $progress = $this->progressFor($userId);

        if ($won) {
            $flags = $progress->flags ?? [];
            $flags['wolf_defeated'] = true;
            $progress->flags = $flags;
            $progress->save();
            $this->advanceTo($progress, 'qi_condensation');

            return [
                'success' => true,
                'redirect' => route('story.intro'),
                'combat' => $combat,
                'message' => 'The corrupted wolf falls. Qi stirs within you.',
            ];
        }

        return [
            'success' => true,
            'redirect' => route('story.intro'),
            'combat' => $combat,
            'message' => 'You are driven back. Try again when your breath steadies.',
        ];
    }

    /**
     * @return array{success: bool, redirect: string, message: string}
     */
    public function completeIntro(int $userId): array
    {
        $progress = $this->progressFor($userId);
        $completed = $progress->completed_steps ?? [];
        $completed[] = (string) $progress->current_step;
        $progress->completed_steps = array_values(array_unique($completed));
        $progress->current_step = 'complete';
        $progress->intro_completed_at = now();
        $progress->save();

        $user = User::query()->find($userId);
        if ($user !== null) {
            $user->forceFill([
                'onboarding_step' => 99,
                'onboarding_completed_at' => $user->onboarding_completed_at ?? now(),
            ])->save();
        }

        return [
            'success' => true,
            'redirect' => route('game.hub'),
            'message' => __('The veil lifts. Your path in the Upper Realms begins.'),
        ];
    }

    public function skipIntro(int $userId): void
    {
        $this->completeIntro($userId);
    }

    private function progressFor(int $userId): UserStoryProgress
    {
        return UserStoryProgress::query()->firstOrCreate(
            ['user_id' => $userId],
            ['current_step' => config('story.step_order.0', 'awakening'), 'completed_steps' => [], 'flags' => []]
        );
    }

    private function advanceTo(UserStoryProgress $progress, string $nextStep): void
    {
        $completed = $progress->completed_steps ?? [];
        $completed[] = (string) $progress->current_step;
        $progress->completed_steps = array_values(array_unique($completed));
        $progress->current_step = $nextStep;
        $progress->save();
    }

    /**
     * @return array<string, mixed>
     */
    private function stepConfig(string $key): array
    {
        return config('story.steps.'.$key, []);
    }

    private function resolveTutorialNpcId(): ?int
    {
        $name = (string) config('story.tutorial_combat.npc_name', 'Corrupted Spirit Wolf');
        if (! Schema::hasTable('npcs')) {
            return null;
        }

        try {
            $db = PdoDatabase::connection();
            $stmt = $db->prepare('SELECT id FROM npcs WHERE name = ? ORDER BY id ASC LIMIT 1');
            $stmt->execute([$name]);
            $id = $stmt->fetchColumn();

            return $id !== false ? (int) $id : null;
        } catch (PDOException $e) {
            error_log('StoryIntroService::resolveTutorialNpcId '.$e->getMessage());

            return null;
        }
    }
}
