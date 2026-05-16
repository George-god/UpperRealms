<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Upper Realms') }}</title>

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;600;700&family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&display=swap" rel="stylesheet">

        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @else
            <link rel="stylesheet" href="{{ asset('css/realm-animations.css') }}">
            <link rel="stylesheet" href="{{ asset('css/realm-visual-fx.css') }}">
            <link rel="stylesheet" href="{{ asset('css/dungeon-combat.css') }}">
            <script src="https://cdn.tailwindcss.com"></script>
            <script>
                tailwind.config = {
                    theme: {
                        extend: {
                            fontFamily: {
                                cinzel: ['Cinzel', 'serif'],
                                dm: ['DM Sans', 'sans-serif'],
                            },
                        },
                    },
                };
            </script>
            <script src="{{ asset('js/realm-sounds.js') }}" defer></script>
            <script src="{{ asset('js/realm-particles.js') }}" defer></script>
            <script src="{{ asset('js/realm-visual-fx.js') }}" defer></script>
        @endif

        <style>
            [x-cloak] { display: none !important; }
            .font-cinzel { font-family: "Cinzel", ui-serif, Georgia, serif; }
            .font-dm { font-family: "DM Sans", ui-sans-serif, system-ui, sans-serif; }
        </style>
    </head>
    <body class="font-dm min-h-full bg-[#0B0F1A] antialiased text-slate-200">
        @auth
            @php($uFx = auth()->user())
            <x-realm-visual-fx
                :realm-id="(int) $uFx->realm_id"
                :dao-flair="data_get($uFx, 'dao_element') ?? data_get($uFx, 'dao_alignment')"
                :bloodline-flair="data_get($uFx, 'bloodline_affinity') ?? data_get($uFx, 'bloodline_type')"
            />
        @endauth
        <div class="relative z-10 flex min-h-screen" x-data="{ sidebarOpen: false }" @keydown.window.escape="sidebarOpen = false">
            @include('layouts.partials.sidebar')

            <div class="flex min-w-0 flex-1 flex-col">
                @include('layouts.partials.topbar', ['breadcrumbs' => $breadcrumbs ?? []])

                <main class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8">
                    {{ $slot }}
                </main>
            </div>
        </div>
        @stack('scripts')
    </body>
</html>
