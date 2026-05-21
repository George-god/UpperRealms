@extends('layouts.onboarding')

@section('content')
    <div class="flex min-h-full flex-col" x-data="{ slide: 0 }">
        <header class="border-b border-indigo-500/10 px-4 py-4 sm:px-8">
            <div class="mx-auto flex max-w-3xl items-center justify-between gap-4">
                <span class="font-cinzel text-sm font-semibold tracking-widest text-amber-200/80">{{ config('app.name', 'Upper Realms') }}</span>
                <form method="POST" action="{{ route('onboarding.skip') }}">
                    @csrf
                    <button type="submit" class="text-xs text-slate-500 underline-offset-4 transition hover:text-slate-300 hover:underline">
                        {{ __('Skip intro') }}
                    </button>
                </form>
            </div>
        </header>

        <main class="mx-auto flex w-full max-w-3xl flex-1 flex-col justify-center px-4 py-10 sm:px-8">
            <div class="space-y-8">
                <div class="flex justify-center gap-2" role="tablist" aria-label="{{ __('Intro slides') }}">
                    @foreach (['Qi', 'Sects', 'Path'] as $i => $label)
                        <button
                            type="button"
                            @click="slide = {{ $i }}"
                            :class="slide === {{ $i }} ? 'bg-indigo-500/40 text-indigo-100 ring-1 ring-indigo-400/50' : 'bg-white/5 text-slate-500'"
                            class="rounded-full px-3 py-1 text-xs font-medium transition"
                        >
                            {{ $label }}
                        </button>
                    @endforeach
                </div>

                <div class="min-h-[14rem] rounded-2xl border border-indigo-500/20 bg-[rgba(12,16,32,0.75)] p-6 shadow-[0_0_40px_-16px_rgba(99,102,241,0.35)] backdrop-blur-md sm:p-8">
                    <div x-show="slide === 0" x-transition.opacity.duration.300ms class="space-y-4">
                        <h1 class="font-cinzel text-2xl font-semibold text-indigo-100 sm:text-3xl">{{ __('The veil is thin') }}</h1>
                        <p class="text-sm leading-relaxed text-slate-400">
                            {{ __('Qi gathers where intent is true. You are no longer merely mortal—you are a cultivator, bound to breath, sect, and the long road upward through the realms.') }}
                        </p>
                        <p class="text-sm text-slate-500">{{ __('Meditation strengthens your spirit. Trials temper your will. The jianghu remembers every step.') }}</p>
                    </div>
                    <div x-show="slide === 1" x-transition.opacity.duration.300ms class="space-y-4" style="display: none;">
                        <h1 class="font-cinzel text-2xl font-semibold text-indigo-100 sm:text-3xl">{{ __('Sects & heavens') }}</h1>
                        <p class="text-sm leading-relaxed text-slate-400">
                            {{ __('No cultivator walks alone for long. Your sect offers missions, a hall of names, and allies in the wider world. Honor the outer court before you chase inner power.') }}
                        </p>
                        <p class="text-sm text-slate-500">{{ __('The Heavenly Dao watches. World events shift fate for all—your timing will matter.') }}</p>
                    </div>
                    <div x-show="slide === 2" x-transition.opacity.duration.300ms class="space-y-4" style="display: none;">
                        <h1 class="font-cinzel text-2xl font-semibold text-indigo-100 sm:text-3xl">{{ __('Spirit Veil Forest') }}</h1>
                        <p class="text-sm leading-relaxed text-slate-400">
                            {{ __('You will awaken in a forest that should kill mortals. Memory fragments, a cautious herbalist named Lin Mei, and the first breath of qi await.') }}
                        </p>
                        <p class="text-sm font-medium text-amber-200/90">{{ __('When you are ready, step into the mist.') }}</p>
                    </div>
                </div>

                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-between">
                    <button
                        type="button"
                        @click="slide = Math.max(0, slide - 1)"
                        x-show="slide > 0"
                        class="rounded-xl border border-slate-600/50 px-4 py-3 text-sm text-slate-300 transition hover:border-slate-500 hover:bg-white/5"
                    >
                        {{ __('Back') }}
                    </button>
                    <div class="flex flex-1 flex-col gap-3 sm:flex-row sm:justify-end">
                        <button
                            type="button"
                            @click="slide = Math.min(2, slide + 1)"
                            x-show="slide < 2"
                            class="rounded-xl bg-gradient-to-r from-indigo-600 to-violet-600 px-6 py-3 text-sm font-semibold text-white shadow-[0_0_24px_-8px_rgba(99,102,241,0.6)] transition hover:from-indigo-500 hover:to-violet-500"
                        >
                            {{ __('Continue') }}
                        </button>
                        <form method="POST" action="{{ route('onboarding.begin') }}" x-show="slide === 2" class="sm:ml-auto">
                            @csrf
                            <button
                                type="submit"
                                class="w-full rounded-xl bg-gradient-to-r from-amber-600 to-amber-500 px-6 py-3 text-sm font-semibold text-slate-900 shadow-[0_0_28px_-8px_rgba(245,158,11,0.45)] transition hover:from-amber-500 hover:to-amber-400 sm:w-auto"
                            >
                                {{ __('Awaken in the forest') }}
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>
@endsection
