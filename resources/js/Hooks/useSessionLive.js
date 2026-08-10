import { useEffect, useState } from 'react';

export default function useSessionLive(initialSession, token = null) {
    const [session, setSession] = useState(initialSession);
    const [connected, setConnected] = useState(window.Echo?.connector?.pusher?.connection?.state === 'connected');

    useEffect(() => setSession(initialSession), [initialSession]);

    useEffect(() => {
        if (!window.Echo) return;
        const channel = window.Echo.channel(`session.${initialSession.code}`)
            .listen('.session.updated', (event) => setSession(previous => ({ ...previous, ...event.snapshot })));
        const connection = window.Echo.connector?.pusher?.connection;
        setConnected(connection?.state === 'connected');
        const onConnected = () => setConnected(true);
        const onDisconnected = () => setConnected(false);
        connection?.bind('connected', onConnected);
        connection?.bind('disconnected', onDisconnected);
        return () => {
            connection?.unbind('connected', onConnected);
            connection?.unbind('disconnected', onDisconnected);
            window.Echo.leave(`session.${initialSession.code}`);
        };
    }, [initialSession.code]);

    useEffect(() => {
        const refresh = async () => {
            try {
                const url = token ? `/r/${initialSession.code}/state` : `/display/${initialSession.code}/state`;
                const headers = token ? { Authorization: `Bearer ${token}`, Accept: 'application/json' } : { Accept: 'application/json' };
                const response = await fetch(url, { headers });
                if (response.ok) { const next = await response.json(); setSession(previous => ({ ...previous, ...next })); setConnected(true); }
                else setConnected(false);
            } catch { setConnected(false); }
        };
        refresh();
        const interval = setInterval(refresh, 3000);
        return () => clearInterval(interval);
    }, [initialSession.code, token]);

    return { session, setSession, connected };
}
