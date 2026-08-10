import { useEffect, useMemo, useRef, useState } from 'react';

const format = (ms) => {
    const total = Math.max(0, Math.ceil(ms / 1000));
    const hours = Math.floor(total / 3600);
    const minutes = Math.floor((total % 3600) / 60);
    const seconds = total % 60;
    return `${hours ? String(hours).padStart(2, '0') + ':' : ''}${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
};

export default function Countdown({ session, compact = false, onUrgentSecond }) {
    const offset = useMemo(() => new Date(session.server_now).getTime() - Date.now(), [session.server_now]);
    const [now, setNow] = useState(Date.now() + offset);
    const lastUrgentSecond = useRef(null);
    useEffect(() => {
        let frame;
        let last = 0;
        const tick = (time) => {
            if (time - last > 50) { setNow(Date.now() + offset); last = time; }
            frame = requestAnimationFrame(tick);
        };
        frame = requestAnimationFrame(tick);
        return () => cancelAnimationFrame(frame);
    }, [offset]);

    const start = session.timer_started_at ? new Date(session.timer_started_at).getTime() : null;
    const end = session.timer_ends_at ? new Date(session.timer_ends_at).getTime() : null;
    const preparing = start && now < start;
    const remaining = session.state === 'paused' ? session.paused_remaining_ms : end ? end - now : (session.competition.duration_seconds || 0) * 1000;
    const prestart = preparing ? Math.max(1, Math.ceil((start - now) / 1000)) : null;
    const urgentSecond = Math.max(0, Math.ceil(remaining / 1000));

    useEffect(() => {
        const shouldSound = session.state === 'running' && !preparing && urgentSecond <= 9;
        if (!shouldSound) {
            lastUrgentSecond.current = null;
            return;
        }
        if (lastUrgentSecond.current === urgentSecond) return;
        lastUrgentSecond.current = urgentSecond;
        onUrgentSecond?.(urgentSecond);
    }, [onUrgentSecond, preparing, session.state, urgentSecond]);

    return (
        <div className={`countdown ${compact ? 'compact' : ''} ${remaining <= 10000 && !preparing ? 'urgent' : ''}`}>
            <span className="countdown-label">{preparing ? 'Bersiap' : session.state === 'paused' ? 'Dijeda' : session.state === 'running' ? 'Waktu tersisa' : 'Durasi lomba'}</span>
            <strong>{preparing ? prestart : format(remaining)}</strong>
            {preparing && <span className="countdown-sub">Mulai serentak dalam hitungan</span>}
        </div>
    );
}
