import './bootstrap';
import './realm-sounds';
import './realm-particles';
import './realm-breakthrough-realm-styles';
import './realm-breakthrough-cinematic';

import Alpine from 'alpinejs';
import { registerOnboardingTracker } from './onboarding-tracker';

registerOnboardingTracker(Alpine);

window.Alpine = Alpine;

Alpine.start();
