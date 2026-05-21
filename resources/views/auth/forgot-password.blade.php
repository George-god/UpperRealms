<x-guest-layout>
    <div class="mb-6 text-center">
        <h1 class="font-cinzel text-xl font-semibold text-amber-100/95 sm:text-2xl">
            {{ __('Recover your path') }}
        </h1>
        <p class="mt-2 text-sm leading-relaxed text-indigo-200/75">
            {{ __('Forgot your password? No problem. Enter your email and we will send a link to forge a new seal.') }}
        </p>
    </div>

    @if (session('status'))
        <x-ui.alert variant="success" class="mb-6" :message="session('status')" />
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
        @csrf

        <div>
            <x-ui.input
                label="{{ __('Email') }}"
                id="email"
                name="email"
                type="email"
                :value="old('email')"
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

        <div class="flex flex-col gap-3 pt-2 sm:flex-row sm:items-center sm:justify-between">
            <a class="text-center text-sm text-indigo-300/85 underline-offset-4 realm-transition hover:text-indigo-200 hover:underline sm:text-left" href="{{ route('login') }}">
                {{ __('Back to sign in') }}
            </a>
            <x-ui.button class="sm:min-w-[12rem]">
                {{ __('Email Password Reset Link') }}
            </x-ui.button>
        </div>
    </form>
</x-guest-layout>
