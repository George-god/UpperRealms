/**
 * Animated PvE encounter playback for world map exploration (same log format as PvEBattleService).
 */
(function (global) {
    'use strict';

    function formatNum(n) {
        return Number(n).toLocaleString();
    }

    function setBar(el, current, max) {
        if (!el) {
            return;
        }
        var pct = max > 0 ? Math.min(100, (current / max) * 100) : 0;
        el.style.width = pct + '%';
    }

    function setCombatActionsLocked(prefix, locked) {
        var wrap = document.getElementById(prefix + 'combat-actions');
        if (!wrap) return;
        var btns = wrap.querySelectorAll('.realm-combat-action');
        for (var i = 0; i < btns.length; i++) {
            btns[i].disabled = locked;
        }
        wrap.setAttribute('aria-busy', locked ? 'true' : 'false');
    }

    function flashCard(el, cls) {
        if (!el) return;
        el.classList.remove('realm-combat-hit-player', 'realm-combat-hit-enemy');
        void el.offsetWidth;
        el.classList.add(cls);
        window.setTimeout(function () {
            el.classList.remove(cls);
        }, 480);
    }

    function playAudio(name) {
        if (global.RealmAudio && typeof global.RealmAudio.play === 'function') {
            global.RealmAudio.play(name);
        }
    }

    function realmFx(method) {
        var fx = global.RealmFX;
        if (!fx || typeof fx[method] !== 'function') {
            return;
        }
        return fx[method].apply(fx, Array.prototype.slice.call(arguments, 1));
    }

    function flashHpBar(barEl) {
        if (!barEl || !barEl.parentElement) return;
        var w = barEl.parentElement;
        w.classList.remove('realm-damage-flash');
        void w.offsetWidth;
        w.classList.add('realm-damage-flash');
        window.setTimeout(function () {
            w.classList.remove('realm-damage-flash');
        }, 360);
    }

    function damagePopup(container, text, colorClass) {
        if (!container) return;
        var el = document.createElement('span');
        el.className = 'realm-float-dmg text-lg ' + (colorClass || 'text-red-400');
        el.textContent = text;
        container.appendChild(el);
        window.setTimeout(function () {
            if (el.parentNode) el.parentNode.removeChild(el);
        }, 900);
    }

    /**
     * @param {string} prefix e.g. 'explore-'
     * @param {object} data encounter payload from ExplorationService
     * @param {function(): void} [onComplete]
     */
    global.playExploreEncounterBattle = function (prefix, data, onComplete) {
        prefix = prefix || 'explore-';
        var battlePanel = document.getElementById(prefix + 'battle-panel');
        var battleLog = document.getElementById(prefix + 'battle-log');
        var battleResult = document.getElementById(prefix + 'battle-result');
        var battleChiDisplay = document.getElementById(prefix + 'battle-chi-display');
        var battleChiValue = document.getElementById(prefix + 'battle-chi-value');
        var battleNpcName = document.getElementById(prefix + 'battle-npc-name');
        var battleNpcLabel = document.getElementById(prefix + 'battle-npc-label');
        var battleUserBar = document.getElementById(prefix + 'battle-user-bar');
        var battleNpcBar = document.getElementById(prefix + 'battle-npc-bar');
        var battleUserText = document.getElementById(prefix + 'battle-user-text');
        var battleNpcText = document.getElementById(prefix + 'battle-npc-text');
        var playerCard = document.getElementById(prefix + 'battle-player-card');
        var enemyCard = document.getElementById(prefix + 'battle-enemy-card');
        var userStaminaBar = document.getElementById(prefix + 'battle-user-stamina-bar');
        var enemyPressureBar = document.getElementById(prefix + 'battle-enemy-pressure-bar');
        var userBuffs = document.getElementById(prefix + 'battle-user-buffs');
        var enemyBuffs = document.getElementById(prefix + 'battle-enemy-buffs');

        if (battlePanel) {
            battlePanel.classList.remove('hidden');
            battlePanel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
        setCombatActionsLocked(prefix, true);
        if (battleNpcName) {
            battleNpcName.textContent = data.npc_name || 'Enemy';
        }
        if (battleNpcLabel) {
            battleNpcLabel.textContent = data.npc_name || 'Enemy';
        }
        if (battleLog) {
            battleLog.innerHTML = '';
        }
        if (userBuffs) userBuffs.innerHTML = '';
        if (enemyBuffs) enemyBuffs.innerHTML = '';
        if (battleResult) {
            battleResult.classList.add('hidden');
            battleResult.textContent = '';
            battleResult.className =
                'mt-3 hidden rounded-xl border border-indigo-500/20 bg-indigo-950/30 px-4 py-3 text-center text-sm font-semibold';
        }
        if (battleChiDisplay) {
            battleChiDisplay.classList.add('hidden');
        }
        if (userStaminaBar) userStaminaBar.style.width = '100%';
        if (enemyPressureBar) enemyPressureBar.style.width = '100%';

        var log = data.battle_log || [];
        var userMax = data.user_max_chi || 1;
        var npcMax = data.npc_hp_max || 1;
        var delay = 420;
        var idx = 0;

        function finishBattle() {
            setCombatActionsLocked(prefix, false);
            var winner = data.winner;
            var msg =
                winner === 'user'
                    ? 'Victory! +' + formatNum(data.chi_reward || 0) + ' Chi.'
                    : 'Defeat.';
            if (winner === 'user' && (data.gold_gained || 0) > 0) {
                msg += ' Gold +' + formatNum(data.gold_gained);
            }
            if (winner === 'user' && (data.spirit_stone_gained || 0) > 0) {
                msg += ' Spirit stones +' + formatNum(data.spirit_stone_gained);
            }
            if (winner === 'user' && data.dropped_item) {
                var it = data.dropped_item;
                msg +=
                    ' Dropped: ' +
                    (it.name || 'Item') +
                    ' (+' +
                    (it.attack_bonus || 0) +
                    ' ATK, +' +
                    (it.defense_bonus || 0) +
                    ' DEF, +' +
                    (it.hp_bonus || 0) +
                    ' HP).';
            }
            if (winner === 'user' && data.herb_dropped) {
                msg += ' Herb: ' + (data.herb_dropped.name || 'Herb') + '.';
            }
            if (winner === 'user' && data.material_dropped) {
                msg += ' Material: ' + (data.material_dropped.name || 'Material') + '.';
            }
            if (winner === 'user' && data.rune_fragment_dropped) {
                msg += ' Rune Fragment.';
            }
            if (battleResult) {
                battleResult.classList.remove('hidden');
                battleResult.textContent = msg;
                battleResult.className =
                    'mt-3 rounded-xl border px-4 py-3 text-center text-sm font-semibold ' +
                    (winner === 'user'
                        ? 'border-emerald-500/35 bg-emerald-950/25 text-emerald-200'
                        : 'border-red-500/35 bg-red-950/25 text-red-200');
            }
            if (battleChiDisplay && battleChiValue) {
                battleChiDisplay.classList.remove('hidden');
                battleChiValue.textContent = formatNum(data.user_chi_after || 0);
            }
            if (winner === 'user') {
                playAudio('success');
            }
            if (typeof onComplete === 'function') {
                onComplete();
            }
        }

        function next() {
            if (idx >= log.length) {
                finishBattle();
                return;
            }
            var entry = log[idx];
            var userChi = entry.user_chi;
            var npcHp = entry.npc_hp;
            setBar(battleUserBar, userChi, userMax);
            setBar(battleNpcBar, npcHp, npcMax);
            if (battleUserText) {
                battleUserText.textContent = formatNum(userChi) + ' / ' + formatNum(userMax);
            }
            if (battleNpcText) {
                battleNpcText.textContent = formatNum(npcHp) + ' / ' + formatNum(npcMax);
            }

            var turnFrac = log.length > 0 ? 1 - idx / Math.max(1, log.length - 1) : 1;
            if (userStaminaBar) {
                userStaminaBar.style.width = Math.max(25, turnFrac * 100) + '%';
            }
            if (enemyPressureBar) {
                enemyPressureBar.style.width = Math.max(20, (npcHp / npcMax) * 100) + '%';
            }

            var line = document.createElement('div');
            line.className = 'realm-combat-log-line mb-1.5 text-slate-300';

            if (entry.attacker === 'user') {
                flashCard(enemyCard, 'realm-combat-hit-enemy');
                flashHpBar(battleNpcBar);
                playAudio(entry.technique_name ? 'crit' : 'hit');
                if (enemyCard) {
                    if (entry.technique_name) {
                        realmFx('spawnPreset', enemyCard, 'combat', 14);
                        realmFx('spawnPreset', enemyCard, 'aura', 12);
                        realmFx('critFlash');
                        realmFx('screenShake', true);
                    } else {
                        realmFx('spawnPreset', enemyCard, 'combat', 11);
                        realmFx('screenShake', false);
                    }
                }
                damagePopup(enemyCard, '-' + formatNum(entry.damage || 0), 'text-sky-300');
                if (enemyBuffs) {
                    enemyBuffs.innerHTML =
                        '<span class="rounded border border-red-500/35 bg-red-950/40 px-1.5 py-0.5 text-[10px] text-red-200/90">Struck</span>';
                }
                if (entry.technique_name && userBuffs) {
                    userBuffs.innerHTML =
                        '<span class="rounded border border-cyan-500/40 bg-cyan-950/50 px-1.5 py-0.5 text-[10px] text-cyan-200" title="' +
                        entry.technique_name +
                        '">⚔ ' +
                        entry.technique_name +
                        '</span>';
                } else if (userBuffs && !entry.technique_name) {
                    userBuffs.innerHTML =
                        '<span class="rounded border border-indigo-500/35 bg-indigo-950/40 px-1.5 py-0.5 text-[10px] text-indigo-200">Strike</span>';
                }
                if (entry.technique_name) {
                    line.innerHTML =
                        '<span class="text-indigo-300/80">Turn ' +
                        entry.turn +
                        ':</span> You weave <span class="font-medium text-cyan-300">' +
                        entry.technique_name +
                        '</span> for <span class="font-bold text-red-300">' +
                        formatNum(entry.damage) +
                        '</span> damage.';
                } else {
                    line.innerHTML =
                        '<span class="text-indigo-300/80">Turn ' +
                        entry.turn +
                        ':</span> You strike for <span class="font-bold text-red-300">' +
                        formatNum(entry.damage) +
                        '</span> damage.';
                }
            } else if (entry.action_type === 'dodge') {
                line.innerHTML =
                    '<span class="text-indigo-300/80">Turn ' +
                    entry.turn +
                    ':</span> You <span class="text-emerald-300">evade</span> ' +
                    (data.npc_name || 'Enemy') +
                    "'s attack.";
                if (userBuffs) {
                    userBuffs.innerHTML =
                        '<span class="rounded border border-emerald-500/35 bg-emerald-950/40 px-1.5 py-0.5 text-[10px] text-emerald-200">Evade</span>';
                }
            } else {
                flashCard(playerCard, 'realm-combat-hit-player');
                flashHpBar(battleUserBar);
                playAudio('hit');
                if (playerCard) {
                    realmFx('spawnPreset', playerCard, 'combat', 14);
                }
                realmFx('damageFlash');
                realmFx('screenShake', false);
                damagePopup(playerCard, '-' + formatNum(entry.damage || 0), 'text-red-400');
                if (userBuffs) {
                    userBuffs.innerHTML =
                        '<span class="rounded border border-orange-500/35 bg-orange-950/35 px-1.5 py-0.5 text-[10px] text-orange-200">Hit</span>';
                }
                if (enemyBuffs) {
                    enemyBuffs.innerHTML =
                        '<span class="rounded border border-amber-500/35 bg-amber-950/40 px-1.5 py-0.5 text-[10px] text-amber-200">Assault</span>';
                }
                line.innerHTML =
                    '<span class="text-indigo-300/80">Turn ' +
                    entry.turn +
                    ':</span> ' +
                    (data.npc_name || 'Enemy') +
                    ' tears into you for <span class="font-bold text-red-300">' +
                    formatNum(entry.damage) +
                    '</span> damage.';
            }
            if (battleLog) {
                battleLog.appendChild(line);
                battleLog.scrollTop = battleLog.scrollHeight;
            }
            idx += 1;
            setTimeout(next, delay);
        }

        if (log.length === 0) {
            finishBattle();
        } else {
            next();
        }
    };
})(window);
