@props([
    'padding' => 'p-8 sm:p-10',
])

<div {{ $attributes->merge([
    'class' => 'relative overflow-hidden rounded-[1.25rem] border border-white/10 bg-white/[0.07] shadow-[0_0_40px_-10px_rgba(139,92,246,0.35)] backdrop-blur-xl '.$padding,
]) }}>
    <div class="pointer-events-none absolute -right-20 -top-20 h-40 w-40 rounded-full bg-amber-500/10 blur-3xl" aria-hidden="true"></div>
    <div class="pointer-events-none absolute -bottom-16 -left-16 h-36 w-36 rounded-full bg-cyan-500/10 blur-3xl" aria-hidden="true"></div>
    <div class="relative z-10">
        {{ $slot }}
    </div>
</div>
