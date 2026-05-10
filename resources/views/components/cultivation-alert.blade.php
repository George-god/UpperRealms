@props([
    'variant' => 'info',
    'messages' => null,
    'message' => null,
])

@php
    $list = $messages;
    if ($list === null && $message !== null) {
        $list = [$message];
    }
    $list = $list ? array_filter((array) $list, fn ($m) => $m !== null && $m !== '') : [];

    $box = match ($variant) {
        'success' => 'border-emerald-500/35 bg-emerald-500/10 text-emerald-100',
        'error' => 'border-red-500/40 bg-red-500/10 text-red-100',
        default => 'border-cyan-500/35 bg-cyan-500/10 text-cyan-100',
    };
@endphp

@if (count($list) > 0)
    <div {{ $attributes->merge([
        'class' => 'rounded-xl border px-4 py-3 text-sm leading-relaxed backdrop-blur-sm '.$box,
        'role' => 'alert',
    ]) }}>
        @if (count($list) === 1)
            <p class="m-0">{{ $list[0] }}</p>
        @else
            <ul class="m-0 list-inside list-disc space-y-1">
                @foreach ($list as $item)
                    <li>{{ $item }}</li>
                @endforeach
            </ul>
        @endif
    </div>
@endif
