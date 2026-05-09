(function () {
    'use strict';

    var btn = document.getElementById('cultivate-btn');
    var statusEl = document.getElementById('cultivate-status');
    var chiValueEl = document.getElementById('chi-value');
    var chiBarFill = document.getElementById('chi-bar-fill');
    var chiPercentEl = document.getElementById('chi-percent');
    var statAttackEl = document.getElementById('stat-attack');
    var statDefenseEl = document.getElementById('stat-defense');
    var statLevelEl = document.getElementById('stat-level');
    var realmProgressWrap = document.getElementById('realm-progress-wrap');
    var realmCurrentLevelEl = document.getElementById('realm-current-level');
    var realmProgressFill = document.getElementById('realm-progress-fill');
    var realmProgressPercentEl = document.getElementById('realm-progress-percent');
    var realmLevelsUntilEl = document.getElementById('realm-levels-until');
    var realmProgressLabelLevel = document.getElementById('realm-progress-label-level');
    var realmBreakthroughHint = document.getElementById('realm-breakthrough-hint');

    if (!btn) return;

    var initialCooldown = parseInt(btn.getAttribute('data-cooldown-remaining'), 10) || 0;
    if (initialCooldown > 0 && statusEl) {
        statusEl.style.display = 'block';
        statusEl.className = 'mt-3 text-sm text-red-400';
    }

    function setButtonDisabled(disabled, text) {
        btn.disabled = disabled;
        btn.textContent = text || (disabled ? '⏳ Cultivating…' : '⚡ Cultivate Now');
    }

    function showStatus(msg, isError) {
        if (!statusEl) return;
        statusEl.textContent = msg;
        statusEl.className = isError ? 'mt-3 text-sm text-red-400' : 'mt-3 text-sm text-gray-400 text-center';
        statusEl.style.display = msg ? 'block' : 'none';
    }

    function updateChiBar(chi, maxChi) {
        var pct = maxChi > 0 ? Math.min(100, (chi / maxChi) * 100) : 0;
        if (chiValueEl) chiValueEl.textContent = formatNum(chi) + ' / ' + formatNum(maxChi);
        if (chiBarFill) chiBarFill.style.width = pct + '%';
        if (chiPercentEl) chiPercentEl.textContent = pct.toFixed(1) + '%';
    }

    function formatNum(n) {
        return Number(n).toLocaleString();
    }

    function updateStats(data) {
        if (data.attack != null && statAttackEl) statAttackEl.textContent = formatNum(data.attack);
        if (data.defense != null && statDefenseEl) statDefenseEl.textContent = formatNum(data.defense);
        if (data.level != null && statLevelEl) statLevelEl.textContent = data.level;
    }

    function updateRealmProgressUi(d) {
        if (!realmProgressWrap) return;
        var level = d.level != null ? parseInt(d.level, 10) : NaN;
        if (isNaN(level)) return;

        var nextReq = d.next_realm_required_level != null
            ? parseInt(d.next_realm_required_level, 10)
            : (realmProgressWrap.getAttribute('data-realm-next-required')
                ? parseInt(realmProgressWrap.getAttribute('data-realm-next-required'), 10)
                : 0);
        var nextName = d.next_realm_name != null
            ? String(d.next_realm_name)
            : (realmProgressWrap.getAttribute('data-next-realm-name') || '');

        if (d.next_realm_required_level != null && !isNaN(nextReq)) {
            realmProgressWrap.setAttribute('data-realm-next-required', String(nextReq));
        }
        if (d.next_realm_name != null && d.next_realm_name !== '') {
            realmProgressWrap.setAttribute('data-next-realm-name', nextName);
        }

        var pct = d.realm_progress_percent != null
            ? parseInt(d.realm_progress_percent, 10)
            : (nextReq > 0 ? Math.min(100, Math.round(100 * level / nextReq)) : 100);
        if (isNaN(pct)) pct = 0;

        if (realmCurrentLevelEl) realmCurrentLevelEl.textContent = String(level);
        if (realmProgressLabelLevel) realmProgressLabelLevel.textContent = '(Level ' + level + ')';
        if (realmProgressFill) realmProgressFill.style.width = pct + '%';
        if (realmProgressPercentEl) realmProgressPercentEl.textContent = pct + '%';

        if (realmLevelsUntilEl) {
            if (nextReq > 0 && level < nextReq) {
                var left = d.levels_until_next_realm != null
                    ? parseInt(d.levels_until_next_realm, 10)
                    : (nextReq - level);
                realmLevelsUntilEl.textContent = left + ' levels until ' + (nextName || 'next realm');
                realmLevelsUntilEl.classList.remove('hidden');
            } else {
                realmLevelsUntilEl.classList.add('hidden');
            }
        }

        var bt = !!d.breakthrough_available;
        realmProgressWrap.classList.toggle('breakthrough-available', bt);
        if (realmBreakthroughHint) {
            realmBreakthroughHint.classList.toggle('hidden', !bt);
        }
    }

    function startCooldown(seconds) {
        var remaining = seconds;
        setButtonDisabled(true, '⏳ Cooldown: ' + remaining + 's');
        showStatus('Wait ' + remaining + 's to cultivate again.', true);

        var tick = setInterval(function () {
            remaining--;
            if (remaining <= 0) {
                clearInterval(tick);
                setButtonDisabled(false);
                showStatus('');
                return;
            }
            btn.textContent = '⏳ Cooldown: ' + remaining + 's';
            statusEl.textContent = 'Wait ' + remaining + 's to cultivate again.';
        }, 1000);
    }

    btn.addEventListener('click', function () {
        if (btn.disabled) return;

        setButtonDisabled(true, '⏳ Cultivating…');
        showStatus('');

        fetch('cultivate_action.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body: 'action=cultivate'
        })
            .then(function (res) {
                return res.text().then(function (text) {
                    try {
                        return { data: JSON.parse(text), ok: res.ok, status: res.status };
                    } catch (e) {
                        console.error('Cultivate: invalid JSON. Status:', res.status, 'URL:', res.url, 'Response:', text ? text.substring(0, 500) : '(empty)');
                        var snippet = text ? text.substring(0, 300).replace(/\s+/g, ' ') : '(empty)';
                        throw new Error('Server returned invalid JSON (status ' + res.status + '). Response: ' + snippet);
                    }
                });
            })
            .then(function (result) {
                var data = result.data;
                if (data.success && data.data) {
                    var d = data.data;
                    updateChiBar(d.chi, d.max_chi);
                    updateStats({ attack: d.attack, defense: d.defense, level: d.level });
                    updateRealmProgressUi(d);
                    if (d.chi_gained && d.chi_gained > 0) {
                        showStatus(d.level_up ? 'Level up! Level ' + d.new_level + '.' : 'Gained ' + formatNum(d.chi_gained) + ' chi.', false);
                    } else if (d.realm_level_cap_reached) {
                        showStatus('You have reached the peak level for your realm. Break through to advance further.', false);
                    } else {
                        showStatus('Your chi is already at maximum. Break through or fight to increase it further.', false);
                    }
                    setButtonDisabled(false);
                    var baseCd = (d.cooldown_remaining != null ? d.cooldown_remaining : 5);
                    startCooldown(baseCd);
                } else {
                    var cooldown = (data.data && data.data.cooldown_remaining) || 0;
                    if (cooldown > 0) {
                        startCooldown(cooldown);
                    } else {
                        showStatus(data.message || 'Cultivation failed.', true);
                        setButtonDisabled(false);
                    }
                }
            })
            .catch(function (err) {
                console.error('Cultivate error:', err);
                var msg = (err && err.message) ? err.message : 'Request failed. Try again.';
                if (msg === 'Failed to fetch' || msg.indexOf('NetworkError') >= 0) {
                    msg = 'Network error. Check that the server is running and the URL is correct.';
                }
                showStatus(msg, true);
                setButtonDisabled(false);
            });
    });

    if (initialCooldown > 0) {
        startCooldown(initialCooldown);
    }
})();

