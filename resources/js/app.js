import './bootstrap';

import Alpine from 'alpinejs';
import gatewayPulse from './pulse';

window.Alpine = Alpine;

// Компонент живых счётчиков доступен вьюхам как x-data="gatewayPulse(...)".
Alpine.data('gatewayPulse', gatewayPulse);

Alpine.start();
