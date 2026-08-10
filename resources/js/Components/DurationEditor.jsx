import { router } from '@inertiajs/react';
import { Clock3, Save } from 'lucide-react';
import { useEffect, useState } from 'react';

const presets = [30, 60, 90, 120, 180, 300];

export default function DurationEditor({ session, value }) {
    const [duration, setDuration] = useState(value || 60);
    useEffect(() => setDuration(value || 60), [value]);
    const submit = event => {
        event.preventDefault();
        router.patch(route('sessions.duration', session.id), { duration_seconds: Number(duration) }, { preserveScroll: true });
    };

    return <form className="panel duration-editor" onSubmit={submit}>
        <div className="panel-head"><div><h2><Clock3 size={18}/> Durasi countdown</h2><p>Bebas diubah untuk sesi ini.</p></div></div>
        <label><span>Detik</span><input type="number" min="1" max="86400" value={duration} onChange={event => setDuration(event.target.value)}/></label>
        <div className="duration-presets">{presets.map(seconds => <button className={Number(duration) === seconds ? 'selected' : ''} type="button" key={seconds} onClick={() => setDuration(seconds)}>{seconds < 60 ? `${seconds} dtk` : `${seconds / 60} mnt`}</button>)}</div>
        <button className="btn btn-primary full" disabled={!duration || Number(duration) < 1}><Save size={16}/> Simpan durasi</button>
    </form>;
}
