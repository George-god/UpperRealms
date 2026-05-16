/**
 * Mystical Archive — Codex UI (Alpine data factory).
 */
export function codexArchive(initial) {
    const data = initial?.codex ?? {};
    const categories = data.categories ?? {};
    const byCategory = data.by_category ?? {};
    const completion = data.completion ?? {};
    const reveal = initial?.reveal ?? [];

    const categoryKeys = Object.keys(categories);
    const activeCategory = categoryKeys[0] ?? 'realms';

    return {
        categories,
        byCategory,
        completion,
        grand: data.grand_completion ?? { percent: 0, unlocked: 0, total: 0 },
        activeCategory,
        selectedId: null,
        newCount: data.new_count ?? 0,
        toasts: [],
        syncUrl: initial?.syncUrl ?? '',
        seenUrl: initial?.seenUrl ?? '',
        csrf: initial?.csrf ?? '',

        init() {
            this.seedParticles();
            this.playAmbient();
            if (reveal.length > 0) {
                reveal.forEach((entry, i) => {
                    setTimeout(() => this.pushToast(entry), i * 400);
                });
            }
            const list = this.filteredEntries();
            if (list.length > 0) {
                this.selectEntry(list[0]);
            }
        },

        filteredEntries() {
            return this.byCategory[this.activeCategory] ?? [];
        },

        setCategory(slug) {
            this.activeCategory = slug;
            const list = this.filteredEntries();
            this.selectedId = list[0]?.id ?? null;
            if (this.selectedId) {
                this.markSeenIfNeeded(this.selectedEntry());
            }
        },

        selectEntry(entry) {
            if (!entry) {
                return;
            }
            this.selectedId = entry.id;
            this.markSeenIfNeeded(entry);
        },

        selectedEntry() {
            return this.filteredEntries().find((e) => e.id === this.selectedId) ?? null;
        },

        categoryPercent(slug) {
            return this.completion[slug]?.percent ?? 0;
        },

        async markSeenIfNeeded(entry) {
            if (!entry?.is_unlocked || !entry.is_new) {
                return;
            }
            entry.is_new = false;
            this.newCount = Math.max(0, this.newCount - 1);
            try {
                await fetch(this.seenUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': this.csrf,
                    },
                    body: JSON.stringify({ entry_id: entry.id }),
                });
            } catch (_) {
                /* silent */
            }
        },

        async resync() {
            try {
                const res = await fetch(this.syncUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': this.csrf,
                    },
                });
                const json = await res.json();
                if (!json.success) {
                    return;
                }
                this.byCategory = json.codex.by_category ?? this.byCategory;
                this.completion = json.codex.completion ?? this.completion;
                this.grand = json.codex.grand_completion ?? this.grand;
                this.newCount = json.codex.new_count ?? this.newCount;
                (json.newly_unlocked ?? []).forEach((e, i) => {
                    setTimeout(() => this.pushToast(e), i * 350);
                });
            } catch (_) {
                /* silent */
            }
        },

        pushToast(entry) {
            const id = Date.now() + Math.random();
            this.toasts.push({ id, ...entry });
            setTimeout(() => {
                this.toasts = this.toasts.filter((t) => t.id !== id);
            }, 5000);
        },

        playAmbient() {
            if (typeof window.realmSounds?.play === 'function') {
                window.realmSounds.play('codex_ambient', { loop: true, volume: 0.15 });
            }
        },

        seedParticles() {
            const host = this.$refs?.particles;
            if (!host) {
                return;
            }
            for (let i = 0; i < 18; i++) {
                const p = document.createElement('span');
                p.className = 'codex-particle';
                p.style.left = `${Math.random() * 100}%`;
                p.style.top = `${Math.random() * 100}%`;
                p.style.animationDelay = `${Math.random() * 8}s`;
                p.style.animationDuration = `${6 + Math.random() * 6}s`;
                host.appendChild(p);
            }
        },
    };
}
