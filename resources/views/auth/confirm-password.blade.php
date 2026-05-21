<x-guest-layout>
    <div class="mb-6 text-center">
        <h1 class="font-cinzel text-xl font-semibold text-amber-100/95 sm:text-2xl">
            {{ __('Confirm your aura') }}
        </h1>
        <p class="mt-2 text-sm text-indigo-200/75">
            {{ __('This is a secure area. Confirm your password before continuing.') }}
        </p>
    </div>

    <form method="POST" action="{{ route('password.confirm') }}" class="space-y-5">
        @csrf

        <div>
            <x-ui.input
                label="{{ __('Password') }}"
                id="password"
                name="password"
                type="password"
                required
                autocomplete="current-password"
            >
                <x-slot name="icon">
                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                    </svg>
                </x-slot>
            </x-ui.input>
            <x-ui.alert variant="error" class="mt-2" :messages="$errors->get('password')" />
        </div>

        <div class="flex justify-end pt-2">
            <x-ui.button class="min-w-[8rem]">
                {{ __('Confirm') }}
            </x-ui.button>
        </div>
    </form>
</x-guest-layout>
