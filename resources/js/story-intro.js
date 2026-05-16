/**
 * Spirit Veil prologue — particles, dialogue reveal, AJAX actions.
 */
export function storyIntro(initial) {
    return {
        actionUrl: initial.actionUrl ?? '',
        csrf: initial.csrf ?? '',
        flashbacks: initial.flashbacks ?? [],
        flashIndex: 0,
        dialogueIndex: 0,
        dialogue: initial.dialogue ?? [],
        loading: false,
        combatLog: null,

        init() {
            this.seedParticles();
            if (this.flashbacks.length > 0) {
                this.cycleFlashbacks();
            }
            if (this.dialogue.length > 0) {
                this.cycleDialogue();
            }
        },

        cycleFlashbacks() {
            if (this.flashbacks.length <= 1) {
                return;
            }
            setInterval(() => {
                this.flashIndex = (this.flashIndex + 1) % this.flashbacks.length;
            }, 2800);
        },

        cycleDialogue() {
            if (this.dialogue.length <= 1) {
                return;
            }
            let i = 0;
            const tick = () => {
                if (i < this.dialogue.length - 1) {
                    i += 1;
                    this.dialogueIndex = i;
                    setTimeout(tick, 2200);
                }
            };
            setTimeout(tick, 1800);
        },

        async postAction(action, target = null) {
            if (this.loading) {
                return;
            }
            this.loading = true;
            try {
                const body = new URLSearchParams({ action, _token: this.csrf });
                if (target) {
                    body.set('target', target);
                }
                const res = await fetch(this.actionUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-CSRF-TOKEN': this.csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body,
                });
                const json = await res.json();
                if (!json.success) {
                    alert(json.message || 'Something blocks the way.');
                    return;
                }
                if (json.data?.combat?.battle_log) {
                    this.combatLog = json.data.combat;
                }
                if (json.data?.redirect) {
                    window.location.href = json.data.redirect;
                    return;
                }

                window.location.reload();
            } catch (e) {
                console.error(e);
                const form = e.target?.closest?.('form');
                if (form) {
                    form.submit();
                }
            } finally {
                this.loading = false;
            }
        },

        seedParticles() {
            const host = this.$refs?.particles;
            if (!host) {
                return;
            }
            const count = host.dataset.density === 'low' ? 12 : 24;
            for (let i = 0; i < count; i++) {
                const p = document.createElement('span');
                p.className = 'story-particle';
                p.style.left = `${Math.random() * 100}%`;
                p.style.top = `${Math.random() * 100}%`;
                p.style.animationDelay = `${Math.random() * 10}s`;
                p.style.animationDuration = `${8 + Math.random() * 8}s`;
                host.appendChild(p);
            }
        },
    };
}
