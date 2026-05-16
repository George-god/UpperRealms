<?php

declare(strict_types=1);

namespace App\Services\Codex;

use App\Models\CodexEntry;
use App\Models\UserCodexEntry;
use App\Support\PdoDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use PDO;

/**
 * Unlocks codex entries by reading existing player progress (no gameplay mutation).
 */
class CodexUnlockService
{
    public function __construct(
        private readonly CodexCatalogSyncService $catalogSync,
    ) {}

    /**
     * @return array{newly_unlocked: list<array<string, mixed>>, new_count: int}
     */
    public function syncForUser(int $userId): array
    {
        if (! Schema::hasTable('codex_entries') || ! Schema::hasTable('user_codex_entries')) {
            return ['newly_unlocked' => [], 'new_count' => 0];
        }

        if (CodexEntry::query()->count() === 0 && Schema::hasTable('realms')) {
            $this->catalogSync->sync();
        }

        $entryKeys = $this->resolveUnlockKeys($userId);
        if ($entryKeys === []) {
            return ['newly_unlocked' => [], 'new_count' => 0];
        }

        $entries = CodexEntry::query()
            ->whereIn('entry_key', $entryKeys)
            ->get()
            ->keyBy('entry_key');

        $existing = UserCodexEntry::query()
            ->where('user_id', $userId)
            ->pluck('codex_entry_id')
            ->flip();

        $newly = [];
        foreach ($entryKeys as $key) {
            $entry = $entries->get($key);
            if ($entry === null || isset($existing[$entry->id])) {
                continue;
            }

            UserCodexEntry::query()->create([
                'user_id' => $userId,
                'codex_entry_id' => $entry->id,
                'unlocked_at' => now(),
                'is_new' => true,
            ]);

            $newly[] = [
                'id' => $entry->id,
                'entry_key' => $entry->entry_key,
                'title' => $entry->title,
                'category' => $entry->category,
                'icon' => $entry->icon,
            ];
        }

        return ['newly_unlocked' => $newly, 'new_count' => count($newly)];
    }

    public function markSeen(int $userId, ?int $entryId = null): void
    {
        $query = UserCodexEntry::query()->where('user_id', $userId);
        if ($entryId !== null) {
            $query->where('codex_entry_id', $entryId);
        }

        $query->update([
            'is_new' => false,
            'first_seen_at' => now(),
        ]);
    }

    public function newCount(int $userId): int
    {
        if (! Schema::hasTable('user_codex_entries')) {
            return 0;
        }

        return (int) UserCodexEntry::query()
            ->where('user_id', $userId)
            ->where('is_new', true)
            ->count();
    }

    /**
     * @return list<string>
     */
    private function resolveUnlockKeys(int $userId): array
    {
        $keys = array_merge(
            $this->realmKeys($userId),
            $this->bloodlineKeys($userId),
            $this->artifactKeys($userId),
            $this->regionKeys($userId),
            $this->manualKeys($userId),
            $this->sectKeys($userId),
            $this->monsterKeys($userId),
            $this->historyKeys($userId),
        );

        return array_values(array_unique($keys));
    }

    /**
     * @return list<string>
     */
    private function realmKeys(int $userId): array
    {
        if (! Schema::hasTable('users') || ! Schema::hasTable('realms')) {
            return [];
        }

        $db = PdoDatabase::connection();
        $stmt = $db->prepare('SELECT realm_id FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $realmId = (int) ($stmt->fetchColumn() ?: 1);

        $realmIds = $db->query('SELECT id FROM realms WHERE id <= '.(int) $realmId.' ORDER BY id ASC')->fetchAll(PDO::FETCH_COLUMN) ?: [];

        return array_map(static fn ($id): string => 'realm_'.(int) $id, $realmIds);
    }

    /**
     * @return list<string>
     */
    private function bloodlineKeys(int $userId): array
    {
        if (! Schema::hasTable('user_bloodlines') || ! Schema::hasTable('bloodlines')) {
            return [];
        }

        $db = PdoDatabase::connection();
        $stmt = $db->prepare('
            SELECT b.bloodline_key, b.id
            FROM user_bloodlines ub
            JOIN bloodlines b ON b.id = ub.bloodline_id
            WHERE ub.user_id = ?
        ');
        $stmt->execute([$userId]);
        $keys = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $keys[] = 'bloodline_'.((string) ($row['bloodline_key'] ?? $row['id']));
        }

        return $keys;
    }

    /**
     * @return list<string>
     */
    private function artifactKeys(int $userId): array
    {
        if (! Schema::hasTable('user_artifacts') || ! Schema::hasTable('artifacts')) {
            return [];
        }

        $db = PdoDatabase::connection();
        $stmt = $db->prepare('
            SELECT DISTINCT a.artifact_key, a.id
            FROM user_artifacts ua
            JOIN artifacts a ON a.id = ua.artifact_id
            WHERE ua.user_id = ?
        ');
        $stmt->execute([$userId]);
        $keys = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $keys[] = 'artifact_'.((string) ($row['artifact_key'] ?? $row['id']));
        }

        return $keys;
    }

    /**
     * @return list<string>
     */
    private function regionKeys(int $userId): array
    {
        if (! Schema::hasTable('world_regions')) {
            return [];
        }

        $db = PdoDatabase::connection();
        $regionIds = [];

        if (Schema::hasTable('user_location')) {
            $stmt = $db->prepare('SELECT region_id FROM user_location WHERE user_id = ? LIMIT 1');
            $stmt->execute([$userId]);
            $rid = (int) ($stmt->fetchColumn() ?: 0);
            if ($rid > 0) {
                $regionIds[$rid] = true;
            }
        }

        if (Schema::hasTable('dungeon_runs') && Schema::hasTable('dungeons')) {
            $stmt = $db->prepare('
                SELECT DISTINCT d.region_id
                FROM dungeon_runs dr
                JOIN dungeons d ON d.id = dr.dungeon_id
                WHERE dr.user_id = ? AND dr.is_completed = 1
            ');
            $stmt->execute([$userId]);
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) ?: [] as $rid) {
                $regionIds[(int) $rid] = true;
            }
        }

        $keys = [];
        foreach (array_keys($regionIds) as $id) {
            $keys[] = 'region_'.$id;
        }

        return $keys;
    }

    /**
     * @return list<string>
     */
    private function manualKeys(int $userId): array
    {
        if (! Schema::hasTable('user_cultivation_manuals') || ! Schema::hasTable('cultivation_manuals')) {
            return [];
        }

        $db = PdoDatabase::connection();
        $stmt = $db->prepare('
            SELECT m.manual_key, m.id
            FROM user_cultivation_manuals um
            JOIN cultivation_manuals m ON m.id = um.manual_id
            WHERE um.user_id = ?
        ');
        $stmt->execute([$userId]);
        $keys = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $keys[] = 'manual_'.((string) ($row['manual_key'] ?? $row['id']));
        }

        return $keys;
    }

    /**
     * @return list<string>
     */
    private function sectKeys(int $userId): array
    {
        if (! Schema::hasTable('sect_members')) {
            return [];
        }

        $db = PdoDatabase::connection();
        $stmt = $db->prepare('SELECT sect_id FROM sect_members WHERE user_id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $sectId = (int) ($stmt->fetchColumn() ?: 0);
        if ($sectId <= 0) {
            return [];
        }

        return ['sect_'.$sectId];
    }

    /**
     * @return list<string>
     */
    private function monsterKeys(int $userId): array
    {
        $keys = [];
        $db = PdoDatabase::connection();

        if (Schema::hasTable('pve_battles')) {
            $stmt = $db->prepare('SELECT DISTINCT npc_id FROM pve_battles WHERE user_id = ? AND winner = ?');
            $stmt->execute([$userId, 'user']);
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) ?: [] as $npcId) {
                $keys[] = 'npc_'.(int) $npcId;
            }
        }

        if (Schema::hasTable('dungeon_runs') && Schema::hasTable('dungeons')) {
            $stmt = $db->prepare('
                SELECT DISTINCT dr.dungeon_id
                FROM dungeon_runs dr
                WHERE dr.user_id = ? AND dr.is_completed = 1
            ');
            $stmt->execute([$userId]);
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) ?: [] as $dungeonId) {
                $keys[] = 'dungeon_boss_'.(int) $dungeonId;
            }
        }

        if (Schema::hasTable('dao_records')) {
            $stmt = $db->prepare('SELECT description, context_data FROM dao_records WHERE user_id = ?');
            $stmt->execute([$userId]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $desc = (string) ($row['description'] ?? '');
                $this->matchEncounterNamesFromText($desc, $keys);
                $ctx = json_decode((string) ($row['context_data'] ?? ''), true);
                if (is_array($ctx)) {
                    foreach (['enemy', 'boss_name', 'encounter', 'target_name'] as $field) {
                        if (! empty($ctx[$field])) {
                            $this->matchEncounterNamesFromText((string) $ctx[$field], $keys);
                        }
                    }
                }
            }
        }

        if (Schema::hasTable('world_boss_templates') && Schema::hasTable('dao_records')) {
            $stmt = $db->prepare("
                SELECT DISTINCT wbt.id
                FROM dao_records dr
                JOIN world_boss_templates wbt ON dr.description LIKE CONCAT('%', wbt.name, '%')
                WHERE dr.user_id = ? AND dr.event_type LIKE '%world_boss%'
            ");
            $stmt->execute([$userId]);
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) ?: [] as $bossId) {
                $keys[] = 'world_boss_'.(int) $bossId;
            }
        }

        return $keys;
    }

    /**
     * @param list<string> $keys
     */
    private function matchEncounterNamesFromText(string $text, array &$keys): void
    {
        if ($text === '' || ! Schema::hasTable('codex_entries')) {
            return;
        }

        static $encounterNames = null;
        if ($encounterNames === null) {
            $encounterNames = CodexEntry::query()
                ->where('category', 'monsters')
                ->where('source_type', 'encounter')
                ->pluck('source_ref', 'entry_key')
                ->all();
        }

        foreach ($encounterNames as $entryKey => $name) {
            if ($name !== '' && stripos($text, (string) $name) !== false) {
                $keys[] = $entryKey;
            }
        }
    }

    /**
     * @return list<string>
     */
    private function historyKeys(int $userId): array
    {
        $keys = [];
        $milestones = config('codex.history_milestones', []);

        if (Schema::hasTable('dao_records')) {
            $db = PdoDatabase::connection();
            $stmt = $db->prepare('SELECT COUNT(*) FROM dao_records WHERE user_id = ?');
            $stmt->execute([$userId]);
            if ((int) $stmt->fetchColumn() > 0) {
                $keys[] = 'history_cosmic_origins';
            }
        }

        if (Schema::hasTable('dao_records') && $milestones !== []) {
            $db = PdoDatabase::connection();
            foreach ($milestones as $milestoneKey => $rule) {
                $types = $rule['event_types'] ?? [];
                $min = (int) ($rule['min_count'] ?? 1);
                if ($types === []) {
                    continue;
                }
                $placeholders = implode(',', array_fill(0, count($types), '?'));
                $params = array_merge([$userId], $types);
                $stmt = $db->prepare("
                    SELECT COUNT(*) FROM dao_records
                    WHERE user_id = ? AND event_type IN ($placeholders)
                ");
                $stmt->execute($params);
                if ((int) $stmt->fetchColumn() >= $min) {
                    $keys[] = 'history_'.$milestoneKey;
                }
            }
        }

        if (Schema::hasTable('eras') && Schema::hasTable('era_rankings')) {
            $db = PdoDatabase::connection();
            $stmt = $db->prepare('SELECT DISTINCT era_id FROM era_rankings WHERE user_id = ?');
            $stmt->execute([$userId]);
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) ?: [] as $eraId) {
                $keys[] = 'era_'.(int) $eraId;
            }
        }

        if (Schema::hasTable('dungeon_runs')) {
            $db = PdoDatabase::connection();
            $stmt = $db->prepare('SELECT COUNT(*) FROM dungeon_runs WHERE user_id = ? AND is_completed = 1');
            $stmt->execute([$userId]);
            if ((int) $stmt->fetchColumn() > 0) {
                $keys[] = 'history_fallen_sects';
            }
        }

        if (Schema::hasTable('user_location')) {
            $db = PdoDatabase::connection();
            $stmt = $db->prepare('SELECT region_id FROM user_location WHERE user_id = ? LIMIT 1');
            $stmt->execute([$userId]);
            $rid = (int) ($stmt->fetchColumn() ?: 0);
            if ($rid >= 9) {
                $keys[] = 'history_fallen_sects';
            }
        }

        return array_values(array_unique($keys));
    }
}
