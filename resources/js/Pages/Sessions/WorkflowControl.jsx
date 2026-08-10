import AppShell from '@/Components/AppShell';
import Bracket from '@/Components/Bracket';
import Countdown from '@/Components/Countdown';
import DurationEditor from '@/Components/DurationEditor';
import EntryRoster from '@/Components/EntryRoster';
import useSessionLive from '@/Hooks/useSessionLive';
import { Head, Link, router } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2, ExternalLink, GitBranch, Play, PlayCircle, Plus, ShieldCheck, Trophy, Users } from 'lucide-react';
import { useMemo, useState } from 'react';

const labels = { judged_rubric: 'Penjurian', metric_race: 'Perlombaan metrik', multi_round_score: 'Skor multisesi', elimination: 'Eliminasi', knockout_score: 'Sistem gugur' };
const affiliationOptions = ['TIK', 'SANTIK', 'Sekretariat', 'IKP', 'Magang', 'PKL', 'Lainnya'];

function RulesPanel({ rules }) {
    const sections = [['Perlengkapan', rules.equipment], ['Posisi & persiapan', rules.setup], ['Cara bermain', rules.play], ['Penilaian', rules.scoring], ['Kondisi khusus', rules.contingencies], ['Pelanggaran', rules.violations], ['Keselamatan', rules.safety]];
    return <section className="panel rules-panel"><div className="panel-head"><div><h2>Peraturan resmi</h2><p>{rules.source}</p></div></div>{sections.map(([title, items]) => items?.length ? <div className="rule-section" key={title}><strong>{title}</strong><ul>{items.map(item => <li key={item}>{item}</li>)}</ul></div> : null)}</section>;
}

function EntryForm({ session }) {
    const entry = session.rules.entry || {};
    const exact = entry.members_min === entry.members_max;
    const [name, setName] = useState('');
    const [affiliationOption, setAffiliationOption] = useState('');
    const [customAffiliation, setCustomAffiliation] = useState('');
    const [members, setMembers] = useState('');
    const submit = event => {
        event.preventDefault();
        const list = members.split(/\r?\n|,/).map(value => value.trim()).filter(Boolean);
        const affiliation = affiliationOption === 'Lainnya' ? customAffiliation.trim() : affiliationOption;
        router.post(route('sessions.participants', session.id), { entries: [{ name, affiliation, members: list }] }, { preserveScroll: true, onSuccess: () => { setName(''); setAffiliationOption(''); setCustomAffiliation(''); setMembers(''); } });
    };
    return <form className="panel entry-form" onSubmit={submit}><div className="panel-head"><div><h2><Plus size={18}/> Tambah {session.competition.entry_kind === 'team' ? 'regu' : 'peserta'}</h2><p>{exact ? `Wajib ${entry.members_min} anggota` : `${entry.members_min}–${entry.members_max} anggota`}</p></div></div>
        <input value={name} onChange={e => setName(e.target.value)} placeholder={session.competition.entry_kind === 'team' ? 'Nama regu' : 'Nama peserta'} />
        <select value={affiliationOption} onChange={e => { setAffiliationOption(e.target.value); if (e.target.value !== 'Lainnya') setCustomAffiliation(''); }} aria-label="Bidang atau afiliasi">
            <option value="">Pilih bidang/afiliasi</option>
            {affiliationOptions.map(option => <option value={option} key={option}>{option}</option>)}
        </select>
        {affiliationOption === 'Lainnya' && <input value={customAffiliation} onChange={e => setCustomAffiliation(e.target.value)} maxLength="100" placeholder="Tulis bidang/afiliasi lainnya" autoFocus />}
        <textarea rows={Math.min(6, entry.members_max || 3)} value={members} onChange={e => setMembers(e.target.value)} placeholder="Nama anggota, satu per baris" />
        <button className="btn btn-dark full" disabled={!name.trim() || !members.trim() || !affiliationOption || (affiliationOption === 'Lainnya' && !customAffiliation.trim())}>Tambahkan</button>
    </form>;
}

function MetricSetupFields({ session, rules, capacity, setCapacity, unit, setUnit }) {
    const count = Math.max(1, session.participants.length);
    const entryLabel = session.competition.entry_kind === 'team' ? 'regu' : 'peserta';
    const unitOptions = rules.workflow.remaining_unit_options || [rules.workflow.remaining_unit, 'jumlah sisa', 'gram', 'persen'].filter(Boolean);
    return <>
        <label className="prepare-option"><span>{session.competition.entry_kind === 'team' ? 'Regu' : 'Peserta'} per babak</span><select value={Math.min(capacity, count)} onChange={event => setCapacity(Number(event.target.value))}>{Array.from({ length: count }, (_, index) => index + 1).map(size => <option value={size} key={size}>{size === count ? `Semua (${count})` : size}</option>)}</select><small>{capacity >= count ? `Semua ${count} ${entryLabel} bermain bersamaan dalam satu babak.` : `${count} ${entryLabel} akan dibagi otomatis, maksimal ${capacity} per babak.`}</small></label>
        {rules.workflow.remaining_unit_required && <label className="prepare-option"><span>Jika waktu habis, hitung sisa dalam</span><select value={unit} onChange={event => setUnit(event.target.value)}>{[...new Set(unitOptions)].map(option => <option value={option} key={option}>{option}</option>)}</select><small>Regu dengan jumlah sisa paling sedikit lebih unggul.</small></label>}
    </>;
}

function StageTimer({ session, stage }) {
    const timerSession = { state: stage.state, server_now: session.server_now, timer_started_at: stage.started_at, timer_ends_at: stage.ends_at, paused_remaining_ms: stage.paused_remaining_ms, competition: { duration_seconds: stage.duration_seconds || 0 } };
    const action = name => router.post(route('workflow.stage.timer', [session.id, stage.id]), { action: name }, { preserveScroll: true });
    return <div className="stage-timer"><Countdown session={timerSession}/><div className="transport">{stage.state === 'running' ? <button className="btn btn-dark" onClick={() => action('pause')}>Jeda</button> : stage.state === 'paused' ? <button className="btn btn-primary" onClick={() => action('resume')}>Lanjutkan</button> : <button className="btn btn-primary" onClick={() => action('start')}><Play size={17}/> Mulai</button>}<button className="btn btn-ghost" onClick={() => action('reset')}>Reset</button><button className="btn btn-ghost" onClick={() => action('complete')}>Tutup babak</button></div></div>;
}

function ResultEditor({ session, stage, result }) {
    const workflow = session.competition.workflow_key;
    const [outcome, setOutcome] = useState(result.outcome === 'pending' ? 'finished' : result.outcome);
    const [primary, setPrimary] = useState(result.primary_value ? Number(result.primary_value) / 1000 : '');
    const [secondary, setSecondary] = useState(result.secondary_value || '');
    const [metrics, setMetrics] = useState(result.metrics || {});
    const save = () => {
        const data = { participant_id: result.participant_id, outcome, primary_value: primary === '' ? null : Number(primary) * 1000, secondary_value: secondary === '' ? null : Number(secondary), metrics };
        router.patch(route('workflow.stage.result', [session.id, stage.id]), data, { preserveScroll: true });
    };
    if (workflow === 'judged_rubric') {
        const rubric = session.rules.workflow.rubric;
        return <div className="result-editor rubric-editor"><strong>{result.participant?.name}</strong>{Object.entries(rubric).map(([key, max]) => <label key={key}><span>{({ creativity: 'Kreativitas', presentation: 'Penyajian', taste: 'Cita rasa', hygiene: 'Kebersihan' })[key]} / {max}</span><input type="number" min="0" max={max} value={metrics[key] ?? ''} onChange={e => setMetrics({ ...metrics, [key]: Number(e.target.value) })}/></label>)}<button className="mini-btn approve" onClick={save}>Simpan nilai</button></div>;
    }
    if (workflow === 'multi_round_score') return <div className="result-editor"><strong>{result.participant?.name}</strong><input type="number" min="0" value={metrics.correct_answers ?? ''} onChange={e => setMetrics({ correct_answers: Number(e.target.value) })} placeholder="Jawaban benar"/><button className="mini-btn approve" onClick={save}>Simpan</button></div>;
    if (workflow === 'elimination') return <div className="result-editor"><strong>{result.participant?.name}</strong><button className={`mini-btn ${result.outcome === 'passed' ? 'approve' : ''}`} onClick={() => router.patch(route('workflow.stage.result', [session.id, stage.id]), { participant_id: result.participant_id, outcome: 'passed' }, { preserveScroll: true })}>Lolos</button><button className="mini-btn" onClick={() => router.patch(route('workflow.stage.result', [session.id, stage.id]), { participant_id: result.participant_id, outcome: 'failed' }, { preserveScroll: true })}>Gagal</button></div>;
    return <div className="result-editor race-editor"><strong>{result.participant?.name}</strong><select value={outcome} onChange={e => setOutcome(e.target.value)}><option value="finished">Selesai</option><option value="timeout">Timeout</option><option value="safety_stop">Dihentikan keselamatan</option><option value="disqualified">Diskualifikasi</option><option value="void">Percobaan batal</option></select><input type="number" min="0" step="0.001" value={primary} onChange={e => setPrimary(e.target.value)} placeholder="Waktu (detik)"/><input type="number" min="0" step="0.001" value={secondary} onChange={e => setSecondary(e.target.value)} placeholder={`Sisa (${stage.config?.remaining_unit || 'jumlah'})`}/><button className="mini-btn approve" onClick={save}>Simpan</button></div>;
}

function WorkflowStage({ session, stage }) {
    const [command, setCommand] = useState('step_on_color');
    const [playThirdPlace, setPlayThirdPlace] = useState(false);
    return <article className={`panel workflow-stage ${stage.state}`}><div className="panel-head"><div><h2>{stage.label}</h2><p>{stage.state} · percobaan {stage.attempt_number}</p></div></div>{stage.timer_kind !== 'none' && <StageTimer session={session} stage={stage}/>}<div className="result-list">{stage.results.map(result => <ResultEditor key={result.id} session={session} stage={stage} result={result}/>)}</div>{session.competition.workflow_key === 'elimination' && <div className="stage-advance"><select value={command} onChange={e => setCommand(e.target.value)}><option value="step_on_color">Putaran berikut: injak warna</option><option value="avoid_color">Putaran berikut: jangan injak warna</option></select><button className="btn btn-primary" onClick={() => router.post(route('workflow.elimination.advance', [session.id, stage.id]), { command })}>Sahkan dan lanjut</button></div>}</article>;
}

export default function WorkflowControl({ initialSession }) {
    const { session, connected } = useSessionLive(initialSession);
    const [capacity, setCapacity] = useState(Math.max(1, initialSession.participants.length));
    const [unit, setUnit] = useState(initialSession.rules?.workflow?.remaining_unit || (initialSession.competition.name.includes('Kerupuk') ? 'potongan kerupuk' : initialSession.competition.name.includes('Suap') ? 'potongan pisang' : 'jumlah sisa'));
    const [command, setCommand] = useState('step_on_color');
    const [incident, setIncident] = useState({ participant_id: '', kind: 'warning', value: '', reason: '' });
    const [replayParticipants, setReplayParticipants] = useState([]);
    const [manualRanks, setManualRanks] = useState(['', '', '']);
    const [juryReason, setJuryReason] = useState('');
    const rules = session.rules || {};
    const prepared = session.stages.length > 0 || session.matches.length > 0;
    const currentStage = [...session.stages].reverse().find(stage => !['completed', 'void'].includes(stage.state));
    const countdownStage = session.stages.find(stage => stage.timer_kind === 'countdown' && !['completed', 'void'].includes(stage.state)) || session.stages.find(stage => stage.timer_kind === 'countdown');
    const supportsCountdown = Boolean(countdownStage || rules.workflow?.timer_kind === 'countdown' || session.competition.workflow_key === 'multi_round_score');
    const countdownDuration = countdownStage?.duration_seconds || rules.workflow?.duration_seconds || session.competition.duration_seconds;
    const placements = useMemo(() => session.placements || [], [session.placements]);
    const prepare = () => session.competition.workflow_key === 'knockout_score' ? router.post(route('general.bracket.generate', session.id), { play_third_place: playThirdPlace }) : router.post(route('workflow.prepare', session.id), { heat_capacity: capacity, remaining_unit: unit, command });
    const logIncident = event => { event.preventDefault(); router.post(route('workflow.incident', session.id), { ...incident, value: incident.value === '' ? null : Number(incident.value), competition_stage_id: currentStage?.id }, { preserveScroll: true, onSuccess: () => setIncident({ participant_id: '', kind: 'warning', value: '', reason: '' }) }); };

    return <AppShell title={session.competition.name} eyebrow={labels[session.competition.workflow_key] || 'KONTROL LOMBA'} actions={<><span className={`connection ${connected ? 'online' : 'offline'}`}><i/>{connected ? 'Real-time aktif' : 'Polling aktif'}</span>{session.competition.video_url&&<a className="btn btn-video" href={session.competition.video_url} target="_blank" rel="noreferrer"><PlayCircle size={17}/> Video panduan <ExternalLink size={15}/></a>}<Link className="btn btn-outline" href={route('sessions.display', session.code)} target="_blank">Layar publik <ExternalLink size={16}/></Link></>}>
        <Head title={`Kontrol ${session.competition.name}`}/>
        <section className="workflow-summary panel"><div><span className="live-badge">RESMI · {session.code}</span><h2>{rules.category} · {session.competition.schedule_text}</h2><p>{session.competition.venue} · PIC {session.competition.person_in_charge}</p></div><div className="workflow-facts"><span><Users/> {session.participants.length} entri</span><span><ShieldCheck/> {session.checklist_confirmed ? 'Checklist siap' : 'Belum dikonfirmasi'}</span></div></section>
        <div className="workflow-layout"><main className="workflow-main">
            {!session.checklist_confirmed && <section className="panel checklist-card"><ShieldCheck size={40}/><div><h2>Checklist pra-lomba</h2><p>Pastikan perlengkapan dan seluruh butir keselamatan pada panel aturan telah dipenuhi.</p></div><button className="btn btn-primary" onClick={() => router.post(route('workflow.checklist', session.id), { confirmed: true })}><CheckCircle2 size={18}/> Konfirmasi siap</button></section>}
            {!prepared && <section className="panel prepare-card"><div className="panel-head"><div><h2>Siapkan babak lomba</h2><p>Pilih cara peserta bermain. Rekomendasi sudah terisi dan bisa langsung digunakan.</p></div></div>{session.competition.workflow_key === 'metric_race' && <MetricSetupFields session={session} rules={rules} capacity={capacity} setCapacity={setCapacity} unit={unit} setUnit={setUnit}/>} {session.competition.workflow_key === 'elimination' && <label>Perintah pertama<select value={command} onChange={e => setCommand(e.target.value)}><option value="step_on_color">Injak warna</option><option value="avoid_color">Jangan injak warna</option></select></label>}{session.competition.workflow_key === 'knockout_score' && <label><span>Perebutan Juara 3</span><select value={playThirdPlace ? 'yes' : 'no'} onChange={e => setPlayThirdPlace(e.target.value === 'yes')}><option value="no">Tidak, pakai skor semifinal</option><option value="yes">Ya, mainkan pertandingan</option></select></label>}<button className="btn btn-primary" disabled={!session.checklist_confirmed || !session.participants.length || (rules.workflow.remaining_unit_required && !unit)} onClick={prepare}>{session.competition.workflow_key === 'knockout_score' ? <GitBranch size={18}/> : <Play size={18}/>} Siapkan babak</button></section>}
            {session.competition.workflow_key === 'knockout_score' ? <section className="panel knockout-section"><Bracket matches={session.matches} sessionId={session.id} interactive routePrefix="general" scoreMode/></section> : session.stages.map(stage => <WorkflowStage key={stage.id} session={session} stage={stage}/>)}
            {prepared && session.state !== 'finished' && !['elimination','knockout_score','judged_rubric'].includes(session.competition.workflow_key) && <section className="panel replay-card"><div className="panel-head"><div><h2>Babak ulang / tie-break</h2><p>Pilih hanya entri yang seri atau wajib mengulang menurut aturan.</p></div></div><div className="replay-entries">{session.participants.map(participant => <label key={participant.id}><input type="checkbox" checked={replayParticipants.includes(participant.id)} onChange={e => setReplayParticipants(e.target.checked ? [...replayParticipants, participant.id] : replayParticipants.filter(id => id !== participant.id))}/>{participant.name}</label>)}</div><button className="btn btn-outline" disabled={!replayParticipants.length} onClick={() => router.post(route('workflow.replay', session.id), { participant_ids: replayParticipants }, { preserveScroll: true, onSuccess: () => setReplayParticipants([]) })}>Buat babak ulang</button></section>}
            {prepared && session.state !== 'finished' && session.competition.workflow_key !== 'elimination' && session.competition.workflow_key !== 'knockout_score' && <section className="panel finalize-card"><Trophy/><div><h2>Finalisasi peringkat</h2><p>Sistem menghitung tiga peringkat terbaik dari seluruh babak dan tie-break profil.</p></div><button className="btn btn-primary" onClick={() => router.post(route('workflow.finalize', session.id))}>Tetapkan Juara 1–3</button></section>}
            {prepared && session.state !== 'finished' && session.competition.workflow_key !== 'knockout_score' && <section className="panel jury-ranking"><div className="panel-head"><div><h2>Keputusan juri</h2><p>Gunakan setelah tie-break tidak memecah seri atau untuk mengesahkan hasil babak ulang.</p></div></div><div className="manual-ranks">{manualRanks.map((value,index)=><label key={index}><span>Juara {index+1}</span><select value={value} onChange={e=>setManualRanks(manualRanks.map((item,i)=>i===index?e.target.value:item))}><option value="">Pilih entri</option>{session.participants.map(participant=><option value={participant.id} key={participant.id}>{participant.name}</option>)}</select></label>)}</div><textarea value={juryReason} onChange={e=>setJuryReason(e.target.value)} placeholder="Alasan wajib: hasil babak ulang atau pertimbangan dewan juri"/><button className="btn btn-dark" disabled={!manualRanks[0] || !juryReason.trim() || new Set(manualRanks.filter(Boolean)).size !== manualRanks.filter(Boolean).length} onClick={()=>router.post(route('workflow.finalize',session.id),{placements:manualRanks.filter(Boolean).map(Number),reason:juryReason})}>Sahkan keputusan juri</button></section>}
            {placements.length > 0 && <section className="panel placements"><div className="panel-head"><h2>Hasil resmi</h2></div>{placements.map(place => <article key={place.rank}><span>{place.rank}</span><strong>{place.entry?.name}</strong><small>{place.decision_reason || 'Dihitung otomatis'}</small></article>)}</section>}
        </main><aside className="workflow-side"><EntryForm session={session}/>{supportsCountdown&&<DurationEditor session={session} value={countdownDuration}/>}<EntryRoster session={session}/><RulesPanel rules={rules}/>{session.participants.length > 0 && <form className="panel incident-form" onSubmit={logIncident}><div className="panel-head"><div><h2><AlertTriangle size={18}/> Catat insiden</h2><p>Masuk ke log audit resmi.</p></div></div><select value={incident.participant_id} onChange={e => setIncident({ ...incident, participant_id: Number(e.target.value) })}><option value="">Pilih entri</option>{session.participants.map(p => <option key={p.id} value={p.id}>{p.name}</option>)}</select><select value={incident.kind} onChange={e => setIncident({ ...incident, kind: e.target.value })}><option value="warning">Peringatan</option><option value="time_penalty">Penalti waktu (detik)</option><option value="disqualification">Diskualifikasi</option><option value="equipment_failure">Gangguan alat</option><option value="safety_stop">Penghentian keselamatan</option><option value="jury_decision">Keputusan juri</option></select>{incident.kind === 'time_penalty' && <input type="number" min="1" value={incident.value} onChange={e => setIncident({ ...incident, value: Number(e.target.value) })} placeholder="Detik penalti"/>}<textarea value={incident.reason} onChange={e => setIncident({ ...incident, reason: e.target.value })} placeholder="Alasan wajib"/><button className="btn btn-dark full" disabled={!incident.participant_id || !incident.reason}>Simpan insiden</button></form>}</aside></div>
    </AppShell>;
}
