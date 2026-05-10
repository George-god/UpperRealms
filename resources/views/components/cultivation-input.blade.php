@props([
    'label' => null,
    'id',
    'name',
    'type' => 'text',
    'value' => null,
    'required' => false,
    'autofocus' => false,
    'autocomplete' => null,
    'placeholder' => '',
])

@php
    $inputId = $id;
@endphp

<div class="group/input">
    @if ($label)
        <label for="{{ $inputId }}" class="mb-1.5 block text-sm font-medium text-violet-200/90">
            {{ $label }}
        </label>
    @endif
    <div class="relative">
        @isset($icon)
            <span class="pointer-events-none absolute inset-y-0 left-0 flex w-11 items-center justify-center text-amber-400/85 transition-colors duration-200 group-focus-within/input:text-cyan-300">
                {{ $icon }}
            </span>
        @endisset
        <input
            id="{{ $inputId }}"
            name="{{ $name }}"
            type="{{ $type }}"
            value="{{ $value }}"
            @if ($required) required @endif
            @if ($autofocus) autofocus @endif
            @if ($autocomplete !== null) autocomplete="{{ $autocomplete }}" @endif
            placeholder="{{ $placeholder }}"
            @class([
                'w-full rounded-xl border border-white/10 bg-black/25 py-3 text-slate-100 placeholder:text-slate-500 transition-all duration-200',
                'focus:border-cyan-400/45 focus:outline-none focus:ring-2 focus:ring-cyan-500/25',
                isset($icon) ? 'pl-11 pr-4' : 'px-4',
            ])
        />
    </div>
</div>
