@extends('layouts.story')

@section('content')
    <div
        class="story-root story-veil theme-forest flex min-h-[70vh] items-center justify-center px-4"
        x-data="storyIntro(@js(['actionUrl' => route('story.action'), 'csrf' => csrf_token()]))"
    >
        <div class="story-particles" x-ref="particles" aria-hidden="true"></div>
        <div class="relative z-10 max-w-xl text-center space-y-8">
            @foreach ($step['cinematic']['lines'] ?? [] as $line)
                <p class="story-cinematic-text font-cinzel text-xl text-indigo-100 sm:text-2xl">{{ $line }}</p>
            @endforeach
            <p class="text-sm tracking-[0.35em] uppercase text-amber-300/90">{{ $step['cinematic']['subtitle'] ?? '' }}</p>
            @foreach ($step['actions'] ?? [] as $action)
                <x-story.action-form
                    :action="$action['key']"
                    :label="$action['label']"
                    class="[&_button]:border [&_button]:border-amber-500/40 [&_button]:bg-amber-950/30 [&_button]:text-amber-100 [&_button]:hover:bg-amber-900/40 [&_button]:from-transparent [&_button]:to-transparent [&_button]:bg-none"
                />
            @endforeach
        </div>
    </div>
@endsection
