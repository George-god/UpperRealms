@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'rounded-xl border border-emerald-500/35 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-100 backdrop-blur-sm']) }} role="status">
        {{ $status }}
    </div>
@endif
