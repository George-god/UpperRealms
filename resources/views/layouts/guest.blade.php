<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Upper Realms') }} — {{ __('Realm') }}</title>

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;600;700&family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&display=swap" rel="stylesheet">

        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @else
            <link rel="stylesheet" href="{{ asset('css/realm-animations.css') }}">
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
        @endif

        <style>
            .font-cinzel { font-family: "Cinzel", ui-serif, Georgia, serif; }
            .font-dm { font-family: "DM Sans", ui-sans-serif, system-ui, sans-serif; }
            @keyframes realm-auth-float {
                0%, 100% { transform: translate(0, 0) scale(1); opacity: 0.35; }
                50% { transform: translate(-1.5%, 1%) scale(1.04); opacity: 0.5; }
            }
            .realm-auth-bg {
                background-color: #0B0F1A;
                background-image:
                    radial-gradient(ellipse 90% 55% at 15% 20%, rgba(99, 102, 241, 0.14), transparent),
                    radial-gradient(ellipse 70% 45% at 85% 75%, rgba(232, 184, 74, 0.08), transparent),
                    radial-gradient(ellipse 50% 40% at 50% 100%, rgba(56, 189, 248, 0.06), transparent);
            }
            .realm-auth-particles {
                animation: realm-auth-float 20s ease-in-out infinite;
            }
            .realm-auth-grid {
                background-size: 40px 40px;
                background-image: linear-gradient(rgba(99, 102, 241, 0.06) 1px, transparent 1px),
                    linear-gradient(90deg, rgba(99, 102, 241, 0.06) 1px, transparent 1px);
            }
        </style>
    </head>
    <body class="font-dm min-h-full antialiased text-slate-200 realm-auth-bg">
        <div class="pointer-events-none fixed inset-0 realm-auth-particles realm-auth-bg" aria-hidden="true"></div>
        <div class="pointer-events-none fixed inset-0 realm-auth-grid opacity-50" aria-hidden="true"></div>
        <div class="pointer-events-none fixed inset-0 bg-gradient-to-t from-[#0B0F1A] via-transparent to-indigo-950/25" aria-hidden="true"></div>

        <div class="relative z-10 flex min-h-screen flex-col items-center justify-center px-4 py-10 sm:px-6">
            <button
                type="button"
                data-realm-audio-toggle
                class="realm-audio-toggle realm-hover-lift fixed right-4 top-4 z-20 rounded-xl border border-indigo-500/30 bg-[rgba(20,25,45,0.85)] px-3 py-2 text-lg shadow-lg backdrop-blur-md"
                aria-pressed="true"
                title="{{ __('Sound on') }}"
            >
                <span data-realm-audio-icon aria-hidden="true">🔊</span>
            </button>
            <a href="{{ url('/') }}" class="group mb-8 flex flex-col items-center gap-2 transition-opacity duration-200 hover:opacity-90">
                <x-application-logo class="h-12 w-12 fill-current text-amber-400/90 drop-shadow-[0_0_14px_rgba(232,184,74,0.35)] transition-transform duration-300 group-hover:scale-105 sm:h-14 sm:w-14" />
                <span class="font-cinzel text-lg font-semibold tracking-[0.2em] text-amber-100/90 sm:text-xl">{{ config('app.name', 'Upper Realms') }}</span>
            </a>

            <x-ui.card glow="gold" class="w-full max-w-md" padding="p-6 sm:p-8">
                {{ $slot }}
            </x-ui.card>

            <p class="mt-8 max-w-md text-center text-xs text-slate-500">
                {{ __('The Dao watches. Tread carefully, cultivator.') }}
            </p>
        </div>
    </body>
</html>
