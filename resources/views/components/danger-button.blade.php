<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center rounded-xl border border-red-500/50 bg-red-600/85 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white shadow-[0_0_14px_-4px_rgba(239,68,68,0.5)] transition duration-200 hover:bg-red-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-400 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-950 disabled:opacity-40']) }}>
    {{ $slot }}
</button>
