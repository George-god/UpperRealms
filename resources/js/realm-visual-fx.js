/**
 * Ambient realm particles — uses global RealmFX.spawnParticles when available.
 * Reads [data-realm-fx-root] tier for palette and spawn rate.
 */
(function () {
    'use strict';

    var TIER_PRESETS = {
        qi: { colors: ['#e0f2fe', '#93c5fd', '#c4b5fd'], interval: 3200, count: 2 },
        foundation: { colors: ['#4ade80', '#22c55e', '#86efac'], interval: 2600, count: 3 },
        core: { colors: ['#fb923c', '#f87171', '#fdba74'], interval: 2000, count: 4 },
        nascent: { colors: ['#c4b5fd', '#a78bfa', '#e9d5ff'], interval: 1700, count: 3 },
        transformation: { colors: ['#38bdf8', '#818cf8', '#f472b6'], interval: 1400, count: 5 },
        immortal: { colors: ['#fde047', '#fbbf24', '#fcd34d', '#fef3c7'], interval: 1100, count: 6 },
    };

    var DAO_BOOST = {
        fire: { colors: ['#f97316', '#ef4444'], intervalMul: 0.85 },
        water: { colors: ['#38bdf8', '#0ea5e9'], intervalMul: 1.05 },
        demonic: { colors: ['#7c3aed', '#4c1d95'], intervalMul: 0.9 },
        righteous: { colors: ['#fef08a', '#e2e8f0'], intervalMul: 1.1 },
    };

    function prefersReducedMotion() {
        return window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    }

    function mergePalettes(base, dao) {
        var preset = DAO_BOOST[dao];
        if (!preset || !preset.colors) {
            return base.colors;
        }
        return base.colors.concat(preset.colors);
    }

    function intervalFor(base, dao) {
        var ms = base.interval;
        var preset = DAO_BOOST[dao];
        if (preset && preset.intervalMul) {
            ms = Math.round(ms * preset.intervalMul);
        }
        return Math.max(700, ms);
    }

    function tick(root, host, state) {
        var fx = window.RealmFX;
        if (!fx || typeof fx.spawnParticles !== 'function') {
            return;
        }
        var tier = root.getAttribute('data-realm-fx-tier') || 'qi';
        var dao = root.getAttribute('data-realm-fx-dao') || '';
        var base = TIER_PRESETS[tier] || TIER_PRESETS.qi;
        var colors = mergePalettes(base, dao);
        var n = base.count;
        try {
            fx.spawnParticles(host, colors, n);
        } catch (e) {
            /* ignore */
        }
    }

    function startRoot(root) {
        if (!root || prefersReducedMotion()) {
            return;
        }
        var host = root.querySelector('.realm-fx-particles-host');
        if (!host) {
            return;
        }
        var tier = root.getAttribute('data-realm-fx-tier') || 'qi';
        var dao = root.getAttribute('data-realm-fx-dao') || '';
        var base = TIER_PRESETS[tier] || TIER_PRESETS.qi;
        var ms = intervalFor(base, dao);
        tick(root, host, {});
        window.setInterval(function () {
            tick(root, host, {});
        }, ms);
    }

    function init() {
        document.querySelectorAll('[data-realm-fx-root]').forEach(startRoot);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
