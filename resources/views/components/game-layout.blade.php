@props([
    'realmId' => 1,
    'title' => 'Home',
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} — {{ config('app.name', 'Upper Realms') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="{{ asset('assets/realms/realm-sprite.css') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: Inter, system-ui, sans-serif; }
        @keyframes pulse-glow {
            0%, 100% { box-shadow: 0 0 20px rgba(100, 200, 255, 0.3); }
            50% { box-shadow: 0 0 30px rgba(100, 200, 255, 0.6); }
        }
        .realm-badge { animation: pulse-glow 3s ease-in-out infinite; }
        .realm-badge-1 { background: linear-gradient(90deg, rgba(34,211,238,0.2), rgba(59,130,246,0.2)); border-color: rgba(34,211,238,0.5); color: rgb(103 232 249); }
        .realm-badge-2 { background: linear-gradient(90deg, rgba(168,85,247,0.2), rgba(139,92,246,0.2)); border-color: rgba(168,85,247,0.5); color: rgb(216 180 254); }
        .realm-badge-3 { background: linear-gradient(90deg, rgba(251,146,60,0.2), rgba(249,115,22,0.2)); border-color: rgba(251,146,60,0.5); color: rgb(253 186 116); }
        .realm-badge-4 { background: linear-gradient(90deg, rgba(236,72,153,0.2), rgba(219,39,119,0.2)); border-color: rgba(236,72,153,0.5); color: rgb(244 114 182); }
        .realm-badge-5 { background: linear-gradient(90deg, rgba(250,204,21,0.2), rgba(234,179,8,0.2)); border-color: rgba(250,204,21,0.5); color: rgb(253 224 71); }
        body.realm-1 .realm-bg-accent .realm-blur-1 { background: rgba(34, 211, 238, 0.08); }
        body.realm-1 .realm-bg-accent .realm-blur-2 { background: rgba(59, 130, 246, 0.08); }
        body.realm-2 .realm-bg-accent .realm-blur-1 { background: rgba(168, 85, 247, 0.08); }
        body.realm-2 .realm-bg-accent .realm-blur-2 { background: rgba(139, 92, 246, 0.08); }
        body.realm-3 .realm-bg-accent .realm-blur-1 { background: rgba(251, 146, 60, 0.08); }
        body.realm-3 .realm-bg-accent .realm-blur-2 { background: rgba(249, 115, 22, 0.08); }
        body.realm-4 .realm-bg-accent .realm-blur-1 { background: rgba(236, 72, 153, 0.08); }
        body.realm-4 .realm-bg-accent .realm-blur-2 { background: rgba(219, 39, 119, 0.08); }
        body.realm-5 .realm-bg-accent .realm-blur-1 { background: rgba(250, 204, 21, 0.1); }
        body.realm-5 .realm-bg-accent .realm-blur-2 { background: rgba(234, 179, 8, 0.1); }
        body.dashboard-main-page {
            background-color: #070d16;
            background-image: linear-gradient(180deg, #0a1424 0%, #070d16 28%, #060a12 100%);
        }
        .dashboard-ascension-strip {
            height: min(52vh, 560px);
            box-shadow: 0 24px 48px rgba(0, 0, 0, 0.35);
        }
        @media (min-width: 640px) {
            .dashboard-ascension-strip { height: min(58vh, 640px); }
        }
        @media (min-width: 1024px) {
            .dashboard-ascension-strip { height: min(62vh, 720px); }
        }
        .dashboard-ascension-scrim {
            background: linear-gradient(
                to bottom,
                rgba(15, 23, 42, 0.78) 0%,
                rgba(8, 47, 73, 0.42) 38%,
                rgba(49, 46, 129, 0.38) 62%,
                rgba(15, 23, 42, 0.9) 100%
            ),
            linear-gradient(to right, rgba(15, 23, 42, 0.72) 0%, rgba(15, 23, 42, 0.18) 45%, transparent 78%);
        }
        details.game-menu > summary { list-style: none; }
        details.game-menu > summary::-webkit-details-marker { display: none; }
    </style>
</head>
<body class="dashboard-main-page min-h-screen text-gray-100 realm-{{ (int) $realmId }}">
    <div class="fixed inset-0 z-[5] overflow-hidden pointer-events-none realm-bg-accent" aria-hidden="true">
        <div class="realm-blur-1 absolute top-1/4 left-1/4 w-96 h-96 rounded-full blur-3xl animate-pulse"></div>
        <div class="realm-blur-2 absolute bottom-1/4 right-1/4 w-96 h-96 rounded-full blur-3xl animate-pulse" style="animation-delay: 1s;"></div>
    </div>

    <div class="relative grid min-h-screen grid-cols-1">
        <div class="col-start-1 row-start-1 relative z-0 isolate w-full select-none pointer-events-none" aria-hidden="true">
            <div class="dashboard-ascension-strip relative w-full overflow-hidden rounded-b-2xl">
                <img
                    src="{{ asset('assets/images/dashboard-ascension-bg.jpg') }}"
                    alt=""
                    class="h-full w-full object-cover object-top"
                    width="338"
                    height="1024"
                    decoding="async"
                    onerror="this.style.display='none'"
                >
                <div class="dashboard-ascension-scrim absolute inset-0"></div>
            </div>
        </div>
        <div class="relative z-10 col-start-1 row-start-1 flex min-h-screen flex-col">
            @isset($header)
                <header class="border-b border-gray-800/80 bg-gray-900/35 backdrop-blur-md">
                    <div class="max-w-7xl mx-auto px-4 py-4">
                        {{ $header }}
                    </div>
                </header>
            @endisset
            <main class="flex-1 w-full max-w-7xl mx-auto px-4 py-6">
                {{ $slot }}
            </main>
        </div>
    </div>
</body>
</html>
