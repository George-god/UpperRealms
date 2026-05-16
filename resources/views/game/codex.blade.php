<x-app-layout>
    <x-slot name="header">
        <span class="font-cinzel text-lg text-indigo-50">{{ __('Mystical Archive') }}</span>
    </x-slot>

    @if (!empty($migrationRequired))
        <div class="rounded-xl border border-amber-500/40 bg-amber-950/30 p-6 text-amber-100">
            <p class="font-semibold">{{ __('The Codex archives are not installed yet.') }}</p>
            <p class="mt-2 text-sm text-amber-200/80">{{ __('Run: php artisan migrate') }}</p>
        </div>
    @else
        @php
            $codexPayload = [
                'codex' => $codex,
                'reveal' => $reveal ?? [],
                'syncUrl' => route('game.codex.sync'),
                'seenUrl' => route('game.codex.seen'),
                'csrf' => csrf_token(),
            ];
        @endphp

        <div
            class="codex-root"
            x-data="codexArchive(@js($codexPayload))"
            x-cloak
        >
            <div class="mb-4 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="font-cinzel text-2xl font-semibold tracking-wide text-violet-100 sm:text-3xl">
                        {{ __('Codex of the Upper Realms') }}
                    </h1>
                    <p class="mt-1 text-sm text-slate-400">
                        {{ __('An ancient archive of realms, foes, relics, and fate.') }}
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <div
                        class="codex-progress-ring flex h-12 w-12 items-center justify-center rounded-full text-xs font-semibold text-amber-200"
                        :style="`--pct: ${grand.percent}`"
                    >
                        <span class="rounded-full bg-[#0B0F1A]/90 px-2 py-1" x-text="`${grand.percent}%`"></span>
                    </div>
                    <button
                        type="button"
                        @click="resync()"
                        class="rounded-lg border border-violet-500/30 bg-violet-950/40 px-3 py-2 text-sm text-violet-200 transition hover:border-violet-400/50 hover:bg-violet-900/30"
                    >
                        {{ __('Resonance Scan') }}
                    </button>
                    <span
                        x-show="newCount > 0"
                        class="rounded-full border border-amber-500/40 bg-amber-500/10 px-3 py-1 text-xs font-medium text-amber-200"
                        x-text="`${newCount} {{ __('new') }}`"
                    ></span>
                </div>
            </div>

            <div class="codex-archive codex-rune-border grid grid-cols-1 lg:grid-cols-12">
                <div class="codex-particles" x-ref="particles" aria-hidden="true"></div>

                {{-- Sidebar categories --}}
                <aside class="relative z-10 border-b border-violet-500/15 p-4 lg:col-span-3 lg:border-b-0 lg:border-r">
                    <p class="mb-3 font-cinzel text-xs uppercase tracking-[0.2em] text-violet-300/80">{{ __('Categories') }}</p>
                    <nav class="space-y-1">
                        <template x-for="(meta, slug) in categories" :key="slug">
                            <button
                                type="button"
                                @click="setCategory(slug)"
                                class="codex-sidebar-item codex-glass flex w-full items-center gap-3 rounded-lg border border-transparent px-3 py-2.5 text-left"
                                :class="{ 'is-active': activeCategory === slug }"
                            >
                                <span class="text-lg" x-text="meta.icon" aria-hidden="true"></span>
                                <span class="min-w-0 flex-1">
                                    <span class="block text-sm font-medium text-slate-100" x-text="meta.label"></span>
                                    <span class="block truncate text-[11px] text-slate-500" x-text="`${categoryPercent(slug)}% · ${completion[slug]?.unlocked ?? 0}/${completion[slug]?.total ?? 0}`"></span>
                                </span>
                            </button>
                        </template>
                    </nav>
                </aside>

                {{-- Entry list --}}
                <section class="relative z-10 border-b border-violet-500/15 p-4 lg:col-span-4 lg:border-b-0 lg:border-r">
                    <p class="mb-3 font-cinzel text-xs uppercase tracking-[0.2em] text-violet-300/80" x-text="categories[activeCategory]?.label ?? ''"></p>
                    <p class="mb-3 text-xs text-slate-500" x-text="categories[activeCategory]?.description ?? ''"></p>
                    <div class="max-h-[28rem] space-y-2 overflow-y-auto pr-1">
                        <template x-for="entry in filteredEntries()" :key="entry.id">
                            <button
                                type="button"
                                @click="selectEntry(entry)"
                                class="codex-entry-row codex-glass flex w-full items-start gap-3 rounded-lg border border-violet-500/10 px-3 py-3 text-left"
                                :class="{
                                    'is-selected': selectedId === entry.id,
                                    'is-locked': !entry.is_unlocked,
                                    'codex-reveal-flash': entry.is_new
                                }"
                            >
                                <span class="text-xl" x-text="entry.is_unlocked ? entry.icon : '🔒'" aria-hidden="true"></span>
                                <span class="min-w-0 flex-1">
                                    <span class="codex-entry-title block text-sm font-medium text-slate-100" x-text="entry.title"></span>
                                    <span class="mt-0.5 block line-clamp-2 text-xs text-slate-500" x-text="entry.is_unlocked ? (entry.teaser || '').slice(0, 90) : (entry.unlock_hint || '{{ __('Unknown') }}')"></span>
                                </span>
                                <span
                                    x-show="entry.is_new"
                                    class="shrink-0 rounded bg-amber-500/20 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-amber-200"
                                >{{ __('New') }}</span>
                            </button>
                        </template>
                        <p x-show="filteredEntries().length === 0" class="text-sm text-slate-500">{{ __('No entries in this category yet.') }}</p>
                    </div>
                </section>

                {{-- Detail panel --}}
                <section class="relative z-10 p-4 lg:col-span-5">
                    <template x-if="selectedEntry()">
                        <article class="codex-detail-panel codex-glass codex-rune-border rounded-xl border border-violet-500/20 p-5">
                            <div class="mb-4 flex items-start gap-3">
                                <span class="text-3xl" x-text="selectedEntry().is_unlocked ? selectedEntry().icon : '🔒'"></span>
                                <div>
                                    <h2 class="font-cinzel text-xl text-violet-50" x-text="selectedEntry().title"></h2>
                                    <p class="mt-1 text-xs text-slate-500" x-show="!selectedEntry().is_unlocked" x-text="selectedEntry().unlock_hint"></p>
                                    <p class="mt-1 text-xs text-emerald-400/90" x-show="selectedEntry().is_unlocked && selectedEntry().unlocked_at">
                                        {{ __('Inscribed') }}
                                        <span x-text="selectedEntry().unlocked_at ? new Date(selectedEntry().unlocked_at).toLocaleString() : ''"></span>
                                    </p>
                                </div>
                            </div>
                            <div class="prose prose-invert max-w-none text-sm leading-relaxed text-slate-300">
                                <p x-show="!selectedEntry().is_unlocked" class="italic text-slate-500" x-text="selectedEntry().teaser"></p>
                                <p x-show="selectedEntry().is_unlocked" x-text="selectedEntry().body || selectedEntry().teaser"></p>
                            </div>
                        </article>
                    </template>
                    <p x-show="!selectedEntry()" class="text-sm text-slate-500">{{ __('Select an entry to read its inscription.') }}</p>
                </section>
            </div>

            {{-- New entry toasts --}}
            <div class="pointer-events-none fixed bottom-6 right-6 z-50 flex flex-col gap-2">
                <template x-for="toast in toasts" :key="toast.id">
                    <div class="codex-notification pointer-events-auto codex-glass flex items-center gap-3 rounded-lg border border-amber-500/40 px-4 py-3 shadow-lg shadow-amber-900/20">
                        <span class="text-xl" x-text="toast.icon ?? '📜'"></span>
                        <div>
                            <p class="text-xs uppercase tracking-wider text-amber-300/80">{{ __('Archive Updated') }}</p>
                            <p class="text-sm font-medium text-amber-100" x-text="toast.title"></p>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    @endif
</x-app-layout>
