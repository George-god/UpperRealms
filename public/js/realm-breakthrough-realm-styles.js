/**
 * Realm-specific breakthrough cinematic plans (modular, no backend).
 * Each transition has a named planner function; resolve() picks one from realmId + success.
 *
 * Optional: dao { element, alignment }, bloodlineBreakthroughBonus (0–1 float).
 */
(function (global) {
    'use strict';

    /** @param {number} realmId current DB realm id (after success = new realm) */
    function resolveArrivalRealmId(realmId, success) {
        var rid = parseInt(realmId, 10);
        if (isNaN(rid) || rid < 1) {
            rid = 1;
        }
        var arrival = success ? rid : rid + 1;
        if (arrival < 2) {
            arrival = 2;
        }
        return Math.min(arrival, 99);
    }

    function styleKeyFromArrival(arrival) {
        if (arrival <= 2) {
            return 'qiFoundation';
        }
        if (arrival === 3) {
            return 'foundationCore';
        }
        if (arrival === 4) {
            return 'coreNascent';
        }
        if (arrival === 5) {
            return 'nascentSoul';
        }
        return 'immortal';
    }

    /**
     * Qi → Foundation: calm, soft, low intensity
     * @param {{ success: boolean }} ctx
     */
    function planQiFoundation(ctx) {
        var ok = ctx.success;
        return {
            key: 'qiFoundation',
            bodyStyleClass: 'realm-bt-cine-style-calm',
            layerStyleClass: 'realm-bt-style-calm',
            ringWrapClass: 'realm-bt-rings--calm',
            buildMs: ok ? 2600 : 2200,
            buildSound: 'cultivate',
            lightningStrikes: ok ? 0 : 1,
            strikeSound: 'crit',
            shakeOnStrike: [false],
            afterStrikeLightShake: ok,
            particleBurst: ok
                ? [
                      { colors: ['#a5f3fc', '#e0f2fe', '#ddd6fe'], count: 12 },
                      { preset: 'cultivation', count: 10 },
                  ]
                : [{ preset: 'combat', count: 10 }, { colors: ['#bae6fd'], count: 6 }],
            climaxGlow: ok ? 'breakthroughGlow' : 'damageFlash',
            orbExplodeClass: 'realm-bt-orb-explode--calm',
            injectVortex: false,
            vortexClass: '',
        };
    }

    /**
     * Foundation → Core: fire, medium
     */
    function planFoundationCore(ctx) {
        var ok = ctx.success;
        return {
            key: 'foundationCore',
            bodyStyleClass: 'realm-bt-cine-style-fire',
            layerStyleClass: 'realm-bt-style-fire',
            ringWrapClass: 'realm-bt-rings--fire',
            buildMs: ok ? 2100 : 1900,
            buildSound: 'cultivate',
            lightningStrikes: ok ? 1 : 2,
            strikeSound: 'crit',
            shakeOnStrike: [false, true],
            afterStrikeLightShake: !ok,
            particleBurst: ok
                ? [
                      { colors: ['#fb923c', '#f97316', '#fcd34d'], count: 22 },
                      { preset: 'aura', count: 12 },
                  ]
                : [{ preset: 'combat', count: 18 }, { colors: ['#ea580c', '#9a3412'], count: 12 }],
            climaxGlow: ok ? 'breakthroughGlow' : 'damageFlash',
            orbExplodeClass: 'realm-bt-orb-explode--fire',
            injectVortex: false,
            vortexClass: '',
        };
    }

    /**
     * Core → Nascent Soul: lightning, strong shake, dark overlay
     */
    function planCoreNascent(ctx) {
        var ok = ctx.success;
        return {
            key: 'coreNascent',
            bodyStyleClass: 'realm-bt-cine-style-lightning',
            layerStyleClass: 'realm-bt-style-lightning',
            ringWrapClass: 'realm-bt-rings--lightning',
            buildMs: ok ? 1900 : 1700,
            buildSound: 'cultivate',
            lightningStrikes: ok ? 2 : 3,
            strikeSound: 'crit',
            shakeOnStrike: [true, true, true],
            afterStrikeLightShake: false,
            particleBurst: ok
                ? [
                      { colors: ['#e0e7ff', '#a5b4fc', '#38bdf8'], count: 26 },
                      { preset: 'cultivation', count: 14 },
                  ]
                : [{ preset: 'combat', count: 22 }, { colors: ['#94a3b8', '#cbd5e1'], count: 14 }],
            climaxGlow: ok ? 'breakthroughGlow' : 'damageFlash',
            orbExplodeClass: 'realm-bt-orb-explode--lightning',
            injectVortex: false,
            vortexClass: '',
        };
    }

    /**
     * Nascent → Soul Transformation: distortion, purple vortex
     */
    function planNascentSoul(ctx) {
        var ok = ctx.success;
        return {
            key: 'nascentSoul',
            bodyStyleClass: 'realm-bt-cine-style-vortex',
            layerStyleClass: 'realm-bt-style-vortex',
            ringWrapClass: 'realm-bt-rings--vortex',
            buildMs: ok ? 2000 : 1800,
            buildSound: 'cultivate',
            lightningStrikes: ok ? 2 : 2,
            strikeSound: 'crit',
            shakeOnStrike: [true, false],
            afterStrikeLightShake: true,
            particleBurst: ok
                ? [
                      { colors: ['#c084fc', '#a78bfa', '#6366f1'], count: 28 },
                      { preset: 'aura', count: 16 },
                  ]
                : [{ preset: 'combat', count: 20 }, { colors: ['#7c3aed', '#4c1d95'], count: 14 }],
            climaxGlow: ok ? 'critFlash' : 'damageFlash',
            orbExplodeClass: 'realm-bt-orb-explode--vortex',
            injectVortex: true,
            vortexClass: 'realm-bt-vortex--purple',
        };
    }

    /**
     * Immortal realm+: golden, cosmic
     */
    function planImmortal(ctx) {
        var ok = ctx.success;
        return {
            key: 'immortal',
            bodyStyleClass: 'realm-bt-cine-style-immortal',
            layerStyleClass: 'realm-bt-style-immortal',
            ringWrapClass: 'realm-bt-rings--immortal',
            buildMs: ok ? 2200 : 2000,
            buildSound: 'cultivate',
            lightningStrikes: ok ? 2 : 2,
            strikeSound: 'crit',
            shakeOnStrike: [true, true],
            afterStrikeLightShake: ok,
            particleBurst: ok
                ? [
                      { colors: ['#fde68a', '#fcd34d', '#fef3c7', '#fbbf24'], count: 36 },
                      { preset: 'aura', count: 18 },
                  ]
                : [{ preset: 'combat', count: 16 }, { colors: ['#f59e0b', '#b45309'], count: 14 }],
            climaxGlow: ok ? 'breakthroughGlow' : 'damageFlash',
            orbExplodeClass: 'realm-bt-orb-explode--immortal',
            injectVortex: true,
            vortexClass: 'realm-bt-vortex--gold',
        };
    }

    var PLANNERS = {
        qiFoundation: planQiFoundation,
        foundationCore: planFoundationCore,
        coreNascent: planCoreNascent,
        nascentSoul: planNascentSoul,
        immortal: planImmortal,
    };

    function applyDao(plan, dao) {
        if (!dao) {
            return;
        }
        if (dao.element) {
            var el = String(dao.element).toLowerCase();
            var tint = null;
            if (el.indexOf('fire') >= 0) {
                tint = ['#fb7185', '#fdba74'];
            } else if (el.indexOf('water') >= 0 || el.indexOf('ice') >= 0) {
                tint = ['#38bdf8', '#7dd3fc'];
            } else if (el.indexOf('wood') >= 0 || el.indexOf('life') >= 0) {
                tint = ['#4ade80', '#86efac'];
            } else if (el.indexOf('metal') >= 0 || el.indexOf('gold') >= 0) {
                tint = ['#e5e7eb', '#fde047'];
            } else if (el.indexOf('earth') >= 0) {
                tint = ['#d6d3d1', '#fbbf24'];
            }
            if (tint && plan.particleBurst) {
                plan.particleBurst.push({ colors: tint, count: 8 });
            }
        }
        if (dao.alignment) {
            var al = String(dao.alignment).toLowerCase();
            if (al.indexOf('chaos') >= 0 || al.indexOf('demonic') >= 0) {
                if (plan.particleBurst) {
                    plan.particleBurst.push({ colors: ['#7c3aed', '#4c1d95'], count: 6 });
                }
            } else if (al.indexOf('order') >= 0 || al.indexOf('law') >= 0) {
                if (plan.particleBurst) {
                    plan.particleBurst.push({ colors: ['#e2e8f0', '#cbd5e1'], count: 5 });
                }
            }
        }
    }

    function applyBloodline(plan, bonus) {
        var b = parseFloat(bonus);
        if (isNaN(b) || b <= 0) {
            return;
        }
        var n = Math.min(22, Math.max(6, Math.round(8 + b * 40)));
        if (!plan.particleBurst) {
            plan.particleBurst = [];
        }
        plan.particleBurst.push({ colors: ['#fda4af', '#fb7185', '#fecdd3'], count: n });
        plan.bloodlineBoost = true;
    }

    /**
     * @param {number} realmId
     * @param {boolean} success
     * @param {{ element?: string, alignment?: string }|null} [dao]
     * @param {number} [bloodlineBonus] 0..1
     */
    function buildPlan(realmId, success, dao, bloodlineBonus) {
        var arrival = resolveArrivalRealmId(realmId, success);
        var key = styleKeyFromArrival(arrival);
        var planner = PLANNERS[key] || planQiFoundation;
        var plan = planner({ success: !!success });
        applyDao(plan, dao);
        applyBloodline(plan, bloodlineBonus);
        return plan;
    }

    global.RealmBreakthroughRealmStyles = {
        resolveArrivalRealmId: resolveArrivalRealmId,
        styleKeyFromArrival: styleKeyFromArrival,
        buildPlan: buildPlan,
        planQiFoundation: planQiFoundation,
        planFoundationCore: planFoundationCore,
        planCoreNascent: planCoreNascent,
        planNascentSoul: planNascentSoul,
        planImmortal: planImmortal,
    };
})(window);
