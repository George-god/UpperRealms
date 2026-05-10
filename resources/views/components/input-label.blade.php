@props(['value'])

<label {{ $attributes->merge(['class' => 'block text-sm font-medium text-violet-200/90']) }}>
    {{ $value ?? $slot }}
</label>
