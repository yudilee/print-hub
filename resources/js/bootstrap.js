import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

const isHttps = typeof window !== 'undefined' && window.location.protocol === 'https:';
const defaultHost = typeof window !== 'undefined' ? window.location.hostname : 'localhost';

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY || 'printhub-live-key',
    wsHost: import.meta.env.VITE_REVERB_HOST || defaultHost,
    wsPort: isHttps ? 443 : (import.meta.env.VITE_REVERB_PORT ?? 80),
    wssPort: 443,
    forceTLS: isHttps,
    enabledTransports: isHttps ? ['wss'] : ['ws', 'wss'],
});
