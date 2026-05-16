/**
 * Standalone Spirit Veil prologue helpers (Alpine CDN, no Vite).
 */
document.addEventListener('alpine:init', () => {
    Alpine.data('storyIntro', (initial) => ({
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
            if (this.flashbacks.length > 1) {
                setInterval(() => {
                    this.flashIndex = (this.flashIndex + 1) % this.flashbacks.length;
                }, 2800);
            }
            if (this.dialogue.length > 1) {
                let i = 0;
                const tick = () => {
                    if (i < this.dialogue.length - 1) {
                        i += 1;
                        this.dialogueIndex = i;
                        setTimeout(tick, 2200);
                    }
                };
                setTimeout(tick, 1800);
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
    }));
});
