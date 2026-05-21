/**
 * Dungeon combat cinematic playback — uses existing battle_log snapshots only.
 * @see window.DungeonCombat.initFromMount('#dungeon-combat-mount')
 */
(function (global) {
    'use strict';

    var DAO_CLASSES = [
        { re: /flame|fire|inferno|burn|ember/i, cls: 'dc-dao-flame' },
        { re: /water|tide|flow|frost|ice|mist/i, cls: 'dc-dao-water' },
        { re: /wind|gale|storm|swift|blade.?wind/i, cls: 'dc-dao-wind' },
        { re: /earth|stone|mountain|quake|seal/i, cls: 'dc-dao-earth' },
        { re: /lightning|thunder|arc|volt/i, cls: 'dc-dao-lightning' },
        { re: /demon|devil|corrupt|blood|yin|ghost/i, cls: 'dc-dao-demonic' },
    ];

    function prefersReducedMotion() {
        return global.matchMedia && global.matchMedia('(prefers-reduced-motion: reduce)').matches;
    }

    function playSound(name) {
        if (global.RealmAudio && typeof global.RealmAudio.play === 'function') {
            try {
                global.RealmAudio.play(name);
            } catch (e) {}
        }
    }

    function resolveTheme(regionName, dungeonName) {
        var t = ((regionName || '') + ' ' + (dungeonName || '')).toLowerCase();
        if (/ice|frost|frozen|snow|glacier/.test(t)) return 'dc-theme--frozen-temple';
        if (/volcan|lava|magma|inferno|ash/.test(t)) return 'dc-theme--volcanic-depths';
        if (/abyss|void|cave|deep|underdark|crypt/.test(t)) return 'dc-theme--abyss-cave';
        if (/heaven|celestial|cloud|trial|divine|golden/.test(t)) return 'dc-theme--heavenly-trial';
        return 'dc-theme--ancient-ruins';
    }

    function stageTierFromName(stageName) {
        var s = (stageName || '').toLowerCase();
        if (/stage\s*3|boss\b/.test(s)) return 3;
        if (/stage\s*2|elite/.test(s)) return 2;
        return 1;
    }

    function rarityClass(tier) {
        if (tier >= 3) return 'dc-rarity-mythic';
        if (tier === 2) return 'dc-rarity-elite';
        return 'dc-rarity-common';
    }

    function bossRarityClass(tier) {
        if (tier >= 3) return 'dc-rarity-mythic';
        return 'dc-rarity-elite';
    }

    function inferDaoClass(actionType, techniqueName) {
        var blob = (actionType || '') + ' ' + (techniqueName || '');
        for (var i = 0; i < DAO_CLASSES.length; i++) {
            if (DAO_CLASSES[i].re.test(blob)) return DAO_CLASSES[i].cls;
        }
        return null;
    }

    function isHeavyHit(attacker, damage, npcHpMax) {
        if (attacker !== 'user' || !damage) return false;
        var th = Math.max(22, Math.floor((npcHpMax || 100) * 0.11));
        return damage >= th;
    }

    function initialStateFromFirstRow(log, npcMax) {
        if (!log || !log.length) return { chi: 0, npc: npcMax };
        var r = log[0];
        if (r.attacker === 'user') {
            return {
                npc: Math.min(npcMax, (r.npc_hp || 0) + (r.damage || 0)),
                chi: r.user_chi != null ? r.user_chi : 0,
            };
        }
        return {
            npc: r.npc_hp != null ? r.npc_hp : npcMax,
            chi: Math.min(npcMax, (r.user_chi || 0) + (r.damage || 0)),
        };
    }

    function maxChiFromLog(log, fallback) {
        var m = 0;
        (log || []).forEach(function (row) {
            if (row.user_chi != null) m = Math.max(m, row.user_chi);
        });
        return m > 0 ? m : fallback || 100;
    }

    function enemyEmoji(tier) {
        if (tier >= 3) return '👹';
        if (tier === 2) return '💀';
        return '⚔️';
    }

    function initFromMount(selector) {
        var mount = typeof selector === 'string' ? document.querySelector(selector) : selector;
        if (!mount) return;
        var jsonEl = mount.querySelector('script[type="application/json"][data-dungeon-battle]');
        if (!jsonEl || !jsonEl.textContent) return;
        var payload;
        try {
            payload = JSON.parse(jsonEl.textContent);
        } catch (e) {
            return;
        }
        var arena = mount.querySelector('.dc-root');
        if (!arena) return;

        var battle = payload.battle || {};
        var log = battle.battle_log || [];
        var npcMax = Math.max(1, parseInt(battle.npc_hp_max, 10) || 1);
        var npcName = battle.npc_name || 'Enemy';
        var playerName = payload.playerName || 'Cultivator';
        var stageName = payload.stage_name || 'Battle';
        var tier = stageTierFromName(stageName);
        var theme = payload.themeClass || resolveTheme(payload.regionName, payload.dungeonName);
        var isBoss = tier >= 3;
        var enemyRarity = isBoss ? bossRarityClass(tier) : rarityClass(tier);

        arena.className =
            'dc-root ' +
            theme +
            (isBoss ? ' dc-boss-intro' : '') +
            (tier >= 3 ? ' dc-mythic-ambience' : '');

        var bgPulse = arena.querySelector('.dc-bg-pulse');
        if (bgPulse) bgPulse.style.opacity = isBoss ? '0.85' : '0';

        var enemyPanel = arena.querySelector('.dc-panel--enemy');
        if (enemyPanel) {
            enemyPanel.className =
                'dc-panel dc-panel--enemy ' + enemyRarity + (isBoss ? ' dc-panel--boss' : '');
            var portrait = enemyPanel.querySelector('.dc-enemy-portrait');
            if (portrait) portrait.textContent = enemyEmoji(tier);
        }

        var floatRoot = arena.querySelector('.dc-float-root');
        var fxLayer = arena.querySelector('.dc-fx-layer');
        var flashEl = arena.querySelector('.dc-flash-overlay');
        var playerPanel = arena.querySelector('.dc-panel--player');
        var logEl = arena.querySelector('.dc-log');
        var skillStrip = arena.querySelector('.dc-skill-strip');
        var barNpc = arena.querySelector('.dc-bar-fill--enemy');
        var barPlayer = arena.querySelector('.dc-bar-fill--player');
        var valNpc = arena.querySelector('[data-dc-npc-pct]');
        var valPlayer = arena.querySelector('[data-dc-player-pct]');
        var progInner = arena.querySelector('.dc-progress > span');
        var btnPlay = arena.querySelector('[data-dc-play]');
        var btnSkip = arena.querySelector('[data-dc-skip]');

        var init = initialStateFromFirstRow(log, npcMax);
        var chiMax = Math.max(
            maxChiFromLog(log, 100),
            init.chi || 0,
            parseInt(battle.user_chi_after, 10) || 0,
            1
        );

        function setBars(npcHp, userChi) {
            var np = Math.max(0, Math.min(100, Math.round((npcHp / npcMax) * 100)));
            var cp = Math.max(0, Math.min(100, Math.round((userChi / chiMax) * 100)));
            if (barNpc) {
                barNpc.style.width = np + '%';
                barNpc.classList.remove('dc-bar-damage-flash');
                void barNpc.offsetWidth;
                barNpc.classList.add('dc-bar-damage-flash');
            }
            if (barPlayer) {
                barPlayer.style.width = cp + '%';
                barPlayer.classList.remove('dc-bar-damage-flash');
                void barPlayer.offsetWidth;
                barPlayer.classList.add('dc-bar-damage-flash');
            }
            if (valNpc) valNpc.textContent = np + '%';
            if (valPlayer) valPlayer.textContent = cp + '%';
        }

        setBars(init.npc, init.chi);

        var i = 0;
        var playing = false;
        var timer = null;
        var STEP = prefersReducedMotion() ? 40 : 520;

        function clearDaoTint() {
            DAO_CLASSES.forEach(function (d) {
                arena.classList.remove(d.cls);
            });
        }

        function spawnFloat(targetPanel, text, kind) {
            if (!floatRoot || !targetPanel) return;
            var r = targetPanel.getBoundingClientRect();
            var rootR = arena.getBoundingClientRect();
            var el = document.createElement('div');
            el.className = 'dc-float-dmg dc-float-dmg--' + (kind || 'player');
            el.textContent = text;
            var lx = r.left - rootR.left + r.width * 0.55;
            var ly = r.top - rootR.top + r.height * 0.25;
            el.style.left = lx + 'px';
            el.style.top = ly + 'px';
            floatRoot.appendChild(el);
            window.setTimeout(function () {
                if (el.parentNode) el.parentNode.removeChild(el);
            }, 1100);
        }

        function spawnStrike(x, y, spell) {
            if (!fxLayer) return;
            var s = document.createElement('div');
            s.className = 'dc-strike ' + (spell ? 'dc-strike--spell' : 'dc-strike--melee');
            s.style.left = x + 'px';
            s.style.top = y + 'px';
            fxLayer.appendChild(s);
            window.setTimeout(function () {
                if (s.parentNode) s.parentNode.removeChild(s);
            }, 600);
        }

        function shake(heavy) {
            if (prefersReducedMotion()) return;
            arena.classList.remove('dc-shaking', 'dc-shaking--heavy');
            void arena.offsetWidth;
            arena.classList.add(heavy ? 'dc-shaking--heavy' : 'dc-shaking');
            window.setTimeout(function () {
                arena.classList.remove('dc-shaking', 'dc-shaking--heavy');
            }, heavy ? 560 : 440);
        }

        function flash() {
            if (!flashEl || prefersReducedMotion()) return;
            flashEl.classList.remove('dc-flash--on');
            void flashEl.offsetWidth;
            flashEl.classList.add('dc-flash--on');
        }

        function appendLogLine(row, crit, dodge) {
            if (!logEl) return;
            var div = document.createElement('div');
            var isUser = row.attacker === 'user';
            div.className = 'dc-log-line ' + (isUser ? 'dc-log-line--user' : 'dc-log-line--npc');
            if (crit) div.classList.add('dc-log-line--crit');
            if (dodge) div.classList.add('dc-log-line--dodge');
            var who = isUser ? playerName : npcName;
            var tech = row.technique_name ? ' · ' + row.technique_name : '';
            var msg;
            if (dodge) {
                msg = who + ' evaded an attack.';
            } else {
                msg = who + ' strikes for ' + (row.damage || 0) + ' damage.' + tech;
            }
            div.textContent = msg;
            logEl.appendChild(div);
            logEl.scrollTop = logEl.scrollHeight;
        }

        function applyRow(row) {
            var npcHp = row.npc_hp != null ? row.npc_hp : init.npc;
            var userChi = row.user_chi != null ? row.user_chi : init.chi;
            setBars(npcHp, userChi);

            var isUser = row.attacker === 'user';
            var dodge = row.action_type === 'dodge' || (row.attacker === 'npc' && (row.damage || 0) === 0);
            var heavy = isHeavyHit(row.attacker, row.damage || 0, npcMax);
            var crit = !!heavy && !dodge;

            var dao = inferDaoClass(row.action_type, row.technique_name);
            clearDaoTint();
            if (dao) arena.classList.add(dao);

            var targetPanel = isUser ? enemyPanel : playerPanel;
            var actingPanel = isUser ? playerPanel : enemyPanel;
            if (actingPanel) {
                actingPanel.classList.add('dc-panel--acting');
                window.setTimeout(function () {
                    actingPanel.classList.remove('dc-panel--acting');
                }, 320);
            }
            if (targetPanel && (row.damage || 0) > 0) {
                targetPanel.classList.add('dc-panel--hit');
                window.setTimeout(function () {
                    targetPanel.classList.remove('dc-panel--hit');
                }, 380);
            }

            if (skillStrip) {
                if (isUser && row.technique_name) {
                    skillStrip.innerHTML =
                        '<strong>Technique:</strong> ' +
                        String(row.technique_name) +
                        ' <span class="text-slate-500">(' +
                        String(row.action_type || 'attack') +
                        ')</span>';
                } else if (isUser) {
                    skillStrip.innerHTML = '<strong>Strike:</strong> martial exchange';
                } else if (dodge) {
                    skillStrip.innerHTML = '<strong>Enemy:</strong> evasive movement';
                } else {
                    skillStrip.innerHTML = '<strong>Enemy:</strong> hostile technique';
                }
            }

            if (floatRoot && targetPanel) {
                var rootR = arena.getBoundingClientRect();
                var tr = targetPanel.getBoundingClientRect();
                var cx = tr.left - rootR.left + tr.width / 2;
                var cy = tr.top - rootR.top + tr.height / 2;
                spawnStrike(cx, cy, !!(dao || (row.technique_name && isUser)));

                if (dodge) {
                    spawnFloat(targetPanel, 'Dodge', 'dodge');
                } else if (row.damage > 0) {
                    var fl = document.createElement('div');
                    fl.className =
                        'dc-float-dmg ' +
                        (isUser ? 'dc-float-dmg--player' : 'dc-float-dmg--enemy') +
                        (crit ? ' dc-float-dmg--crit' : '');
                    fl.textContent = '-' + row.damage;
                    fl.style.left = cx + 'px';
                    fl.style.top = cy + 'px';
                    floatRoot.appendChild(fl);
                    window.setTimeout(function () {
                        if (fl.parentNode) fl.parentNode.removeChild(fl);
                    }, 1100);
                }
            }

            if (crit) {
                playSound('crit');
                flash();
                shake(isBoss);
            } else if ((row.damage || 0) > 0) {
                playSound('dungeon_hit');
            } else if (dodge) {
                playSound('click');
            }

            appendLogLine(row, crit, dodge);

            if (progInner) {
                progInner.style.width = Math.min(100, Math.round(((i + 1) / log.length) * 100)) + '%';
            }
        }

        function endPlayback() {
            playing = false;
            if (timer) {
                clearTimeout(timer);
                timer = null;
            }
            if (battle.winner === 'npc' && enemyPanel) {
                /* player defeated — subtle */
            } else if (battle.winner === 'user' && enemyPanel) {
                var portrait = enemyPanel.querySelector('.dc-enemy-portrait');
                if (portrait) portrait.classList.add('dc-enemy-death');
            }
            clearDaoTint();
            if (btnPlay) btnPlay.disabled = false;
            playSound('dungeon_end');
            var rwEnd = payload.rewards;
            if (
                rwEnd &&
                ((parseInt(rwEnd.gold, 10) || 0) > 0 ||
                    (parseInt(rwEnd.spirit_stones, 10) || 0) > 0 ||
                    rwEnd.manual)
            ) {
                playSound('loot');
            }
        }

        function step() {
            if (i >= log.length) {
                endPlayback();
                return;
            }
            applyRow(log[i]);
            i += 1;
            if (i < log.length) {
                timer = window.setTimeout(step, STEP);
            } else {
                timer = window.setTimeout(endPlayback, STEP * 0.4);
            }
        }

        function play() {
            if (playing || !log.length) return;
            playing = true;
            if (btnPlay) btnPlay.disabled = true;
            if (logEl) logEl.innerHTML = '';
            i = 0;
            setBars(init.npc, init.chi);
            if (progInner) progInner.style.width = '0%';
            var portrait = enemyPanel && enemyPanel.querySelector('.dc-enemy-portrait');
            if (portrait) portrait.classList.remove('dc-enemy-death');
            if (isBoss) playSound('boss_intro');
            step();
        }

        function skipAll() {
            if (timer) clearTimeout(timer);
            timer = null;
            playing = false;
            if (logEl) logEl.innerHTML = '';
            var last = log.length ? log[log.length - 1] : null;
            if (last) {
                setBars(last.npc_hp != null ? last.npc_hp : 0, last.user_chi != null ? last.user_chi : 0);
            }
            log.forEach(function (row) {
                var dodge = row.action_type === 'dodge' || (row.attacker === 'npc' && (row.damage || 0) === 0);
                var heavy = isHeavyHit(row.attacker, row.damage || 0, npcMax);
                appendLogLine(row, heavy && !dodge, dodge);
            });
            clearDaoTint();
            if (progInner) progInner.style.width = '100%';
            if (btnPlay) btnPlay.disabled = false;
            if (battle.winner === 'user' && enemyPanel) {
                var portrait = enemyPanel.querySelector('.dc-enemy-portrait');
                if (portrait) portrait.classList.add('dc-enemy-death');
            }
            playSound('dungeon_end');
            if (payload.rewards && (payload.rewards.gold > 0 || payload.rewards.spirit_stones > 0 || payload.rewards.manual)) {
                playSound('loot');
            }
        }

        if (btnPlay) btnPlay.addEventListener('click', play);
        if (btnSkip) btnSkip.addEventListener('click', skipAll);

        /* Name labels */
        var namePlayer = arena.querySelector('[data-dc-name-player]');
        var nameEnemy = arena.querySelector('[data-dc-name-enemy]');
        if (namePlayer) namePlayer.textContent = playerName;
        if (nameEnemy) nameEnemy.textContent = npcName;

        /* Auto-start gentle */
        if (!prefersReducedMotion() && log.length) {
            window.setTimeout(play, 400);
        }
    }

    global.DungeonCombat = {
        initFromMount: initFromMount,
        resolveTheme: resolveTheme,
        stageTierFromName: stageTierFromName,
    };
})(typeof window !== 'undefined' ? window : globalThis);
