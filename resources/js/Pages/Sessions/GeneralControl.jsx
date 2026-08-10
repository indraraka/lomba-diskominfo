import AppShell from '@/Components/AppShell';
import Bracket from '@/Components/Bracket';
import Countdown from '@/Components/Countdown';
import DurationEditor from '@/Components/DurationEditor';
import useSessionLive from '@/Hooks/useSessionLive';
import { Head, Link, router } from '@inertiajs/react';
import { ExternalLink, GitBranch, Pause, Play, PlayCircle, Plus, RotateCcw, TimerReset, Trash2, Trophy } from 'lucide-react';
import { useMemo, useState } from 'react';

export default function GeneralControl({ initialSession }) {
    const { session, connected } = useSessionLive(initialSession);
    const [names, setNames] = useState('');
    const [selected, setSelected] = useState(session.participants.find(p=>p.status==='waiting')?.id || null);
    const [results, setResults] = useState({});
    const action = (name, extra={}) => router.post(route('sessions.timer',session.id), { action:name, ...extra }, { preserveScroll:true });
    const addParticipants = e => { e.preventDefault(); const list=names.split(/\r?\n|,/).map(n=>n.trim()).filter(Boolean); if(list.length) router.post(route('sessions.participants',session.id),{names:list},{preserveScroll:true,onSuccess:()=>setNames('')}); };
    const ranked = useMemo(()=>session.participants.filter(p=>p.result_value!==null).sort((a,b)=>(Number(a.result_value)-Number(b.result_value))*(session.competition.ranking_direction==='asc'?1:-1)),[session]);
    const knockout = session.competition.general_format === 'knockout';
    const activeName = knockout && session.active_match ? `${session.active_match.team_a?.name} vs ${session.active_match.team_b?.name}` : session.participants.find(p=>p.id===session.current_participant_id)?.name;
    return <AppShell title={session.competition.name} eyebrow="KONTROL LOMBA UMUM" actions={<><span className={`connection ${connected?'online':'offline'}`}><i/>{connected?'Real-time aktif':'Menghubungkan'}</span>{session.competition.video_url&&<a className="btn btn-video" href={session.competition.video_url} target="_blank" rel="noreferrer"><PlayCircle size={17}/> Video panduan <ExternalLink size={15}/></a>}<Link className="btn btn-outline" href={route('sessions.display',session.code)} target="_blank">Layar publik <ExternalLink size={16}/></Link></>}>
        <Head title={`Kontrol ${session.competition.name}`}/>
        <div className="control-grid">
            <section className="control-main">
                <div className="panel live-panel"><div className="live-header"><div><span className="live-badge">LIVE · {session.code}</span><h2>{activeName || (session.competition.timer_mode==='collective'?'Semua peserta':'Pilih peserta')}</h2></div><span className={`session-state ${session.state}`}>{session.state}</span></div><Countdown session={session}/><div className="transport">
                    {session.state==='running'?<button className="btn btn-dark" onClick={()=>action('pause')}><Pause size={18}/> Jeda</button>:session.state==='paused'?<button className="btn btn-primary" onClick={()=>action('resume')}><Play size={18}/> Lanjutkan</button>:<button className="btn btn-primary" disabled={!session.participants.length || (knockout&&!session.active_match) || (!knockout&&session.competition.timer_mode==='individual'&&!selected)} onClick={()=>action('start',{participant_id:selected})}><Play size={18}/> Mulai countdown</button>}
                    <button className="btn btn-ghost" onClick={()=>action('reset')}><RotateCcw size={17}/> Reset</button><button className="btn btn-ghost" onClick={()=>action('finish')}><TimerReset size={17}/> Akhiri sesi</button>
                </div></div>
                <div className="panel"><div className="panel-head"><div><h2>{knockout?'Daftar regu':'Daftar peserta'}</h2><p>{knockout?'Tambahkan 2–16 regu sebelum membuat bracket.':session.competition.timer_mode==='individual'?'Pilih nama sebelum memulai giliran.':'Semua peserta akan mulai bersamaan.'}</p></div><span>{session.participants.length}</span></div>
                    {session.participants.length===0?<div className="mini-empty">Tambahkan {knockout?'regu':'peserta'} dari panel di samping.</div>:<div className={`participant-table ${knockout?'knockout-participants':''}`}>{session.participants.map((p,i)=><div className={`participant-row ${selected===p.id?'selected':''}`} key={p.id} onClick={()=>!knockout&&session.state!=='running'&&setSelected(p.id)}><span className="participant-number">{String(i+1).padStart(2,'0')}</span><div className="participant-name"><strong>{p.name}</strong><span>{p.status}</span></div>{!knockout&&<><input aria-label={`Hasil ${p.name}`} placeholder={session.competition.result_unit||'hasil'} value={results[p.id]??p.result_value??''} onClick={e=>e.stopPropagation()} onChange={e=>setResults({...results,[p.id]:e.target.value})}/><select className="status-select" aria-label={`Status ${p.name}`} value="" onClick={e=>e.stopPropagation()} onChange={e=>router.patch(route('sessions.result',[session.id,p.id]),{status:e.target.value,result_value:results[p.id]??p.result_value},{preserveScroll:true})}><option value="">Hasil…</option><option value="finished">Selesai</option><option value="timeout">Timeout</option><option value="disqualified">Diskualifikasi</option></select></>}<button className="small-action" title="Hapus" disabled={session.state!=='lobby'} onClick={e=>{e.stopPropagation();router.delete(route('sessions.participants.destroy',[session.id,p.id]),{preserveScroll:true})}}><Trash2 size={16}/></button></div>)}</div>}
                </div>
            </section>
            <aside className="control-side"><form className="panel" onSubmit={addParticipants}><div className="panel-head"><div><h2>Tambah {knockout?'regu':'peserta'}</h2><p>Satu nama {knockout?'regu':'peserta'} per baris</p></div></div><textarea rows="7" value={names} onChange={e=>setNames(e.target.value)} placeholder={knockout?'Regu Merah\nRegu Biru\nRegu Hijau':'Siti Rahma\nBudi Santoso\nKarina Putri'}/><button className="btn btn-dark full" disabled={!names.trim()}><Plus size={17}/> Tambahkan {knockout?'regu':'nama'}</button></form><DurationEditor session={session} value={session.competition.duration_seconds}/>
                {!knockout&&<section className="panel"><div className="panel-head"><div><h2><Trophy size={19}/> Peringkat</h2><p>{session.competition.result_label}</p></div></div>{ranked.length===0?<p className="muted">Hasil peserta akan muncul di sini.</p>:<ol className="ranking-list">{ranked.map((p,i)=><li key={p.id}><span>{i+1}</span><strong>{p.name}</strong><em>{p.result_value} {session.competition.result_unit}</em></li>)}</ol>}</section>}
            </aside>
        </div>
        {knockout&&<section className="panel knockout-section"><div className="panel-head"><div><h2><GitBranch size={19}/> Bracket sistem gugur</h2><p>Jalankan countdown untuk pertandingan aktif, lalu pilih regu pemenang.</p></div>{session.matches.length===0&&<button className="btn btn-dark" disabled={session.participants.length<2} onClick={()=>router.post(route('general.bracket.generate',session.id))}><GitBranch size={17}/> Buat bracket</button>}</div><Bracket matches={session.matches} sessionId={session.id} interactive routePrefix="general"/></section>}
    </AppShell>;
}
