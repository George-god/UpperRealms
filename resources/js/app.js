import './bootstrap';
import './realm-sounds';
import './realm-particles';
import './realm-visual-fx';
import './dungeon-combat';
import './character-build';
import { codexArchive } from './codex';
import { storyIntro } from './story-intro';
import './realm-breakthrough-realm-styles';
import './realm-breakthrough-cinematic';

import Alpine from 'alpinejs';
import { registerOnboardingTracker } from './onboarding-tracker';

registerOnboardingTracker(Alpine);

document.addEventListener('alpine:init', () => {
    Alpine.data('codexArchive', codexArchive);
    Alpine.data('storyIntro', storyIntro);
});

window.Alpine = Alpine;

Alpine.start();
