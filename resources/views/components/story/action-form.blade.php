@props([
    'action',
    'label',
    'target' => null,
])

<form method="POST" action="{{ route('story.action') }}" {{ $attributes->class(['inline']) }}>
    @csrf
    <input type="hidden" name="action" value="{{ $action }}">
    @if ($target)
        <input type="hidden" name="target" value="{{ $target }}">
    @endif
    <button
        type="submit"
        class="rounded-xl bg-gradient-to-r from-indigo-600 to-violet-600 px-6 py-3 text-sm font-semibold text-white shadow-[0_0_24px_-8px_rgba(99,102,241,0.5)] transition hover:from-indigo-500 hover:to-violet-500"
    >
        {{ $label }}
    </button>
</form>
