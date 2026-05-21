<?php

declare(strict_types=1);

namespace App\Services\Codex;

use App\Models\CodexEntry;
use App\Models\UserCodexEntry;
class CodexPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function buildPageData(int $userId): array
    {
        $categories = config('codex.categories', []);
        $entries = CodexEntry::query()->orderBy('category')->orderBy('sort_order')->orderBy('id')->get();
        $unlocked = UserCodexEntry::query()
            ->where('user_id', $userId)
            ->get()
            ->keyBy('codex_entry_id');

        $byCategory = [];
        $totals = [];
        $unlockedCounts = [];

        foreach ($categories as $slug => $meta) {
            $byCategory[$slug] = [];
            $totals[$slug] = 0;
            $unlockedCounts[$slug] = 0;
        }

        $list = [];
        foreach ($entries as $entry) {
            $cat = (string) $entry->category;
            if (! isset($byCategory[$cat])) {
                continue;
            }

            $totals[$cat] = ($totals[$cat] ?? 0) + 1;
            $userRow = $unlocked->get($entry->id);
            $isUnlocked = $userRow !== null;

            if ($isUnlocked) {
                $unlockedCounts[$cat] = ($unlockedCounts[$cat] ?? 0) + 1;
            }

            $item = [
                'id' => $entry->id,
                'entry_key' => $entry->entry_key,
                'category' => $cat,
                'title' => $isUnlocked ? $entry->title : __('Unknown Entry'),
                'teaser' => $isUnlocked ? ($entry->body ?? $entry->teaser) : ($entry->teaser ?? __('The archive senses knowledge you have not yet earned.')),
                'body' => $isUnlocked ? ($entry->body ?? '') : '',
                'icon' => $entry->icon ?? ($categories[$cat]['icon'] ?? '📜'),
                'unlock_hint' => $entry->unlock_hint,
                'is_unlocked' => $isUnlocked,
                'is_new' => $isUnlocked && (bool) ($userRow->is_new ?? false),
                'unlocked_at' => $isUnlocked ? optional($userRow->unlocked_at)?->toIso8601String() : null,
                'meta' => $entry->meta ?? [],
            ];

            $byCategory[$cat][] = $item;
            $list[] = $item;
        }

        $completion = [];
        foreach ($categories as $slug => $_meta) {
            $total = max(1, (int) ($totals[$slug] ?? 0));
            $done = (int) ($unlockedCounts[$slug] ?? 0);
            $completion[$slug] = [
                'unlocked' => $done,
                'total' => (int) ($totals[$slug] ?? 0),
                'percent' => (int) ($totals[$slug] ?? 0) > 0 ? (int) round(100 * $done / $total) : 0,
            ];
        }

        $grandTotal = array_sum($totals);
        $grandUnlocked = array_sum($unlockedCounts);

        return [
            'categories' => $categories,
            'by_category' => $byCategory,
            'entries' => $list,
            'completion' => $completion,
            'grand_completion' => [
                'unlocked' => $grandUnlocked,
                'total' => $grandTotal,
                'percent' => $grandTotal > 0 ? (int) round(100 * $grandUnlocked / $grandTotal) : 0,
            ],
            'new_count' => (int) UserCodexEntry::query()->where('user_id', $userId)->where('is_new', true)->count(),
        ];
    }
}
