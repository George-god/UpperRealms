@props([
    'breadcrumbs' => [],
    'showQuickNav' => true,
])

@php
    if (! isset($realmClassic)) {
        $realmClassic = static fn (string $p): string => route('game.classic', ['page' => str_ends_with($p, '.php') ? $p : $p.'.php']);
    }
@endphp

<nav {{ $attributes->class(['flex min-w-0 flex-col gap-2 sm:flex-row sm:items-center sm:gap-3']) }} aria-label="{{ __('Realm navigation') }}">
    <div class="flex min-w-0 flex-wrap items-center gap-2">
        <a
            href="{{ route('game.hub') }}"
            class="realm-nav-pill inline-flex items-center gap-1.5 rounded-xl border border-amber-500/30 bg-amber-950/25 px-3 py-1.5 text-xs font-semibold text-amber-100/95 shadow-[0_0_18px_-10px_rgba(245,158,11,0.45)] backdrop-blur-sm transition hover:border-amber-400/50 hover:shadow-[0_0_22px_-8px_rgba(245,158,11,0.35)] sm:text-sm"
        >
            <span aria-hidden="true">🏯</span>
            {{ __('Home') }}
        </a>
        <a
            href="{{ route('game.hub') }}"
            class="realm-nav-pill inline-flex items-center gap-1.5 rounded-xl border border-indigo-500/25 bg-[#0B0F1A]/55 px-3 py-1.5 text-xs font-semibold text-indigo-100/95 backdrop-blur-sm transition hover:border-indigo-400/45 hover:shadow-[0_0_20px_-10px_rgba(99,102,241,0.4)] sm:text-sm"
        >
            <span aria-hidden="true">✦</span>
            {{ __('Dashboard') }}
        </a>
        <a
            href="{{ $realmClassic('game.php') }}"
            class="realm-nav-pill hidden items-center gap-1.5 rounded-xl border border-indigo-500/20 bg-[#0B0F1A]/40 px-3 py-1.5 text-xs font-medium text-slate-200/90 backdrop-blur-sm transition hover:border-violet-400/35 hover:text-white md:inline-flex sm:text-sm"
        >
            <span aria-hidden="true">☯️</span>
            {{ __('Spirit hall') }}
        </a>
        @if (auth()->check() && auth()->user()->is_admin)
            <a
                href="{{ url('/game/admin/heavenly_observatory.php') }}"
                class="realm-nav-pill inline-flex items-center gap-1.5 rounded-xl border border-cyan-500/35 bg-cyan-950/25 px-3 py-1.5 text-xs font-semibold text-cyan-100/95 shadow-[0_0_18px_-10px_rgba(34,211,238,0.35)] backdrop-blur-sm transition hover:border-cyan-400/55 sm:text-sm"
            >
                <span aria-hidden="true">🔭</span>
                {{ __('Admin') }}
            </a>
        @endif
    </div>

    @if ($showQuickNav)
        <div class="flex max-w-full flex-1 flex-wrap items-center gap-1.5 overflow-x-auto pb-0.5 sm:justify-center md:px-2">
            <span class="hidden text-[10px] font-semibold uppercase tracking-wider text-slate-600 lg:inline">{{ __('Quick') }}</span>
            @foreach ([
                ['page' => 'world_map.php', 'icon' => '🧭', 'label' => __('Map')],
                ['page' => 'battles.php', 'icon' => '⚔️', 'label' => __('Combat')],
                ['page' => 'sect.php', 'icon' => '🏛️', 'label' => __('Sect')],
                ['page' => 'inventory.php', 'icon' => '🎒', 'label' => __('Inventory')],
                ['page' => 'marketplace.php', 'icon' => '🏪', 'label' => __('Market')],
                ['page' => 'artifacts.php', 'icon' => '✨', 'label' => __('Artifacts')],
                ['page' => 'bloodline.php', 'icon' => '🩸', 'label' => __('Bloodline')],
            ] as $q)
                <a
                    href="{{ $realmClassic($q['page']) }}"
                    class="realm-nav-quick inline-flex shrink-0 items-center gap-1 rounded-lg border border-transparent bg-white/[0.04] px-2 py-1 text-[11px] font-medium text-slate-300 transition hover:border-indigo-500/30 hover:bg-indigo-500/10 hover:text-amber-50 hover:shadow-[0_0_14px_-6px_rgba(99,102,241,0.45)] sm:text-xs"
                >
                    <span aria-hidden="true">{{ $q['icon'] }}</span>
                    {{ $q['label'] }}
                </a>
            @endforeach
        </div>
    @endif

    @if (count($breadcrumbs) > 0)
        <ol class="flex min-w-0 flex-wrap items-center gap-1 text-[11px] text-slate-500 sm:text-xs">
            @foreach ($breadcrumbs as $i => $crumb)
                @if ($i > 0)
                    <li class="text-slate-600" aria-hidden="true">/</li>
                @endif
                <li class="min-w-0 truncate">
                    @if (! empty($crumb['href'] ?? null))
                        <a href="{{ $crumb['href'] }}" class="text-indigo-300/90 transition hover:text-amber-200 hover:underline hover:underline-offset-2">
                            {{ $crumb['label'] }}
                        </a>
                    @else
                        <span class="font-medium text-slate-300">{{ $crumb['label'] }}</span>
                    @endif
                </li>
            @endforeach
        </ol>
    @endif
</nav>
