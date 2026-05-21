@props([
    'href' => null,
    'active' => false,
    'class' => '',
])

@php
    $href = $href ?? route('game.codex');
    $newCount = 0;
    if (auth()->check() && \Illuminate\Support\Facades\Schema::hasTable('user_codex_entries')) {
        $newCount = (int) \App\Models\UserCodexEntry::query()
            ->where('user_id', auth()->id())
            ->where('is_new', true)
            ->count();
    }
@endphp

<a href="{{ $href }}" {{ $attributes->merge(['class' => trim($class)]) }}>
    <span class="text-lg" aria-hidden="true">📚</span>
    <span class="flex-1">{{ $slot->isEmpty() ? __('Codex') : $slot }}</span>
    @if ($newCount > 0)
        <span class="ml-auto rounded-full bg-amber-500/20 px-2 py-0.5 text-[10px] font-semibold text-amber-200">{{ $newCount }}</span>
    @endif
</a>
