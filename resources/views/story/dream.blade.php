@extends('layouts.story')

@section('content')
    <div
        class="story-root story-dream-sky flex min-h-[75vh] flex-col items-center justify-center px-4 py-12"
        x-data="storyIntro(@js(['actionUrl' => route('story.action'), 'csrf' => csrf_token()]))"
    >
        <div class="story-chain w-full max-w-md mb-8"></div>
        <div class="max-w-lg text-center space-y-6">
            @foreach ($step['dream']['lines'] ?? [] as $line)
                <p class="font-cinzel text-lg text-slate-300 story-cinematic-text">{{ $line }}</p>
            @endforeach
            <p class="text-2xl font-cinzel text-amber-200/95 tracking-wide story-cinematic-text">{{ $step['dream']['voice'] ?? '' }}</p>
        </div>
        <div class="story-chain w-full max-w-md mt-8"></div>
        @foreach ($step['actions'] ?? [] as $action)
            <x-story.action-form
                :action="$action['key']"
                :label="$action['label']"
                class="mt-10 [&_button]:rounded-xl [&_button]:border [&_button]:border-indigo-500/30 [&_button]:bg-indigo-900/50 [&_button]:text-indigo-100 [&_button]:from-transparent [&_button]:to-transparent [&_button]:bg-none"
            />
        @endforeach
    </div>
@endsection
