<x-app-layout
    :breadcrumbs="[
        ['label' => __('Dashboard'), 'href' => route('game.hub')],
        ['label' => __('Profile'), 'href' => null],
    ]"
>
    <x-slot name="header">
        <span class="font-cinzel tracking-wide text-amber-100/95">{{ __('Profile') }}</span>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-6">
        <x-ui.card glow="indigo" padding="p-6 sm:p-8">
            <div class="max-w-xl">
                @include('profile.partials.update-profile-information-form')
            </div>
        </x-ui.card>

        <x-ui.card glow="blue" padding="p-6 sm:p-8">
            <div class="max-w-xl">
                @include('profile.partials.update-password-form')
            </div>
        </x-ui.card>

        <x-ui.card glow="red" padding="p-6 sm:p-8">
            <div class="max-w-xl">
                @include('profile.partials.delete-user-form')
            </div>
        </x-ui.card>
    </div>
</x-app-layout>
