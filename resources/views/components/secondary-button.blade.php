<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center justify-center rounded-xl border border-white/15 bg-white/5 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-violet-100 transition duration-200 hover:border-amber-400/25 hover:bg-white/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-cyan-500/30 disabled:opacity-40']) }}>
    {{ $slot }}
</button>
