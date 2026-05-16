<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $step['title'] ?? __('Prologue') }} — {{ config('app.name', 'Upper Realms') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;600;700&family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600&display=swap" rel="stylesheet">
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <link rel="stylesheet" href="{{ asset('css/realm-animations.css') }}">
        <link rel="stylesheet" href="{{ asset('css/story-intro.css') }}">
        <script src="https://cdn.tailwindcss.com"></script>
        <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.9/dist/cdn.min.js"></script>
        <script defer src="{{ asset('js/story-intro.js') }}"></script>
    @endif
    <style>
        .font-cinzel { font-family: "Cinzel", ui-serif, Georgia, serif; }
        .font-dm { font-family: "DM Sans", ui-sans-serif, system-ui, sans-serif; }
    </style>
</head>
<body class="font-dm min-h-full bg-[#050810] text-slate-200 antialiased">
    <header class="relative z-20 border-b border-indigo-500/10 px-4 py-3 sm:px-8">
        <div class="mx-auto flex max-w-4xl items-center justify-between gap-4">
            <span class="font-cinzel text-xs font-semibold tracking-[0.25em] text-emerald-300/70 uppercase">{{ $chapter ?? __('Prologue') }}</span>
            <form method="POST" action="{{ route('story.skip') }}">
                @csrf
                <button type="submit" class="text-xs text-slate-500 underline-offset-4 hover:text-slate-300 hover:underline">{{ __('Skip prologue') }}</button>
            </form>
        </div>
    </header>
    <main class="relative z-10">
        @yield('content')
    </main>
</body>
</html>
