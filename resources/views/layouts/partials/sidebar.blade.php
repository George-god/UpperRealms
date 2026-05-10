@php
    /**
     * URL to a legacy gameplay page (served under /game/classic/{page}).
     * Defined inline: @include does not merge assignments back into the parent view.
     */
    $realmClassic = static fn (string $p): string => route('game.classic', ['page' => str_ends_with($p, '.php') ? $p : $p.'.php']);

    $realmPath = request()->path();
    $realmClassicPage = null;
    if (preg_match('#^game/(?:classic|pages)/(.+\.php)$#i', $realmPath, $m)) {
        $realmClassicPage = strtolower($m[1]);
    }

    $realmClassicActive = static function (string $page) use ($realmClassicPage): bool {
        $page = strtolower(str_ends_with($page, '.php') ? $page : $page.'.php');

        return $realmClassicPage === $page;
    };

    $navClass = static fn (bool $active): string => $active
        ? 'flex items-center gap-3 rounded-xl bg-indigo-600/25 px-3 py-2.5 text-sm font-medium text-white ring-1 ring-indigo-400/35 shadow-[0_0_18px_-10px_rgba(99,102,241,0.45)] realm-transition'
        : 'flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-slate-300 realm-transition hover:bg-white/5 hover:text-amber-100/90 hover:shadow-[0_0_16px_-12px_rgba(99,102,241,0.35)]';
@endphp

<!-- Mobile overlay -->
<div
    x-show="sidebarOpen"
    x-transition.opacity
    class="fixed inset-0 z-40 bg-black/60 backdrop-blur-sm lg:hidden"
    style="display: none;"
    @click="sidebarOpen = false"
    aria-hidden="true"
></div>

<aside
    class="fixed inset-y-0 left-0 z-50 flex w-64 -translate-x-full flex-col border-r border-indigo-500/15 bg-[rgba(20,25,45,0.92)] backdrop-blur-xl realm-transition lg:static lg:z-0 lg:translate-x-0"
    :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
>
    <div class="flex h-16 shrink-0 items-center gap-2 border-b border-indigo-500/10 px-4">
        <a href="{{ route('game.hub') }}" class="flex min-w-0 flex-1 items-center gap-2 rounded-lg outline-none ring-amber-400/0 transition hover:ring-2 hover:ring-amber-400/20 focus-visible:ring-2 focus-visible:ring-amber-400/35">
            <x-application-logo class="h-9 w-9 shrink-0 fill-current text-amber-400/90 drop-shadow-[0_0_10px_rgba(232,184,74,0.35)]" />
            <div class="min-w-0 text-left">
                <p class="truncate text-sm font-semibold tracking-wide text-amber-100/95">{{ config('app.name', 'Upper Realms') }}</p>
                <p class="text-[10px] uppercase tracking-[0.2em] text-indigo-300/60">{{ __('Cultivation') }}</p>
            </div>
        </a>
        <button
            type="button"
            class="ml-auto rounded-lg p-1.5 text-slate-400 hover:bg-white/10 hover:text-white lg:hidden"
            @click="sidebarOpen = false"
            aria-label="{{ __('Close menu') }}"
        >
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>

    <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4" aria-label="{{ __('Main navigation') }}">
        <p class="mb-2 px-3 text-[10px] font-semibold uppercase tracking-wider text-slate-500">{{ __('Realm') }}</p>
        <a href="{{ route('game.hub') }}" class="{{ $navClass(request()->routeIs('game.hub')) }}">
            <span class="text-lg" aria-hidden="true">🏯</span>
            {{ __('Dashboard') }}
        </a>
        <a href="{{ $realmClassic('game.php') }}" class="{{ $navClass($realmClassicActive('game.php')) }}">
            <span class="text-lg" aria-hidden="true">☯️</span>
            {{ __('Spirit hall') }}
        </a>
        <a href="{{ $realmClassic('world_map.php') }}" class="{{ $navClass($realmClassicActive('world_map.php')) }}">
            <span class="text-lg" aria-hidden="true">🧭</span>
            {{ __('World map') }}
        </a>

        <p class="mb-2 mt-6 px-3 text-[10px] font-semibold uppercase tracking-wider text-slate-500">{{ __('Trials') }}</p>
        <a href="{{ $realmClassic('battles.php') }}" class="{{ $navClass($realmClassicActive('battles.php')) }}">
            <span class="text-lg" aria-hidden="true">⚔️</span>
            {{ __('Combat') }}
        </a>
        <a href="{{ $realmClassic('dungeons.php') }}" class="{{ $navClass($realmClassicActive('dungeons.php')) }}">
            <span class="text-lg" aria-hidden="true">🏯</span>
            {{ __('Dungeons') }}
        </a>

        <p class="mb-2 mt-6 px-3 text-[10px] font-semibold uppercase tracking-wider text-slate-500">{{ __('Path') }}</p>
        <a href="{{ $realmClassic('character_sheet.php') }}" class="{{ $navClass($realmClassicActive('character_sheet.php')) }}">
            <span class="text-lg" aria-hidden="true">📋</span>
            {{ __('Character') }}
        </a>
        <a href="{{ $realmClassic('inventory.php') }}" class="{{ $navClass($realmClassicActive('inventory.php')) }}">
            <span class="text-lg" aria-hidden="true">🎒</span>
            {{ __('Inventory') }}
        </a>
        <a href="{{ $realmClassic('bloodline.php') }}" class="{{ $navClass($realmClassicActive('bloodline.php')) }}">
            <span class="text-lg" aria-hidden="true">🩸</span>
            {{ __('Bloodline') }}
        </a>
        <a href="{{ $realmClassic('artifacts.php') }}" class="{{ $navClass($realmClassicActive('artifacts.php')) }}">
            <span class="text-lg" aria-hidden="true">✨</span>
            {{ __('Artifacts') }}
        </a>
        <a href="{{ $realmClassic('marketplace.php') }}" class="{{ $navClass($realmClassicActive('marketplace.php')) }}">
            <span class="text-lg" aria-hidden="true">🏪</span>
            {{ __('Marketplace') }}
        </a>

        <p class="mb-2 mt-6 px-3 text-[10px] font-semibold uppercase tracking-wider text-slate-500">{{ __('Sect') }}</p>
        <a href="{{ $realmClassic('sect.php') }}" class="{{ $navClass($realmClassicActive('sect.php')) }}">
            <span class="text-lg" aria-hidden="true">🏛️</span>
            {{ __('Sect hall') }}
        </a>

        <p class="mb-2 mt-6 px-3 text-[10px] font-semibold uppercase tracking-wider text-slate-500">{{ __('Account') }}</p>
        <a href="{{ route('profile.edit') }}" class="{{ $navClass(request()->routeIs('profile.*')) }}">
            <span class="text-lg" aria-hidden="true">✦</span>
            {{ __('Profile') }}
        </a>
    </nav>

    <div class="border-t border-indigo-500/10 p-3">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-xl border border-red-500/25 bg-red-950/20 py-2.5 text-sm font-medium text-red-200/90 realm-transition hover:border-red-400/40 hover:bg-red-950/40">
                <span aria-hidden="true">⏻</span>
                {{ __('Log out') }}
            </button>
        </form>
    </div>
</aside>
