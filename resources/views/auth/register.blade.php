<x-guest-layout>
    <div class="mb-8 text-center">
        <h1 class="bg-gradient-to-r from-amber-200 via-indigo-200 to-sky-200 bg-clip-text font-cinzel text-2xl font-semibold tracking-wide text-transparent sm:text-3xl">
            {{ __('Begin Your Cultivation Journey') }}
        </h1>
        <p class="mt-2 text-sm text-indigo-300/75">{{ __('Forge your legacy in the Upper Realms') }}</p>
    </div>

    <form method="POST" action="{{ route('register') }}" class="space-y-5" id="register-form">
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
                label="{{ __('Email') }}"
                id="email"
                name="email"
                type="email"
                :value="old('email')"
                required
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

            <div id="password-strength" class="mt-3 rounded-xl border border-indigo-500/15 bg-[#0B0F1A]/50 p-3 text-xs text-slate-400 realm-transition">
                <p class="mb-2 font-medium text-indigo-200/90">{{ __('Spirit seal strength') }}</p>
                <div class="mb-2 h-1.5 overflow-hidden rounded-full bg-black/40">
                    <div id="password-strength-bar" class="h-full w-0 rounded-full bg-gradient-to-r from-red-500 via-amber-400 to-emerald-400 realm-transition ease-out" style="transition-duration: 300ms"></div>
                </div>
                <ul class="m-0 space-y-1.5 p-0">
                    <li id="rule-len" class="flex items-center gap-2 realm-transition">
                        <span class="rule-dot h-1.5 w-1.5 shrink-0 rounded-full bg-slate-600"></span>
                        {{ __('At least 8 characters') }}
                    </li>
                    <li id="rule-mix" class="flex items-center gap-2 realm-transition">
                        <span class="rule-dot h-1.5 w-1.5 shrink-0 rounded-full bg-slate-600"></span>
                        {{ __('Mix of letters and numbers recommended') }}
                    </li>
                </ul>
            </div>

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

        <div class="flex flex-col gap-4 pt-2 sm:flex-row sm:items-center sm:justify-end">
            <a class="text-center text-sm text-indigo-300/85 underline-offset-4 realm-transition hover:text-indigo-200 hover:underline sm:text-left" href="{{ route('login') }}">
                {{ __('Already registered?') }}
            </a>
            <x-ui.button class="sm:min-w-[9rem]">
                {{ __('Register') }}
            </x-ui.button>
        </div>
    </form>

    <script>
        (function () {
            var pw = document.getElementById('password');
            var bar = document.getElementById('password-strength-bar');
            var ruleLen = document.getElementById('rule-len');
            var ruleMix = document.getElementById('rule-mix');
            if (!pw || !bar || !ruleLen || !ruleMix) return;

            function score(v) {
                var s = 0;
                if (v.length >= 8) s += 50;
                if (/[a-zA-Z]/.test(v) && /\d/.test(v)) s += 50;
                return Math.min(100, s);
            }

            function paintRule(el, ok) {
                el.classList.toggle('text-emerald-300/90', ok);
                el.classList.toggle('text-slate-500', !ok);
                var dot = el.querySelector('.rule-dot');
                if (dot) {
                    dot.classList.toggle('bg-emerald-400', ok);
                    dot.classList.toggle('bg-slate-600', !ok);
                }
            }

            function update() {
                var v = pw.value || '';
                var sc = score(v);
                bar.style.width = sc + '%';
                paintRule(ruleLen, v.length >= 8);
                paintRule(ruleMix, /[a-zA-Z]/.test(v) && /\d/.test(v));
            }

            pw.addEventListener('input', update);
            pw.addEventListener('change', update);
            update();
        })();
    </script>
</x-guest-layout>
