import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

const meta = (name) => document.querySelector(`meta[name="${name}"]`)?.content;
const reverbKey = meta('reverb-key') || import.meta.env.VITE_REVERB_APP_KEY;
const reverbHost = meta('reverb-host') || import.meta.env.VITE_REVERB_HOST || window.location.hostname;
const reverbPort = Number(meta('reverb-port') || import.meta.env.VITE_REVERB_PORT || (window.location.protocol === 'https:' ? 443 : 80));
const reverbScheme = meta('reverb-scheme') || import.meta.env.VITE_REVERB_SCHEME || window.location.protocol.replace(':', '');

if (reverbKey) {
    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: reverbKey,
        wsHost: reverbHost,
        wsPort: reverbPort,
        wssPort: reverbPort,
        forceTLS: reverbScheme === 'https',
        enabledTransports: ['ws', 'wss'],
    });
}

export const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content || '';
