@php
    $u = auth()->user();
    $chi = (int) ($u->chi ?? 0);
    $maxChi = (int) ($u->max_chi ?? max(1, $chi));
    $chiPct = $maxChi > 0 ? min(100, (int) round(100 * $chi / $maxChi)) : 0;
@endphp

<header class="sticky top-0 z-30 flex min-h-14 shrink-0 flex-col gap-3 border-b border-indigo-500/15 bg-[rgba(20,25,45,0.75)] px-4 py-3 backdrop-blur-xl sm:px-6">
    <div class="flex items-center gap-3">
        <button
            type="button"
            class="rounded-lg p-2 text-slate-300 hover:bg-white/10 hover:text-white lg:hidden"
            @click="sidebarOpen = true"
            aria-label="{{ __('Open menu') }}"
        >
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>

        @php
            $pageTitle = config('app.name', 'Upper Realms');
            if (isset($header) && trim((string) $header) !== '') {
                $pageTitle = $header;
            }
        @endphp
        <div class="min-w-0 flex-1">
            <h1 class="truncate text-base font-semibold text-indigo-50 sm:text-lg">{!! $pageTitle !!}</h1>
        </div>

        <div class="flex items-center gap-2 sm:gap-4">
            <button
                type="button"
                data-realm-audio-toggle
                class="realm-audio-toggle realm-hover-lift shrink-0 rounded-xl border border-indigo-500/25 bg-[#0B0F1A]/50 p-2 text-base text-indigo-100 backdrop-blur-sm"
                aria-pressed="true"
                title="{{ __('Sound on') }}"
            >
                <span data-realm-audio-icon aria-hidden="true">🔊</span>
            </button>
            <div class="hidden w-36 md:block" title="{{ __('Spirit') }}">
                <div class="mb-0.5 flex justify-between text-[10px] text-slate-400">
                    <span>{{ __('Spirit') }}</span>
                    <span class="tabular-nums">{{ $chi }} / {{ $maxChi }}</span>
                </div>
                <div class="h-1.5 overflow-hidden rounded-full bg-black/40 ring-1 ring-indigo-500/20">
                    <div class="h-full rounded-full bg-gradient-to-r from-indigo-500 to-sky-400 realm-transition" style="width: {{ $chiPct }}%"></div>
                </div>
            </div>
            <div class="hidden sm:flex items-center gap-3 rounded-xl border border-indigo-500/20 bg-[#0B0F1A]/60 px-3 py-1.5">
                <div class="h-9 w-9 shrink-0 rounded-lg bg-gradient-to-br from-indigo-600 to-violet-700 text-center text-sm font-bold leading-9 text-white shadow-[0_0_12px_-4px_rgba(99,102,241,0.6)]">
                    {{ strtoupper(substr($u->username, 0, 1)) }}
                </div>
                <div class="min-w-0 text-right">
                    <p class="truncate text-sm font-semibold text-white">{{ $u->username }}</p>
                    <p class="text-[11px] text-slate-400">
                        {{ __('Lv.') }} {{ (int) $u->level }}
                        <span class="text-slate-600">·</span>
                        {{ \App\Support\RealmDisplay::forUser($u) }}
                    </p>
                </div>
            </div>
        </div>
    </div>

    <x-navigation :breadcrumbs="$breadcrumbs ?? []" class="border-t border-indigo-500/10 pt-3" />
</header>
