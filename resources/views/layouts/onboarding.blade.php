<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('Enter the realm') }} — {{ config('app.name', 'Upper Realms') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;600;700&family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700&display=swap" rel="stylesheet">
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css'])
    @else
        <link rel="stylesheet" href="{{ asset('css/realm-animations.css') }}">
        <script src="https://cdn.tailwindcss.com"></script>
        <script>
            tailwind.config = { theme: { extend: { fontFamily: { cinzel: ['Cinzel', 'serif'], dm: ['DM Sans', 'sans-serif'] } } } };
        </script>
    @endif
    <style>
        .font-cinzel { font-family: "Cinzel", ui-serif, Georgia, serif; }
        .font-dm { font-family: "DM Sans", ui-sans-serif, system-ui, sans-serif; }
    </style>
</head>
<body class="font-dm min-h-full bg-[#050810] antialiased text-slate-200">
    <div class="pointer-events-none fixed inset-0 overflow-hidden" aria-hidden="true">
        <div class="absolute -left-1/4 top-0 h-[60vh] w-[70vw] rounded-full bg-indigo-600/10 blur-[100px]"></div>
        <div class="absolute -right-1/4 bottom-0 h-[50vh] w-[60vw] rounded-full bg-violet-700/10 blur-[90px]"></div>
        <div class="absolute left-1/2 top-1/3 h-px w-[120%] -translate-x-1/2 bg-gradient-to-r from-transparent via-indigo-500/20 to-transparent"></div>
    </div>
    <div class="relative z-10 min-h-full">
        @yield('content')
    </div>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/js/app.js'])
    @endif
</body>
</html>
