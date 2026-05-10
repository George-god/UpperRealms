/**
 * Lightweight Web Audio “chip” sounds — no external assets, low CPU.
 * API: RealmAudio.play(name), RealmAudio.toggleSound(), RealmAudio.isEnabled()
 */
(function (global) {
    'use strict';

    var STORAGE_KEY = 'realm-audio-enabled';
    var enabled = global.localStorage.getItem(STORAGE_KEY) !== '0';
    var ctx = null;

    function getCtx() {
        if (!ctx) {
            var AC = global.AudioContext || global.webkitAudioContext;
            if (!AC) {
                return null;
            }
            ctx = new AC();
        }
        return ctx;
    }

    function resume(c) {
        if (c && c.state === 'suspended') {
            c.resume().catch(function () {});
        }
    }

    /** @param {number} [gainMul] */
    function beep(c, freq, dur, type, gainMul) {
        var g0 = (gainMul != null ? gainMul : 1) * 0.09;
        var o = c.createOscillator();
        var g = c.createGain();
        o.type = type || 'sine';
        o.frequency.setValueAtTime(freq, c.currentTime);
        g.gain.setValueAtTime(g0, c.currentTime);
        g.gain.exponentialRampToValueAtTime(0.001, c.currentTime + dur);
        o.connect(g);
        g.connect(c.destination);
        o.start(c.currentTime);
        o.stop(c.currentTime + dur + 0.02);
    }

    function playClick(c) {
        beep(c, 880, 0.04, 'sine', 0.45);
        window.setTimeout(function () {
            beep(c, 1320, 0.03, 'sine', 0.25);
        }, 25);
    }

    function playHit(c) {
        beep(c, 185, 0.08, 'triangle', 0.9);
        beep(c, 95, 0.12, 'sawtooth', 0.35);
    }

    function playCrit(c) {
        beep(c, 330, 0.06, 'square', 0.4);
        window.setTimeout(function () {
            beep(c, 495, 0.08, 'sine', 0.55);
        }, 45);
        window.setTimeout(function () {
            beep(c, 660, 0.1, 'sine', 0.35);
        }, 100);
    }

    function playCultivate(c) {
        [392, 523.25, 659.25, 783.99].forEach(function (f, i) {
            window.setTimeout(function () {
                beep(c, f, 0.14, 'sine', 0.5 - i * 0.06);
            }, i * 70);
        });
    }

    function playSuccess(c) {
        beep(c, 523.25, 0.12, 'sine', 0.5);
        window.setTimeout(function () {
            beep(c, 659.25, 0.14, 'sine', 0.45);
        }, 100);
        window.setTimeout(function () {
            beep(c, 783.99, 0.2, 'sine', 0.4);
        }, 200);
    }

    function playSound(name) {
        if (!enabled) {
            return;
        }
        var c = getCtx();
        if (!c) {
            return;
        }
        resume(c);
        switch (name) {
            case 'click':
                playClick(c);
                break;
            case 'hit':
                playHit(c);
                break;
            case 'crit':
                playCrit(c);
                break;
            case 'cultivate':
                playCultivate(c);
                break;
            case 'success':
                playSuccess(c);
                break;
            default:
                playClick(c);
        }
    }

    function toggleSound() {
        enabled = !enabled;
        try {
            global.localStorage.setItem(STORAGE_KEY, enabled ? '1' : '0');
        } catch (e) {}
        syncToggleUi();
        return enabled;
    }

    function isEnabled() {
        return enabled;
    }

    function syncToggleUi() {
        var labelOn = '🔊';
        var labelOff = '🔇';
        var titleOn = 'Sound on';
        var titleOff = 'Sound muted';
        document.querySelectorAll('[data-realm-audio-toggle]').forEach(function (btn) {
            btn.setAttribute('aria-pressed', enabled ? 'true' : 'false');
            btn.setAttribute('title', enabled ? titleOn : titleOff);
            var icon = btn.querySelector('[data-realm-audio-icon]');
            if (icon) {
                icon.textContent = enabled ? labelOn : labelOff;
            } else {
                btn.textContent = enabled ? labelOn : labelOff;
            }
        });
    }

    function bindToggleButtons() {
        document.querySelectorAll('[data-realm-audio-toggle]').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                var was = enabled;
                toggleSound();
                if (enabled && !was) {
                    playSound('click');
                }
            });
        });
    }

    /** Delegated UI clicks — excludes mute control and [data-realm-sound="off"] */
    function bindDelegatedClicks() {
        document.addEventListener(
            'click',
            function (e) {
                if (!enabled) {
                    return;
                }
                var t = e.target;
                if (!t || !t.closest) {
                    return;
                }
                if (t.closest('[data-realm-audio-toggle]')) {
                    return;
                }
                if (t.closest('[data-realm-sound="off"]')) {
                    return;
                }
                var el = t.closest(
                    'button, [type="submit"], .realm-sound-click, a.realm-sound-click, .realm-btn'
                );
                if (!el || el.disabled || el.getAttribute('aria-disabled') === 'true') {
                    return;
                }
                if (el.classList && el.classList.contains('realm-audio-toggle')) {
                    return;
                }
                playSound('click');
            },
            true
        );
    }

    function init() {
        bindToggleButtons();
        bindDelegatedClicks();
        syncToggleUi();
    }

    global.RealmAudio = {
        play: playSound,
        playSound: playSound,
        toggleSound: toggleSound,
        isEnabled: isEnabled,
        init: init,
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})(typeof window !== 'undefined' ? window : globalThis);
