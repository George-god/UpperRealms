<x-guest-layout>
    <div class="mb-6 text-center">
        <h1 class="font-cinzel text-xl font-semibold text-amber-100/95 sm:text-2xl">
            {{ __('Reset your seal') }}
        </h1>
        <p class="mt-2 text-sm text-indigo-200/75">{{ __('Choose a new password to continue your cultivation.') }}</p>
    </div>

    <form method="POST" action="{{ route('password.store') }}" class="space-y-5">
        @csrf

        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <div>
            <x-ui.input
                label="{{ __('Email') }}"
                id="email"
                name="email"
                type="email"
                :value="old('email', $request->email)"
                required
                autofocus
                autocomplete="username"
            >
                <x-slot name="icon">
                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                    </svg>
                </x-slot>
            </x-ui.input>
            <x-ui.alert variant="error" class="mt-2" :messages="$errors->get('email')" />
        </div>

        <div>
            <x-ui.input
                label="{{ __('Password') }}"
                id="password"
                name="password"
                type="password"
                required
                autocomplete="new-password"
            >
                <x-slot name="icon">
                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                    </svg>
                </x-slot>
            </x-ui.input>
            <x-ui.alert variant="error" class="mt-2" :messages="$errors->get('password')" />
        </div>

        <div>
            <x-ui.input
                label="{{ __('Confirm Password') }}"
                id="password_confirmation"
                name="password_confirmation"
                type="password"
                required
                autocomplete="new-password"
            >
                <x-slot name="icon">
                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </x-slot>
            </x-ui.input>
            <x-ui.alert variant="error" class="mt-2" :messages="$errors->get('password_confirmation')" />
        </div>

        <div class="flex justify-end pt-2">
            <x-ui.button class="min-w-[10rem]">
                {{ __('Reset Password') }}
            </x-ui.button>
        </div>
    </form>
</x-guest-layout>
