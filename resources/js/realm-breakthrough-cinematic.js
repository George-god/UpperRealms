/**
 * Multi-step realm breakthrough cinematic. Uses RealmFX, RealmAudio,
 * and RealmBreakthroughRealmStyles (load realm-breakthrough-realm-styles.js first).
 *
 * window.REALM_BREAKTHROUGH_CINE = { success, message?, realmId?, dao?, bloodlineBreakthroughBonus? }
 */
(function (global) {
    'use strict';

    var running = false;

    function sleep(ms) {
        return new Promise(function (resolve) {
            global.setTimeout(resolve, ms);
        });
    }

    function playAudio(name) {
        if (!name) {
            return;
        }
        if (global.RealmAudio && typeof global.RealmAudio.play === 'function') {
            global.RealmAudio.play(name);
        }
    }

    function guessRealmIdFromBody() {
        var c = document.body && document.body.className;
        if (!c || typeof c !== 'string') {
            return 2;
        }
        var m = c.match(/realm-(\d+)/);
        return m ? parseInt(m[1], 10) : 2;
    }

    function ensureLayer() {
        var el = document.getElementById('realm-bt-cine-layer');
        if (!el) {
            el = document.createElement('div');
            el.id = 'realm-bt-cine-layer';
            el.setAttribute('aria-hidden', 'true');
            el.innerHTML =
                '<div class="realm-bt-cine-scrim" aria-hidden="true"></div>' +
                '<div class="realm-bt-cine-lightning" aria-hidden="true"></div>';
            document.body.appendChild(el);
        }
        return el;
    }

    function removeVortex(layer) {
        var v = layer.querySelector('.realm-bt-vortex');
        if (v && v.parentNode) {
            v.parentNode.removeChild(v);
        }
    }

    function injectVortex(layer, vortexClass) {
        removeVortex(layer);
        if (!vortexClass) {
            return;
        }
        var bolt = layer.querySelector('.realm-bt-cine-lightning');
        var v = document.createElement('div');
        v.className = 'realm-bt-vortex ' + vortexClass;
        v.setAttribute('aria-hidden', 'true');
        if (bolt && bolt.parentNode === layer) {
            layer.insertBefore(v, bolt);
        } else {
            layer.appendChild(v);
        }
    }

    function injectEnergyRings(stage, ringWrapClass) {
        var wrap = document.createElement('div');
        wrap.className = 'realm-bt-energy-rings' + (ringWrapClass ? ' ' + ringWrapClass : '');
        wrap.innerHTML =
            '<span class="realm-bt-ring realm-bt-ring--a"></span>' +
            '<span class="realm-bt-ring realm-bt-ring--b"></span>' +
            '<span class="realm-bt-ring realm-bt-ring--c"></span>';
        stage.insertBefore(wrap, stage.firstChild);
        return wrap;
    }

    function strikeLightning(layer) {
        var bolt = layer.querySelector('.realm-bt-cine-lightning');
        if (!bolt) {
            return;
        }
        bolt.classList.remove('realm-bt-cine-lightning--strike');
        void bolt.offsetWidth;
        bolt.classList.add('realm-bt-cine-lightning--strike');
    }

    function screenShake(heavy) {
        if (global.RealmFX && typeof global.RealmFX.screenShake === 'function') {
            global.RealmFX.screenShake(!!heavy);
        }
    }

    function runParticleBurst(orb, items) {
        if (!global.RealmFX || !orb || !items) {
            return;
        }
        for (var i = 0; i < items.length; i++) {
            var b = items[i];
            if (b.preset) {
                global.RealmFX.spawnPreset(orb, b.preset, b.count);
            } else if (b.colors) {
                global.RealmFX.spawnParticles(orb, b.colors, b.count);
            }
        }
    }

    function runClimaxGlow(kind, success) {
        if (!global.RealmFX) {
            return;
        }
        if (kind === 'breakthroughGlow' && typeof global.RealmFX.breakthroughGlow === 'function') {
            global.RealmFX.breakthroughGlow();
        } else if (kind === 'critFlash' && typeof global.RealmFX.critFlash === 'function') {
            global.RealmFX.critFlash();
        } else if (kind === 'damageFlash' && typeof global.RealmFX.damageFlash === 'function') {
            global.RealmFX.damageFlash();
        } else if (success && typeof global.RealmFX.breakthroughGlow === 'function') {
            global.RealmFX.breakthroughGlow();
        } else if (!success && typeof global.RealmFX.damageFlash === 'function') {
            global.RealmFX.damageFlash();
        }
    }

    function showReveal(success, message) {
        var el = document.createElement('div');
        el.className =
            'realm-bt-reveal ' + (success ? 'realm-bt-reveal--success' : 'realm-bt-reveal--failure');
        el.setAttribute('role', 'status');
        el.setAttribute('aria-live', 'polite');
        el.textContent = message || (success ? 'Breakthrough — realm ascended!' : 'Breakthrough failed.');
        document.body.appendChild(el);
        global.setTimeout(function () {
            if (el.parentNode) {
                el.parentNode.removeChild(el);
            }
        }, 4200);
    }

    function buildPlan(opts, success) {
        var RS = global.RealmBreakthroughRealmStyles;
        if (!RS || typeof RS.buildPlan !== 'function') {
            return null;
        }
        var rid =
            opts && opts.realmId != null ? parseInt(opts.realmId, 10) : guessRealmIdFromBody();
        if (isNaN(rid)) {
            rid = guessRealmIdFromBody();
        }
        var dao = opts && opts.dao ? opts.dao : null;
        var blood =
            opts && opts.bloodlineBreakthroughBonus != null
                ? parseFloat(opts.bloodlineBreakthroughBonus)
                : 0;
        return RS.buildPlan(rid, success, dao, blood);
    }

    /**
     * @param {{ success?: boolean, message?: string, realmId?: number, dao?: object, bloodlineBreakthroughBonus?: number }} opts
     * @returns {Promise<void>}
     */
    async function play(opts) {
        if (running) {
            return;
        }
        running = true;
        var success = !opts || opts.success !== false;
        var message = (opts && opts.message) || '';
        var plan = buildPlan(opts || {}, success);

        var orb = document.getElementById('cultivation-orb');
        var stage = document.getElementById('cultivation-orb-stage');
        if (!stage && orb && orb.parentElement) {
            stage = orb.parentElement;
        }

        var layer = ensureLayer();
        var scrim = layer.querySelector('.realm-bt-cine-scrim');
        layer.classList.toggle('realm-bt-cine--failure', !success);
        document.body.classList.add('realm-bt-cine-running');
        document.body.classList.toggle('realm-bt-cine-running--fail', !success);

        if (plan && plan.bodyStyleClass) {
            document.body.classList.add(plan.bodyStyleClass);
        }
        if (plan && plan.bloodlineBoost) {
            document.body.classList.add('realm-bt-cine-bloodline');
        }
        if (plan && plan.layerStyleClass) {
            layer.classList.add(plan.layerStyleClass);
        }

        document.body.style.overflow = 'hidden';

        var ringsWrap = null;
        try {
            if (plan && plan.injectVortex) {
                injectVortex(layer, plan.vortexClass || '');
            } else {
                removeVortex(layer);
            }

            requestAnimationFrame(function () {
                layer.classList.add('realm-bt-cine--on');
                if (scrim) {
                    scrim.classList.add('realm-bt-cine-scrim--dark');
                }
            });

            await sleep(520);

            if (stage) {
                ringsWrap = injectEnergyRings(stage, plan ? plan.ringWrapClass : '');
            }

            playAudio(plan && plan.buildSound ? plan.buildSound : 'cultivate');

            await sleep(plan ? plan.buildMs : 1850);

            var strikes = plan ? plan.lightningStrikes | 0 : 2;
            var shakes = (plan && plan.shakeOnStrike) || [];
            for (var s = 0; s < strikes; s++) {
                playAudio(plan && plan.strikeSound ? plan.strikeSound : 'crit');
                strikeLightning(layer);
                var heavy = shakes[s] === true;
                screenShake(heavy);
                await sleep(strikes > 1 ? 150 : 140);
            }

            if (plan && plan.afterStrikeLightShake) {
                screenShake(false);
                await sleep(200);
            } else if (strikes === 0) {
                await sleep(200);
            }

            if (orb) {
                orb.classList.add('realm-bt-orb-cinematic');
                orb.classList.add('realm-bt-orb-explode');
                if (plan && plan.orbExplodeClass) {
                    orb.classList.add(plan.orbExplodeClass);
                }
                var burst = plan && plan.particleBurst ? plan.particleBurst : null;
                if (burst) {
                    runParticleBurst(orb, burst);
                } else if (global.RealmFX) {
                    if (success) {
                        global.RealmFX.spawnPreset(orb, 'aura', 28);
                        global.RealmFX.spawnParticles(orb, ['#fde68a', '#c4b5fd', '#22d3ee'], 12);
                    } else {
                        global.RealmFX.spawnPreset(orb, 'combat', 18);
                        global.RealmFX.spawnPreset(orb, 'aura', 8);
                        playAudio('hit');
                    }
                }
            }

            await sleep(120);
            runClimaxGlow(plan && plan.climaxGlow, success);

            await sleep(560);

            if (success) {
                playAudio('success');
            } else {
                playAudio('hit');
            }
            showReveal(success, message);

            await sleep(3200);

            layer.classList.remove('realm-bt-cine--on', 'realm-bt-cine--failure');
            if (plan && plan.layerStyleClass) {
                layer.classList.remove(plan.layerStyleClass);
            }
            if (scrim) {
                scrim.classList.remove('realm-bt-cine-scrim--dark');
            }
            removeVortex(layer);
            if (ringsWrap && ringsWrap.parentNode) {
                ringsWrap.parentNode.removeChild(ringsWrap);
            }
            if (orb) {
                orb.classList.remove('realm-bt-orb-cinematic', 'realm-bt-orb-explode');
                if (plan && plan.orbExplodeClass) {
                    orb.classList.remove(plan.orbExplodeClass);
                }
            }
            document.body.classList.remove('realm-bt-cine-running', 'realm-bt-cine-running--fail');
            if (plan && plan.bodyStyleClass) {
                document.body.classList.remove(plan.bodyStyleClass);
            }
            document.body.classList.remove('realm-bt-cine-bloodline');
            document.body.style.overflow = '';
        } finally {
            running = false;
        }
    }

    function maybeAutoPlay() {
        var cfg = global.REALM_BREAKTHROUGH_CINE;
        if (!cfg) {
            return;
        }
        delete global.REALM_BREAKTHROUGH_CINE;
        play(cfg).catch(function () {});
    }

    global.RealmBreakthroughCinematic = {
        play: play,
        guessRealmIdFromBody: guessRealmIdFromBody,
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', maybeAutoPlay);
    } else {
        maybeAutoPlay();
    }
})(window);
