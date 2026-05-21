/**
 * Character build UI — stat allocation, specialization, body type, respec.
 */

function characterBuild(config) {
    return {
        allocateUrl: config.allocateUrl,
        specUrl: config.specUrl,
        bodyUrl: config.bodyUrl,
        respecUrl: config.respecUrl,
        points: config.points ?? 0,
        primaries: { ...(config.primaries ?? {}) },
        specialization: config.specialization ?? null,
        bodyType: config.bodyType ?? null,
        loading: false,
        toast: '',
        toastOk: true,

        async post(url, body) {
            const token = document.querySelector('meta[name="csrf-token"]')?.content;
            const res = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': token || '',
                },
                body: JSON.stringify(body),
            });
            return res.json();
        },

        applyBuild(build) {
            if (!build) return;
            this.points = build.attribute_points ?? this.points;
            this.primaries = build.primaries ?? this.primaries;
            this.specialization = build.stat_specialization ?? this.specialization;
            this.bodyType = build.body_type ?? this.bodyType;
            this.syncDom();
        },

        syncDom() {
            document.querySelectorAll('.character-stat-card').forEach((card) => {
                const key = card.dataset.stat;
                const val = this.primaries[key];
                if (val === undefined) return;
                const el = card.querySelector('.character-stat-value');
                if (el) el.textContent = String(val);
                const fill = card.querySelector('.character-stat-bar-fill');
                if (fill) {
                    const max = Math.max(30, ...Object.values(this.primaries).map(Number));
                    fill.style.width = `${Math.min(100, Math.round((100 * val) / max))}%`;
                    fill.classList.add('character-stat-bar-pulse');
                    setTimeout(() => fill.classList.remove('character-stat-bar-pulse'), 600);
                }
            });
        },

        showToast(msg, ok = true) {
            this.toast = msg;
            this.toastOk = ok;
            setTimeout(() => {
                this.toast = '';
            }, 3200);
        },

        async allocate(stat) {
            if (this.loading || this.points < 1) return;
            this.loading = true;
            try {
                const data = await this.post(this.allocateUrl, { stat, points: 1 });
                if (data.success) {
                    this.applyBuild(data.build);
                    this.showToast('Attribute increased.', true);
                } else {
                    this.showToast(data.message || 'Allocation failed.', false);
                }
            } catch {
                this.showToast('Network error.', false);
            }
            this.loading = false;
        },

        async setSpec(key) {
            if (this.loading) return;
            this.loading = true;
            try {
                const data = await this.post(this.specUrl, { specialization: key });
                if (data.success) {
                    this.applyBuild(data.build);
                    this.showToast('Path specialization updated.', true);
                } else {
                    this.showToast(data.message || 'Could not update path.', false);
                }
            } catch {
                this.showToast('Network error.', false);
            }
            this.loading = false;
        },

        async setBody(key) {
            if (this.loading) return;
            this.loading = true;
            try {
                const data = await this.post(this.bodyUrl, { body_type: key });
                if (data.success) {
                    this.applyBuild(data.build);
                    this.showToast('Cultivation body attuned.', true);
                } else {
                    this.showToast(data.message || 'Could not update body.', false);
                }
            } catch {
                this.showToast('Network error.', false);
            }
            this.loading = false;
        },

        async respec() {
            if (this.loading) return;
            if (!window.confirm('Respec will reset your primary attributes and refund points. Continue?')) {
                return;
            }
            this.loading = true;
            try {
                const data = await this.post(this.respecUrl, {});
                if (data.success) {
                    this.applyBuild(data.build);
                    this.showToast('Meridians realigned.', true);
                    setTimeout(() => window.location.reload(), 800);
                } else {
                    this.showToast(data.message || 'Respec failed.', false);
                }
            } catch {
                this.showToast('Network error.', false);
            }
            this.loading = false;
        },
    };
}

window.characterBuild = characterBuild;
