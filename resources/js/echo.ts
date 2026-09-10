import { configureEcho } from '@laravel/echo-vue';

/**
 * Reverb connection settings.
 *
 * `VITE_REVERB_*` are baked into the bundle at build time, so a pre-built image
 * pulled onto a server it was not built for (e.g. accessed by raw IP:port) would
 * otherwise point the WebSocket at the wrong host. When the host is not pinned
 * at build time we fall back to the page's own origin: nginx proxies the Reverb
 * endpoints (`/app`, `/apps`) on the same port the app is served from, so the
 * browser can always reach Reverb at whatever host:port it loaded the page from.
 * This makes one build deployable behind any domain OR any IP:port.
 */
const buildTimeHost = import.meta.env.VITE_REVERB_HOST as string | undefined;
const pinnedAtBuild = typeof buildTimeHost === 'string' && buildTimeHost !== '';

const origin =
    typeof window !== 'undefined'
        ? window.location
        : { hostname: 'localhost', protocol: 'http:', port: '' };

const isSecure = pinnedAtBuild
    ? (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https'
    : origin.protocol === 'https:';

/** Resolve the port to connect on, preferring the build-time pin, then the
 *  page's own port, then the protocol default (443 for wss, 80 for ws). */
const resolvePort = (): number => {
    const pinned = import.meta.env.VITE_REVERB_PORT;
    if (pinnedAtBuild && pinned) {
        return Number(pinned);
    }

    if (origin.port !== '') {
        return Number(origin.port);
    }

    return isSecure ? 443 : 80;
};

const port = resolvePort();

configureEcho({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: pinnedAtBuild ? buildTimeHost : origin.hostname,
    wsPort: port,
    wssPort: port,
    forceTLS: isSecure,
    enabledTransports: ['ws', 'wss'],
});
