<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center rounded-xl border border-amber-500/40 bg-gradient-to-r from-amber-600/90 to-amber-500/90 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-slate-950 shadow-[0_0_16px_-4px_rgba(251,191,36,0.45)] transition duration-200 hover:from-amber-500 hover:to-amber-400 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-950 disabled:opacity-40']) }}>
    {{ $slot }}
</button>
