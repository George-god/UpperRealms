<?php

declare(strict_types=1);

namespace App\Services\Codex;

use App\Models\CodexEntry;
use App\Support\PdoDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PDO;

/**
 * Builds the static codex catalog from existing game tables (read-only).
 */
class CodexCatalogSyncService
{
    public function sync(): int
    {
        if (! Schema::hasTable('codex_entries')) {
            return 0;
        }

        $count = 0;
        foreach ([
            'syncRealms',
            'syncBloodlines',
            'syncArtifacts',
            'syncRegions',
            'syncManuals',
            'syncSects',
            'syncMonsters',
            'syncHistory',
        ] as $method) {
            try {
                $count += $this->{$method}();
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $count;
    }

    private function upsert(array $row): void
    {
        CodexEntry::query()->updateOrCreate(
            ['entry_key' => $row['entry_key']],
            $row
        );
    }

    private function syncRealms(): int
    {
        if (! Schema::hasTable('realms')) {
            return 0;
        }

        $cols = $this->columns('realms', ['id', 'name', 'description']);
        $rows = PdoDatabase::connection()->query('SELECT '.implode(', ', $cols).' FROM realms ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as $realm) {
            $id = (int) $realm['id'];
            $this->upsert([
                'category' => 'realms',
                'entry_key' => 'realm_'.$id,
                'title' => (string) $realm['name'],
                'teaser' => 'A veil of qi obscures this cultivation realm…',
                'body' => trim((string) ($realm['description'] ?? '')) ?: 'Records speak of cultivators who tempered body and spirit within this realm until the heavens acknowledged their ascent.',
                'icon' => '☁️',
                'source_type' => 'realm',
                'source_id' => $id,
                'source_ref' => null,
                'unlock_hint' => 'Reach this cultivation realm.',
                'sort_order' => $id,
                'meta' => ['realm_id' => $id],
            ]);
        }

        return count($rows);
    }

    private function syncBloodlines(): int
    {
        if (! Schema::hasTable('bloodlines')) {
            return 0;
        }

        $rows = PdoDatabase::connection()->query('SELECT id, bloodline_key, name, description FROM bloodlines ORDER BY sort_order ASC, id ASC')->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $this->upsert([
                'category' => 'bloodlines',
                'entry_key' => 'bloodline_'.($row['bloodline_key'] ?? $id),
                'title' => (string) $row['name'],
                'teaser' => 'An ancestral thread sleeps within your meridians…',
                'body' => trim((string) ($row['description'] ?? '')) ?: 'This bloodline awaits awakening through deeds that shake heaven and earth.',
                'icon' => '🩸',
                'source_type' => 'bloodline',
                'source_id' => $id,
                'source_ref' => null,
                'unlock_hint' => 'Awaken this bloodline.',
                'sort_order' => $id,
                'meta' => ['bloodline_id' => $id],
            ]);
        }

        return count($rows);
    }

    private function syncArtifacts(): int
    {
        if (! Schema::hasTable('artifacts')) {
            return 0;
        }

        $rows = PdoDatabase::connection()->query('SELECT id, artifact_key, name, description, rarity FROM artifacts ORDER BY sort_order ASC, id ASC')->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $this->upsert([
                'category' => 'artifacts',
                'entry_key' => 'artifact_'.($row['artifact_key'] ?? $id),
                'title' => (string) $row['name'],
                'teaser' => 'A relic hums beyond the veil of recognition…',
                'body' => trim((string) ($row['description'] ?? '')) ?: 'Sages inscribed this artifact with intent older than mortal kingdoms.',
                'icon' => '✦',
                'source_type' => 'artifact',
                'source_id' => $id,
                'source_ref' => null,
                'unlock_hint' => 'Obtain this artifact.',
                'sort_order' => $id,
                'meta' => ['rarity' => (string) ($row['rarity'] ?? 'common')],
            ]);
        }

        return count($rows);
    }

    private function syncRegions(): int
    {
        if (! Schema::hasTable('world_regions')) {
            return 0;
        }

        $rows = PdoDatabase::connection()->query('SELECT id, name, description, difficulty, resource_type FROM world_regions ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $this->upsert([
                'category' => 'regions',
                'entry_key' => 'region_'.$id,
                'title' => (string) $row['name'],
                'teaser' => 'Uncharted winds whisper from a distant land…',
                'body' => trim((string) ($row['description'] ?? '')) ?: 'Explorers speak of resources and peril woven together in this region.',
                'icon' => '🗺️',
                'source_type' => 'region',
                'source_id' => $id,
                'source_ref' => null,
                'unlock_hint' => 'Explore or chart this region.',
                'sort_order' => $id,
                'meta' => [
                    'difficulty' => (int) ($row['difficulty'] ?? 1),
                    'resource_type' => (string) ($row['resource_type'] ?? ''),
                ],
            ]);
        }

        return count($rows);
    }

    private function syncManuals(): int
    {
        if (! Schema::hasTable('cultivation_manuals')) {
            return 0;
        }

        $rows = PdoDatabase::connection()->query('SELECT id, manual_key, name, description, rarity, source_type FROM cultivation_manuals WHERE is_custom = 0 ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $this->upsert([
                'category' => 'manuals',
                'entry_key' => 'manual_'.($row['manual_key'] ?? $id),
                'title' => (string) $row['name'],
                'teaser' => 'Hidden script strokes await a worthy reader…',
                'body' => trim((string) ($row['description'] ?? '')) ?: 'This manual encodes techniques refined by forgotten masters.',
                'icon' => '📜',
                'source_type' => 'manual',
                'source_id' => $id,
                'source_ref' => null,
                'unlock_hint' => 'Acquire this cultivation manual.',
                'sort_order' => $id,
                'meta' => [
                    'rarity' => (string) ($row['rarity'] ?? 'common'),
                    'source_type' => (string) ($row['source_type'] ?? ''),
                ],
            ]);
        }

        return count($rows);
    }

    private function syncSects(): int
    {
        if (! Schema::hasTable('sects')) {
            return 0;
        }

        $rows = PdoDatabase::connection()->query('SELECT id, name, description, tier FROM sects ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $this->upsert([
                'category' => 'sects',
                'entry_key' => 'sect_'.$id,
                'title' => (string) $row['name'],
                'teaser' => 'A sect banner stirs in memory you do not yet hold…',
                'body' => trim((string) ($row['description'] ?? '')) ?: 'Disciples gather beneath this banner to cultivate, scheme, and war across the realms.',
                'icon' => '🏛️',
                'source_type' => 'sect',
                'source_id' => $id,
                'source_ref' => null,
                'unlock_hint' => 'Join or uncover this sect.',
                'sort_order' => $id,
                'meta' => ['tier' => (string) ($row['tier'] ?? 'third')],
            ]);
        }

        return count($rows);
    }

    private function syncMonsters(): int
    {
        $count = 0;

        if (Schema::hasTable('npcs')) {
            $rows = PdoDatabase::connection()->query('SELECT id, name, realm_id, level FROM npcs ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($rows as $row) {
                $id = (int) $row['id'];
                $this->upsertMonsterEntry('npc', $id, (string) $row['name'], (int) ($row['level'] ?? 1), 'Defeat this foe in PvE combat.');
                $count++;
            }
        }

        if (Schema::hasTable('dungeons')) {
            $rows = PdoDatabase::connection()->query('SELECT id, boss_name, difficulty FROM dungeons ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($rows as $row) {
                $id = (int) $row['id'];
                $name = (string) $row['boss_name'];
                $this->upsertMonsterEntry('dungeon_boss', $id, $name, (int) ($row['difficulty'] ?? 1), 'Vanquish this dungeon sovereign.');
                $count++;
            }
        }

        if (Schema::hasTable('world_boss_templates')) {
            $rows = PdoDatabase::connection()->query('SELECT id, name FROM world_boss_templates ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($rows as $row) {
                $id = (int) $row['id'];
                $this->upsertMonsterEntry('world_boss', $id, (string) $row['name'], 99, 'Challenge this world-shaking entity.');
                $count++;
            }
        }

        if (Schema::hasTable('world_regions')) {
            $regions = PdoDatabase::connection()->query('SELECT exploration_encounters FROM world_regions')->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $seen = [];
            foreach ($regions as $region) {
                foreach ($this->parseEncounterNames((string) ($region['exploration_encounters'] ?? '')) as $name) {
                    $key = 'encounter_'.$this->slug($name);
                    if (isset($seen[$key])) {
                        continue;
                    }
                    $seen[$key] = true;
                    $this->upsert([
                        'category' => 'monsters',
                        'entry_key' => $key,
                        'title' => $name,
                        'teaser' => 'Tracks in the mist—something hunts there…',
                        'body' => 'Explorers report clashes with '.$name.' along remote trails. Survivors describe sudden ambushes and qi signatures that do not match common bestiary scrolls.',
                        'icon' => '👹',
                        'source_type' => 'encounter',
                        'source_id' => null,
                        'source_ref' => $name,
                        'unlock_hint' => 'Encounter this foe while exploring.',
                        'sort_order' => 500 + count($seen),
                        'meta' => ['encounter_name' => $name],
                    ]);
                    $count++;
                }
            }
        }

        return $count;
    }

    private function upsertMonsterEntry(string $sourceType, int $sourceId, string $name, int $level, string $unlockHint): void
    {
        $this->upsert([
            'category' => 'monsters',
            'entry_key' => $sourceType.'_'.$sourceId,
            'title' => $name,
            'teaser' => 'A hostile presence stains the qi nearby…',
            'body' => $name.' is catalogued among threats cultivators face in the jianghu. Veterans advise studying its rhythm before engaging.',
            'icon' => '👹',
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'source_ref' => $this->slug($name),
            'unlock_hint' => $unlockHint,
            'sort_order' => $sourceId,
            'meta' => ['level' => $level],
        ]);
    }

    /**
     * @return list<string>
     */
    private function parseEncounterNames(string $csv): array
    {
        $parts = array_map('trim', explode(',', $csv));

        return array_values(array_filter($parts, static fn (string $n): bool => $n !== ''));
    }

    private function syncHistory(): int
    {
        $static = [
            [
                'entry_key' => 'history_cosmic_origins',
                'title' => 'Before the First Breath',
                'teaser' => 'When chaos had not yet learned its name…',
                'body' => 'Archivists teach that the Upper Realms coalesced when primordial qi split into heaven, earth, and the marrow between. Cultivators are heirs to that fracture—each breakthrough a whisper back toward unity.',
                'unlock_hint' => 'Witness your first recorded dao event.',
            ],
            [
                'entry_key' => 'history_heavenly_tribunal',
                'title' => 'The Heavenly Tribunal',
                'teaser' => 'Judges of lightning write verdicts in scar…',
                'body' => 'The Tribunal does not speak in words. It answers ambition with tribulation—fire, void, and heart-demons made manifest. Those who endure inscribe their names into eras; those who fail become cautionary verse.',
                'unlock_hint' => 'Survive or face tribulation.',
            ],
            [
                'entry_key' => 'history_era_cycles',
                'title' => 'Cycles of Eras',
                'teaser' => 'Seasons of heaven turn like a great seal…',
                'body' => 'Each era ends with the folding of rankings and sect banners. Legends persist in the Hall while mortals begin again beneath a new sky. The Codex remembers what flesh forgets.',
                'unlock_hint' => 'Leave a mark upon an era.',
            ],
            [
                'entry_key' => 'history_fallen_sects',
                'title' => 'Fallen Sects',
                'teaser' => 'Ruins still chant their broken oaths…',
                'body' => 'When ambition outpaces foundation, sects crumble into ruin sites rich with manuals and malice. Treasure hunters and righteous disciples alike pilgrimage there—few return unchanged.',
                'unlock_hint' => 'Explore ruins or ancient battlefields.',
            ],
        ];

        $milestones = [
            'first_exploration' => [
                'title' => 'First Steps Beyond the Gate',
                'teaser' => 'Your sandals remember foreign soil…',
                'body' => 'The world map ceases to be rumor. Each exploration etches your spirit into the land\'s memory—and the Codex answers in kind.',
            ],
            'first_tribulation' => [
                'title' => 'Heaven\'s Gaze Upon You',
                'teaser' => 'Lightning learned your name…',
                'body' => 'Tribulation is not punishment but measurement. The heavens test whether your dao is noise or music.',
            ],
            'first_manual' => [
                'title' => 'Ink That Breathes',
                'teaser' => 'Characters rearrange when you blink…',
                'body' => 'A cultivation manual is a contract between author and reader. Break it, and the text turns to ash; honor it, and meridians sing.',
            ],
            'first_dungeon' => [
                'title' => 'Depths Claim Their Due',
                'teaser' => 'Torches die long before courage…',
                'body' => 'Dungeons are scars in the world where qi pools and predators nest. To emerge is to prove your story worth recording.',
            ],
            'world_boss_witness' => [
                'title' => 'When the Sky Bleeds',
                'teaser' => 'A shadow eclipsed the sect banners…',
                'body' => 'World bosses are calamities given flesh. Whether you strike or merely endure, heaven notes your presence.',
            ],
        ];

        $order = 1;
        foreach ($static as $row) {
            $this->upsert([
                'category' => 'history',
                'entry_key' => $row['entry_key'],
                'title' => $row['title'],
                'teaser' => $row['teaser'],
                'body' => $row['body'],
                'icon' => '📖',
                'source_type' => 'static',
                'source_id' => null,
                'source_ref' => $row['entry_key'],
                'unlock_hint' => $row['unlock_hint'],
                'sort_order' => $order++,
                'meta' => null,
            ]);
        }

        foreach ($milestones as $key => $row) {
            $this->upsert([
                'category' => 'history',
                'entry_key' => 'history_'.$key,
                'title' => $row['title'],
                'teaser' => $row['teaser'],
                'body' => $row['body'],
                'icon' => '📖',
                'source_type' => 'milestone',
                'source_id' => null,
                'source_ref' => $key,
                'unlock_hint' => 'Live through this deed to inscribe the archive.',
                'sort_order' => 50 + $order++,
                'meta' => ['milestone' => $key],
            ]);
        }

        $eraCount = 0;
        if (Schema::hasTable('eras')) {
            $rows = PdoDatabase::connection()->query('SELECT id, name, description FROM eras ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($rows as $row) {
                $id = (int) $row['id'];
                $this->upsert([
                    'category' => 'history',
                    'entry_key' => 'era_'.$id,
                    'title' => 'Era: '.(string) $row['name'],
                    'teaser' => 'A chapter of heaven not yet committed to memory…',
                    'body' => trim((string) ($row['description'] ?? '')) ?: 'This era shaped sect standings and cultivator legends across the realms.',
                    'icon' => '📅',
                    'source_type' => 'era',
                    'source_id' => $id,
                    'source_ref' => null,
                    'unlock_hint' => 'Participate during this era.',
                    'sort_order' => 100 + $id,
                    'meta' => ['era_id' => $id],
                ]);
            }
            $eraCount = count($rows);
        }

        return count($static) + count($milestones) + $eraCount;
    }

    private function slug(string $value): string
    {
        return Str::slug($value, '_');
    }

    /**
     * @param  list<string>  $desired
     * @return list<string>
     */
    private function columns(string $table, array $desired): array
    {
        $cols = array_values(array_filter($desired, static fn (string $c): bool => Schema::hasColumn($table, $c)));
        if ($cols === []) {
            return ['id'];
        }

        return $cols;
    }
}
