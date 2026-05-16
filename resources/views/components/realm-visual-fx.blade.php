<div
    class="realm-fx-root realm-fx--{{ $fxTier }} {{ $fxDao !== '' ? 'realm-fx--dao-'.$fxDao : '' }} {{ $fxBlood !== '' ? 'realm-fx--blood-'.$fxBlood : '' }}"
    aria-hidden="true"
    data-realm-fx-root
    data-realm-fx-tier="{{ $fxTier }}"
    data-realm-fx-realm-id="{{ (int) $realmId }}"
    @if ($fxDao !== '')
        data-realm-fx-dao="{{ $fxDao }}"
    @endif
    @if ($fxBlood !== '')
        data-realm-fx-bloodline="{{ $fxBlood }}"
    @endif
>
    <div class="realm-fx-layer realm-fx-ambient"></div>
    <div class="realm-fx-layer realm-fx-glow"></div>
    <div class="realm-fx-layer realm-fx-auras"></div>
    <div class="realm-fx-layer realm-fx-rings" data-realm-fx-rings></div>
    <div class="realm-fx-layer realm-fx-ember"></div>
    <div class="realm-fx-layer realm-fx-lightning"></div>
    <div class="realm-fx-layer realm-fx-distort"></div>
    <div class="realm-fx-layer realm-fx-shimmer"></div>
    <div class="realm-fx-layer realm-fx-cosmic"></div>
    <div class="realm-fx-layer realm-fx-rays"></div>
    <div class="realm-fx-layer realm-fx-runes"></div>
    <div class="realm-fx-particles-host"></div>
    <div class="realm-fx-layer realm-fx-dao-overlay"></div>
    <div class="realm-fx-layer realm-fx-bloodline-overlay"></div>
</div>
