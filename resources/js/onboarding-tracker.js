/** Register Alpine component for Path of the Outer Disciple tracker (localStorage). */
export function registerOnboardingTracker(Alpine) {
    Alpine.data('realmOnboardingTracker', (initial) => ({
        storageKey: initial.storageKey,
        steps: initial.steps,
        done: {},
        init() {
            try {
                this.done = JSON.parse(window.localStorage.getItem(this.storageKey) || '{}');
            } catch {
                this.done = {};
            }
        },
        toggle(id) {
            this.done[id] = !this.done[id];
            window.localStorage.setItem(this.storageKey, JSON.stringify(this.done));
        },
        isDone(id) {
            return !!this.done[id];
        },
        progress() {
            const n = this.steps.filter((s) => this.done[s.id]).length;
            return this.steps.length ? Math.round((100 * n) / this.steps.length) : 0;
        },
    }));
}
