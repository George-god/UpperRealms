<x-app-layout>
    <x-slot name="header">
        <span class="font-cinzel tracking-wide text-amber-100/95">{{ __('Dashboard') }}</span>
    </x-slot>

    <div class="mx-auto max-w-3xl">
        <x-ui.card glow="indigo" padding="p-6 sm:p-8">
            <p class="text-slate-300">{{ __("You're logged in!") }}</p>
            <p class="mt-3 text-sm text-slate-500">
                {{ __('Your cultivation hall lives on the realm dashboard.') }}
            </p>
            <div class="mt-6">
                <x-ui.button :href="route('game.hub')">
                    {{ __('Open realm dashboard') }}
                </x-ui.button>
            </div>
        </x-ui.card>
    </div>
</x-app-layout>
