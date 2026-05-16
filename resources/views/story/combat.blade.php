@extends('layouts.story')

@section('content')
    <div
        class="story-root story-veil theme-forest px-4 py-8 sm:px-8"
        x-data="storyIntro(@js([
            'actionUrl' => route('story.action'),
            'csrf' => csrf_token(),
        ]))"
    >
        <div class="story-particles" x-ref="particles" aria-hidden="true"></div>
        <div class="relative z-10 mx-auto max-w-2xl space-y-6 text-center">
            <h1 class="font-cinzel text-3xl text-red-200/90">{{ $step['title'] ?? '' }}</h1>
            @foreach ($step['narration'] ?? [] as $line)
                <p class="text-sm text-slate-300">{{ $line }}</p>
            @endforeach
            @foreach ($step['dialogue'] ?? [] as $line)
                <p class="text-sm text-violet-200"><span class="font-semibold">{{ $line['speaker'] }}:</span> {{ $line['text'] }}</p>
            @endforeach
            <template x-if="combatLog">
                <div class="story-panel rounded-xl p-4 text-left text-xs text-slate-400 max-h-40 overflow-y-auto space-y-1">
                    <template x-for="(line, i) in (combatLog?.battle_log ?? [])" :key="i">
                        <p x-text="typeof line === 'string' ? line : (line?.message ?? '')"></p>
                    </template>
                </div>
            </template>
            <x-story.action-form
                action="combat"
                :label="__('Face the Corrupted Spirit Wolf')"
                class="[&_button]:rounded-xl [&_button]:bg-gradient-to-r [&_button]:from-red-800 [&_button]:to-red-600 [&_button]:px-8 [&_button]:py-3 [&_button]:shadow-lg"
            />
        </div>
    </div>
@endsection
