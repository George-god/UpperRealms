<x-guest-layout>
    <div class="mb-8 text-center">
        <h1 class="bg-gradient-to-r from-amber-200 via-amber-100 to-sky-200 bg-clip-text font-cinzel text-2xl font-semibold tracking-wide text-transparent sm:text-3xl">
            {{ __('Enter the Path of Cultivation') }}
        </h1>
        <p class="mt-2 text-sm text-indigo-300/75">{{ __('Sign in to continue your ascension') }}</p>
    </div>

    @if (session('status'))
        <x-ui.alert variant="success" class="mb-6" :message="session('status')" />
    @endif

    <form method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf

        <div>
            <x-ui.input
                label="{{ __('Username') }}"
                id="username"
                name="username"
                type="text"
                :value="old('username')"
                required
                autofocus
                autocomplete="username"
            >
                <x-slot name="icon">
                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                    </svg>
                </x-slot>
            </x-ui.input>
            <x-ui.alert variant="error" class="mt-2" :messages="$errors->get('username')" />
        </div>

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

        <div class="flex items-center gap-2">
            <input id="remember_me" type="checkbox" class="h-4 w-4 rounded border-indigo-500/30 bg-[#0B0F1A]/80 text-indigo-500 shadow-sm realm-transition focus:ring-2 focus:ring-indigo-500/45 focus:ring-offset-0" name="remember">
            <label for="remember_me" class="text-sm text-indigo-200/80">{{ __('Remember me') }}</label>
        </div>

        <div class="flex flex-col gap-4 pt-2 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-sm">
                @if (Route::has('register'))
                    <a class="text-amber-300/90 underline-offset-4 realm-transition hover:text-amber-200 hover:underline" href="{{ route('register') }}">
                        {{ __('Register') }}
                    </a>
                @endif
                @if (Route::has('password.request'))
                    <a class="text-indigo-300/85 underline-offset-4 realm-transition hover:text-indigo-200 hover:underline" href="{{ route('password.request') }}">
                        {{ __('Forgot password?') }}
                    </a>
                @endif
            </div>
            <x-ui.button class="sm:min-w-[8.5rem]">
                {{ __('Log in') }}
            </x-ui.button>
        </div>
    </form>
</x-guest-layout>
