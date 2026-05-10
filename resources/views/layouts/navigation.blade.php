<nav x-data="{ open: false }" class="border-b border-white/10 bg-black/25 backdrop-blur-xl">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('game.hub') }}" class="flex items-center gap-2 transition-opacity duration-200 hover:opacity-90">
                        <x-application-logo class="block h-9 w-auto fill-current text-amber-400/90 drop-shadow-[0_0_8px_rgba(251,191,36,0.25)]" />
                    </a>
                </div>

                <div class="hidden space-x-6 sm:-my-px sm:ms-10 sm:flex sm:items-center">
                    <x-nav-link :href="route('game.hub')" :active="request()->routeIs('game.hub')">
                        {{ __('Realm') }}
                    </x-nav-link>
                </div>
            </div>

            <div class="hidden sm:flex sm:items-center sm:ms-6">
                <x-dropdown align="right" width="48" contentClasses="py-1 bg-slate-900/95 border border-white/10 backdrop-blur-xl">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-sm font-medium text-violet-100 transition-all duration-200 hover:border-amber-400/25 hover:bg-white/10 focus:outline-none focus:ring-2 focus:ring-cyan-500/30">
                            <span>{{ Auth::user()->username }}</span>
                            <svg class="ms-2 h-4 w-4 text-amber-300/80" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile.edit')">
                            {{ __('Profile') }}
                        </x-dropdown-link>

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <x-dropdown-link :href="route('logout')"
                                    onclick="event.preventDefault();
                                                this.closest('form').submit();">
                                {{ __('Log Out') }}
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>

            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center rounded-lg border border-white/10 p-2 text-violet-200 transition duration-200 hover:bg-white/10 focus:outline-none focus:ring-2 focus:ring-cyan-500/30">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden border-t border-white/5 bg-black/30">
        <div class="pt-2 pb-3 space-y-1 px-2">
            <x-responsive-nav-link :href="route('game.hub')" :active="request()->routeIs('game.hub')">
                {{ __('Realm') }}
            </x-responsive-nav-link>
        </div>

        <div class="pt-4 pb-3 border-t border-white/10">
            <div class="px-4">
                <div class="font-medium text-base text-violet-100">{{ Auth::user()->username }}</div>
                <div class="text-sm text-slate-400">{{ Auth::user()->email }}</div>
            </div>

            <div class="mt-3 space-y-1 px-2">
                <x-responsive-nav-link :href="route('profile.edit')">
                    {{ __('Profile') }}
                </x-responsive-nav-link>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <x-responsive-nav-link :href="route('logout')"
                            onclick="event.preventDefault();
                                        this.closest('form').submit();">
                        {{ __('Log Out') }}
                    </x-responsive-nav-link>
                </form>
            </div>
        </div>
    </div>
</nav>
