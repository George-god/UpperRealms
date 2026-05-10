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

<div class="group/input">
    @if ($label)
        <label for="{{ $id }}" class="mb-1.5 block text-sm font-medium text-indigo-100/90">
            {{ $label }}
        </label>
    @endif
    <div class="relative">
        @isset($icon)
            <span class="pointer-events-none absolute inset-y-0 left-0 flex w-11 items-center justify-center text-amber-400/90 realm-transition group-focus-within/input:text-sky-300">
                {{ $icon }}
            </span>
        @endisset
        <input
            id="{{ $id }}"
            name="{{ $name }}"
            type="{{ $type }}"
            value="{{ $value }}"
            @if ($required) required @endif
            @if ($autofocus) autofocus @endif
            @if ($autocomplete !== null) autocomplete="{{ $autocomplete }}" @endif
            placeholder="{{ $placeholder }}"
            @class([
                'w-full rounded-xl border border-indigo-500/20 bg-[#0B0F1A]/80 py-3 text-slate-100 placeholder:text-slate-500 realm-transition',
                'hover:border-indigo-400/35',
                'focus:border-indigo-400/55 focus:outline-none focus:ring-2 focus:ring-indigo-500/35 focus:shadow-[0_0_20px_-6px_rgba(99,102,241,0.45)]',
                isset($icon) ? 'pl-11 pr-4' : 'px-4',
            ])
        />
    </div>
</div>
