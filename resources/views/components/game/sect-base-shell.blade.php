{{--
  Reference sect base shell (header + grid + aside).
  Live classic page: legacy/pages/sect_base.php
--}}
@props(['sectName' => null])

<div class="rounded-2xl border border-emerald-500/20 bg-[rgba(20,25,45,0.75)] p-6 backdrop-blur-xl">
    @if ($sectName)
        <h2 class="text-lg font-semibold text-emerald-200">{{ $sectName }}</h2>
    @endif
    <p class="text-sm text-slate-500">{{ __('Sect base shell — use legacy sect_base.php.') }}</p>
</div>
