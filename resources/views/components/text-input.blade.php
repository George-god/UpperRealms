@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'rounded-xl border-white/10 bg-black/30 text-slate-100 shadow-sm transition duration-200 placeholder:text-slate-500 focus:border-cyan-400/45 focus:outline-none focus:ring-2 focus:ring-cyan-500/25 border']) }}>
