/**
 * Lightweight DOM particles + full-screen FX (no assets, no deps).
 *
 * API:
 *   spawnParticles(container, color, count) — color: string or string[]
 *   RealmFX.spawnPreset(container, 'combat'|'cultivation'|'aura', count)
 *   RealmFX.screenShake(heavy?)
 *   RealmFX.damageFlash() | critFlash() | breakthroughGlow()
 *
 * Examples (after DOM ready):
 *   spawnParticles(document.getElementById('orb'), '#22d3ee', 14);
 *   RealmFX.spawnPreset(enemyCard, 'combat', 12);
 *   RealmFX.critFlash(); RealmFX.screenShake(true);
 */
(function (global) {
    'use strict';

    var MAX_PARTICLES = 40;
    var PRESETS = {
        combat: '#f87171',
        cultivation: '#22d3ee',
        aura: ['#e8b84a', '#a78bfa'],
    };

    function pickColor(color) {
        if (Array.isArray(color)) {
            return color[Math.floor(Math.random() * color.length)];
        }
        return color || PRESETS.combat;
    }

    function ensureFxRoot() {
        var el = document.getElementById('realm-screen-fx');
        if (!el) {
            el = document.createElement('div');
            el.id = 'realm-screen-fx';
            el.setAttribute('aria-hidden', 'true');
            var fill = document.createElement('div');
            fill.className = 'realm-fx-fill';
            el.appendChild(fill);
            document.body.appendChild(el);
        }
        return el;
    }

    function getFxFill() {
        var root = ensureFxRoot();
        return root.querySelector('.realm-fx-fill') || root.firstElementChild;
    }

    function runOverlayClass(className, ms) {
        var fill = getFxFill();
        if (!fill) return;
        fill.classList.remove('realm-fx-damage', 'realm-fx-crit', 'realm-fx-breakthrough');
        void fill.offsetWidth;
        fill.classList.add(className);
        window.setTimeout(function () {
            fill.classList.remove(className);
        }, ms + 80);
    }

    /**
     * @param {HTMLElement} container
     * @param {string|string[]} color CSS color or palette array
     * @param {number} [count]
     */
    function spawnParticles(container, color, count) {
        if (!container || typeof container.appendChild !== 'function') {
            return;
        }
        var n = Math.max(1, Math.min(MAX_PARTICLES, count == null ? 10 : count | 0));
        var pos = global.getComputedStyle(container).position;
        if (pos === 'static' || pos === '') {
            container.style.position = 'relative';
        }

        for (var i = 0; i < n; i++) {
            (function () {
                var p = document.createElement('div');
                p.className = 'realm-particle';
                var c = pickColor(color);
                p.style.background = c;
                p.style.boxShadow = '0 0 8px 1px ' + c;

                var angle = Math.random() * Math.PI * 2;
                var dist = 28 + Math.random() * 52;
                var dx = Math.cos(angle) * dist;
                var dy = Math.sin(angle) * dist - (10 + Math.random() * 18);
                p.style.setProperty('--dx', dx.toFixed(1) + 'px');
                p.style.setProperty('--dy', dy.toFixed(1) + 'px');
                p.style.setProperty('--dur', (0.48 + Math.random() * 0.38).toFixed(2) + 's');

                container.appendChild(p);
                global.requestAnimationFrame(function () {
                    p.classList.add('realm-particle--anim');
                });

                var removeMs = 900;
                window.setTimeout(function () {
                    if (p.parentNode) {
                        p.parentNode.removeChild(p);
                    }
                }, removeMs);
            })();
        }
    }

    function spawnPreset(container, type, count) {
        var col = PRESETS[type] || PRESETS.combat;
        spawnParticles(container, col, count);
    }

    function screenShake(heavy) {
        var h = document.documentElement;
        h.classList.remove('realm-fx-shake-light', 'realm-fx-shake-heavy');
        void h.offsetWidth;
        h.classList.add(heavy ? 'realm-fx-shake-heavy' : 'realm-fx-shake-light');
        var done = function () {
            h.classList.remove('realm-fx-shake-light', 'realm-fx-shake-heavy');
            h.removeEventListener('animationend', done);
        };
        h.addEventListener('animationend', done, { once: true });
    }

    function damageFlash() {
        runOverlayClass('realm-fx-damage', 550);
    }

    function critFlash() {
        runOverlayClass('realm-fx-crit', 480);
    }

    function breakthroughGlow() {
        runOverlayClass('realm-fx-breakthrough', 880);
    }

    global.spawnParticles = spawnParticles;

    global.RealmFX = {
        spawnParticles: spawnParticles,
        spawnPreset: spawnPreset,
        presets: PRESETS,
        screenShake: screenShake,
        damageFlash: damageFlash,
        critFlash: critFlash,
        breakthroughGlow: breakthroughGlow,
    };
})(window);
