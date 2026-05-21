<x-guest-layout>
    <div class="mb-6 text-center">
        <h1 class="font-cinzel text-xl font-semibold text-amber-100/95 sm:text-2xl">
            {{ __('Verify your soul inscription') }}
        </h1>
        <p class="mt-3 text-sm leading-relaxed text-indigo-200/75">
            {{ __('Thanks for signing up! Before you begin, please verify your email using the link we sent. If you did not receive it, we can send another.') }}
        </p>
    </div>

    @if (session('status') == 'verification-link-sent')
        <x-ui.alert variant="success" class="mb-6" :message="__('A new verification link has been sent to the email address you provided during registration.')" />
    @endif

    <div class="mt-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <form method="POST" action="{{ route('verification.send') }}" class="w-full sm:w-auto">
            @csrf
            <x-ui.button type="submit" class="w-full sm:w-auto">
                {{ __('Resend Verification Email') }}
            </x-ui.button>
        </form>

        <form method="POST" action="{{ route('logout') }}" class="w-full sm:w-auto">
            @csrf
            <x-ui.button variant="danger" type="submit" class="w-full sm:w-auto">
                {{ __('Log Out') }}
            </x-ui.button>
        </form>
    </div>
</x-guest-layout>
