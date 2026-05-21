@extends('layouts.story')

@php
    $theme = ($location['theme'] ?? 'forest') === 'village' ? 'village' : 'forest';
    $inspected = $flags['inspected'] ?? [];
@endphp

@section('content')
    <div
        class="story-root story-veil theme-{{ $theme }} px-4 py-8 sm:px-8"
        x-data="storyIntro(@js([
            'actionUrl' => route('story.action'),
            'csrf' => csrf_token(),
            'flashbacks' => $step['flashbacks'] ?? [],
            'dialogue' => $step['dialogue'] ?? [],
        ]))"
    >
        <div class="story-particles" x-ref="particles" data-density="{{ $theme === 'forest' ? 'high' : 'low' }}" aria-hidden="true"></div>

        <div class="relative z-10 mx-auto max-w-3xl space-y-6">
            @if (session('story_status'))
                <p class="rounded-lg border border-emerald-500/30 bg-emerald-950/30 px-4 py-2 text-sm text-emerald-200">{{ session('story_status') }}</p>
            @endif
            @if (session('story_error'))
                <p class="rounded-lg border border-red-500/30 bg-red-950/30 px-4 py-2 text-sm text-red-200">{{ session('story_error') }}</p>
            @endif

            <header class="text-center">
                <p class="text-xs uppercase tracking-[0.3em] text-slate-500">{{ $location['name'] ?? '' }}</p>
                <h1 class="font-cinzel mt-2 text-3xl font-semibold text-indigo-50">{{ $step['title'] ?? '' }}</h1>
                @if (!empty($step['atmosphere']))
                    <p class="mt-2 text-sm text-emerald-300/70">{{ $step['atmosphere'] }}</p>
                @endif
            </header>

            <section class="story-panel rounded-2xl p-6 sm:p-8 space-y-4">
                @foreach ($step['narration'] ?? [] as $line)
                    <p class="text-sm leading-relaxed text-slate-300 story-dialogue-line">{{ $line }}</p>
                @endforeach

                @if (!empty($step['flashbacks']))
                    @php $firstFlash = $step['flashbacks'][0] ?? null; @endphp
                    <div class="mt-6 rounded-xl border border-violet-500/20 bg-violet-950/20 p-4 text-center story-flashback">
                        <div class="text-3xl" x-text="flashbacks[flashIndex]?.image">{{ $firstFlash['image'] ?? '' }}</div>
                        <p class="mt-2 text-sm italic text-violet-200/90" x-text="flashbacks[flashIndex]?.text">{{ $firstFlash['text'] ?? '' }}</p>
                    </div>
                @endif

                @if (!empty($step['quest']))
                    <div class="rounded-xl border border-amber-500/25 bg-amber-950/15 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wider text-amber-300/80">{{ __('Quest') }}</p>
                        <p class="font-cinzel mt-1 text-lg text-amber-100">{{ $step['quest']['name'] }}</p>
                        <p class="mt-1 text-sm text-slate-400">{{ $step['quest']['objective'] }}</p>
                    </div>
                @endif

                @if (!empty($step['inspectables']))
                    <div class="grid gap-3 sm:grid-cols-2">
                        @foreach ($step['inspectables'] as $item)
                            @if (!empty($inspected[$item['key']]))
                                <div class="rounded-xl border border-emerald-500/40 bg-emerald-950/20 px-4 py-3 text-left">
                                    <p class="text-sm font-medium text-violet-100">{{ $item['label'] }}</p>
                                    <p class="mt-2 text-xs text-slate-400">{{ $item['text'] }}</p>
                                </div>
                            @else
                                <form method="POST" action="{{ route('story.action') }}" class="block">
                                    @csrf
                                    <input type="hidden" name="action" value="inspect">
                                    <input type="hidden" name="target" value="{{ $item['key'] }}">
                                    <button
                                        type="submit"
                                        class="w-full rounded-xl border border-slate-600/40 bg-slate-900/40 px-4 py-3 text-left transition hover:border-violet-500/40"
                                    >
                                        <p class="text-sm font-medium text-violet-100">{{ $item['label'] }}</p>
                                        <p class="mt-1 text-xs text-slate-500">{{ __('Inspect') }}</p>
                                    </button>
                                </form>
                            @endif
                        @endforeach
                    </div>
                @endif

                @if (!empty($step['dialogue']))
                    <div class="space-y-3 border-t border-slate-700/50 pt-4">
                        @foreach ($step['dialogue'] as $line)
                            @php $speaker = (string) ($line['speaker'] ?? ''); @endphp
                            <div class="story-dialogue-line">
                                @if ($speaker !== '' && $speaker !== 'narrator')
                                    <p class="text-xs font-semibold text-violet-300/90">{{ $speaker }}</p>
                                @endif
                                <p @class([
                                    'text-sm',
                                    'italic text-slate-400' => $speaker === 'narrator',
                                    'text-slate-200' => $speaker !== 'narrator',
                                ])>{{ $line['text'] ?? '' }}</p>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>

            <div class="flex flex-wrap justify-center gap-3">
                @foreach ($step['actions'] ?? [] as $action)
                    <x-story.action-form :action="$action['key']" :label="$action['label']" />
                @endforeach
            </div>
        </div>
    </div>
@endsection
