import AppShell from '@/Components/AppShell';
import AnswerCountdown from '@/Components/AnswerCountdown';
import Bracket from '@/Components/Bracket';
import useSessionLive from '@/Hooks/useSessionLive';
import { Head, Link, router } from '@inertiajs/react';
import { Check, ExternalLink, GitBranch, PlayCircle, Plus, Radio, RotateCcw, Settings2, Speaker, Square, Trash2, Trophy, Users, X } from 'lucide-react';
import { QRCodeSVG } from 'qrcode.react';
import { useEffect, useMemo, useRef, useState } from 'react';

const playTone = (frequency) => {
    const AudioContext = window.AudioContext || window.webkitAudioContext;
    if (! AudioContext) return;
    const context = window.__buzzerAudio;
    if (!context || context.state !== 'running') return;
    const oscillator = context.createOscillator();
    const gain = context.createGain();
    oscillator.type = 'sine';
    oscillator.frequency.value = frequency;
    gain.gain.setValueAtTime(0.001, context.currentTime);
    gain.gain.exponentialRampToValueAtTime(0.3, context.currentTime + 0.035);
    gain.gain.exponentialRampToValueAtTime(0.001, context.currentTime + 0.42);
    oscillator.connect(gain); gain.connect(context.destination);
    oscillator.start(); oscillator.stop(context.currentTime + 0.45);
};

export default function QuizControl({ initialSession }) {
    const { session, connected } = useSessionLive(initialSession);
    const [audio, setAudio] = useState(true);
    const [names, setNames] = useState('');
    const [mandatoryAnswers, setMandatoryAnswers] = useState({});
    const [tieTeams, setTieTeams] = useState([]);
    const [rankingReason, setRankingReason] = useState('');
    const [adjust, setAdjust] = useState({ team_id: '', delta: '', reason: '' });
    const lastWinner = useRef(null);
    const joinUrl = `${window.location.origin}/r/${session.code}`;
    const approved = session.teams.filter(team => team.approved);
    const sorted = useMemo(() => [...approved].sort((a, b) => (b.score - a.score) || ((b.tiebreak_wins || 0) - (a.tiebreak_wins || 0))), [approved]);
    const knockout = session.competition.quiz_format === 'knockout';
    const mandatoryPhase = session.question_number < 10;
    const mainQuestionsDone = session.question_number >= 20;
    const bracketLocked = knockout && session.matches.length > 0;

    useEffect(() => {
        if (audio && session.round?.winner && session.round.winner_team_id !== lastWinner.current) playTone(session.round.winner.pitch_hz);
        lastWinner.current = session.round?.winner_team_id || null;
    }, [session.round?.winner_team_id, audio]);

    useEffect(() => {
        const unlockAudio = () => {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            if (!AudioContext) return;
            const context = window.__buzzerAudio || (window.__buzzerAudio = new AudioContext());
            if (context.state === 'suspended') context.resume();
        };
        document.addEventListener('pointerdown', unlockAudio, { once: true });
        document.addEventListener('keydown', unlockAudio, { once: true });
        return () => {
            document.removeEventListener('pointerdown', unlockAudio);
            document.removeEventListener('keydown', unlockAudio);
        };
    }, []);

    const score = (action, payload = {}) => router.post(route('quiz.score', session.id), { action, ...payload }, { preserveScroll: true });
    const addTeams = event => {
        event.preventDefault();
        const entries = names.split(/\r?\n/).map(line => line.split('|').map(value => value.trim())).filter(parts => parts[0]).map(parts => ({ name: parts[0], affiliation: parts[1] || '', members: parts.slice(2,5) }));
        if (entries.length && entries.every(entry => entry.members.length === 3 && entry.members.every(Boolean))) router.post(route('quiz.teams', session.id), { entries }, { preserveScroll: true, onSuccess: () => setNames('') });
    };
    const stateLabel = { ready: 'Siap', open: 'Buzzer terbuka', locked: 'Terkunci', closed: 'Soal selesai' }[session.round?.state] || 'Belum ada soal';
    const matchTitle = session.active_match ? `${session.active_match.team_a?.name} vs ${session.active_match.team_b?.name}` : null;

    return <AppShell title={session.competition.name} eyebrow={knockout ? 'CERDAS CERMAT · SISTEM GUGUR' : 'KONTROL CERDAS CERMAT'} actions={<>
        <span className={`connection ${connected ? 'online' : 'offline'}`}><i/>{connected ? 'Real-time aktif' : 'Menghubungkan'}</span>
        <button className={`btn ${audio ? 'btn-outline' : 'btn-dark'}`} onClick={() => { setAudio(true); playTone(440); }}><Speaker size={17}/>{audio ? 'Audio aktif' : 'Aktifkan audio'}</button>
        {session.competition.video_url&&<a className="btn btn-video" href={session.competition.video_url} target="_blank" rel="noreferrer"><PlayCircle size={17}/> Video panduan <ExternalLink size={15}/></a>}
        <Link className="btn btn-outline" href={route('sessions.display', session.code)} target="_blank">Layar publik <ExternalLink size={16}/></Link>
    </>}>
        <Head title={`Cerdas Cermat · ${session.competition.name}`}/>
        <div className="quiz-layout">
            <section className="quiz-stage panel">
                <div className="stage-top"><span className="live-badge">SOAL {session.question_number || '—'} {matchTitle && `· ${matchTitle}`}</span><span className={`buzzer-status ${session.round?.state || 'idle'}`}><i/>{stateLabel}</span></div>
                <div className={`winner-stage ${session.round?.winner ? 'has-winner' : ''}`} style={session.round?.winner ? { '--team-color': session.round.winner.color } : {}}>
                    {session.state === 'finished' ? <><Trophy size={48}/><span>HASIL RESMI</span><strong>Juara telah ditetapkan</strong><p>Layar publik sudah menampilkan podium dan detail pemenang.</p></> : mandatoryPhase ? <><span>BABAK WAJIB · SOAL {session.question_number + 1}/10</span><strong>Catat semua jawaban</strong><div className="mandatory-grid">{approved.map(team => <label key={team.id}><input type="checkbox" checked={Boolean(mandatoryAnswers[team.id])} onChange={e => setMandatoryAnswers({ ...mandatoryAnswers, [team.id]: e.target.checked })}/><i style={{background:team.color}}/>{team.name}<b>{mandatoryAnswers[team.id] ? `+${session.competition.correct_points}` : '0'}</b></label>)}</div></> : session.round?.winner ? <><span>{session.round.phase === 'tiebreak' ? 'PENJAWAB TIE-BREAK' : 'PENEKAN PERTAMA'}</span><strong>{session.round.winner.name}</strong><p>Silakan berikan jawaban.</p><AnswerCountdown session={session} compact onUrgentSecond={audio ? () => playTone(720) : undefined}/></> : <><div className="buzzer-orbit"><Radio size={42}/></div><strong>{mainQuestionsDone && !session.round ? 'Babak utama selesai' : session.round?.state === 'open' ? 'Buzzer dibuka!' : session.round?.opens_at ? 'Bersiap...' : 'Menunggu operator'}</strong><p>{mainQuestionsDone ? 'Buat tie-break untuk tim seri atau finalisasi peringkat.' : session.round?.opens_at ? 'Semua perangkat akan terbuka bersamaan.' : 'Buat soal rebutan lalu buka buzzer.'}</p></>}
                </div>
                {session.state !== 'finished' && <div className="quiz-controls">
                    {mandatoryPhase ? <button className="btn btn-primary btn-large" disabled={!approved.length} onClick={() => router.post(route('quiz.mandatory', session.id), { answers: Object.fromEntries(approved.map(team => [team.id, Boolean(mandatoryAnswers[team.id])])) }, { preserveScroll: true, onSuccess: () => setMandatoryAnswers({}) })}><Check/> Simpan soal wajib</button> : mainQuestionsDone && (!session.round || session.round.state === 'closed') ? null : !session.round || session.round.state === 'closed' ? <button className="btn btn-primary btn-large" onClick={() => router.post(route('quiz.rounds', session.id))}>Soal rebutan berikutnya</button> : session.round.winner ? <><button className="answer-btn correct" onClick={() => score('correct')}><Check/> Benar {session.round.phase !== 'tiebreak' && <span>+{session.competition.correct_points}</span>}</button><button className="answer-btn wrong" onClick={() => score('wrong')}><X/> Salah {session.round.phase !== 'tiebreak' && <span>{session.competition.wrong_points}</span>}</button></> : <button className="btn btn-primary btn-large" onClick={() => router.post(route('quiz.open', session.id))}><Radio size={20}/> Buka buzzer</button>}
                    <button className="btn btn-ghost" onClick={() => router.post(route('quiz.finish', session.id))}><Square size={16}/> Akhiri</button>
                </div>}
            </section>
            <aside className="quiz-side">
                <form className="panel" onSubmit={addTeams}><div className="panel-head"><div><h2><Plus size={18}/> Tambah regu</h2><p>Format: Regu | Bidang | Anggota 1 | Anggota 2 | Anggota 3</p></div></div><textarea rows="5" value={names} onChange={event => setNames(event.target.value)} placeholder={'Garuda | Sekretariat | Ani | Budi | Citra'} disabled={session.question_number > 0 || session.teams.length >= 6}/><button className="btn btn-dark full" disabled={!names.trim() || session.question_number > 0 || session.teams.length >= 6}><Plus size={17}/> Tambahkan regu</button></form>
                <section className="panel join-card"><div><p className="eyebrow">GABUNG RUANG</p><h2>{session.code}</h2><p>PIN <strong>{session.pin || '••••••'}</strong></p></div><div className="qr-wrap"><QRCodeSVG value={joinUrl} size={112}/></div></section>
                <section className="panel">
                    <div className="panel-head"><div><h2><Users size={18}/> Grup</h2><p>{bracketLocked ? 'Terkunci setelah bracket dibuat' : 'Maksimal 6 grup'}</p></div><span>{approved.length}/6</span></div>
                    <div className="team-list">{session.teams.length === 0 ? <p className="muted">Belum ada grup. Tambahkan manual atau gunakan QR.</p> : session.teams.map(team => <div className="team-row" key={team.id}><i style={{ background: team.color }}/><div><strong>{team.name}</strong><span>{team.approved ? (team.online ? 'Online' : 'Disetujui') : 'Menunggu/diskualifikasi'}</span></div>{!knockout && <b>{team.score}</b>}<button className={`mini-btn ${team.approved ? '' : 'approve'}`} disabled={bracketLocked} onClick={() => router.post(route('quiz.approve', [session.id, team.id]), {}, { preserveScroll: true })}>{team.approved ? 'Batalkan' : 'Setujui'}</button>{session.question_number>0&&<button className="mini-btn" title="Catat bantuan luar" onClick={()=>{const reason=window.prompt('Alasan/bukti bantuan luar:');if(reason)router.post(route('quiz.external-help',[session.id,team.id]),{reason},{preserveScroll:true})}}>Pelanggaran</button>}{session.question_number===0&&<button className="small-action" title="Hapus grup" onClick={()=>router.delete(route('quiz.teams.destroy',[session.id,team.id]),{preserveScroll:true})}><Trash2 size={15}/></button>}</div>)}</div>
                    {knockout && !bracketLocked && <button className="btn btn-dark full bracket-generate" disabled={approved.length < 2} onClick={() => router.post(route('quiz.bracket.generate', session.id))}><GitBranch size={17}/> Buat bracket ({approved.length} grup)</button>}
                </section>
            </aside>
        </div>
        {mainQuestionsDone && session.state !== 'finished' && <section className="panel quiz-finalize"><div><h2>Tie-break dan hasil resmi</h2><p>Pilih minimal dua tim seri. Pemenang tie-break otomatis ditempatkan lebih tinggi; jika tetap seri setelah tiga soal, isi alasan keputusan juri.</p></div><div className="tie-team-list">{sorted.map(team => <label key={team.id}><input type="checkbox" checked={tieTeams.includes(team.id)} onChange={e => setTieTeams(e.target.checked ? [...tieTeams, team.id] : tieTeams.filter(id => id !== team.id))}/>{team.name} ({team.score} poin{team.tiebreak_wins ? ` · ${team.tiebreak_wins} menang tie-break` : ''})</label>)}</div><button className="btn btn-outline" disabled={tieTeams.length < 2} onClick={() => router.post(route('quiz.tiebreak', session.id), { team_ids: tieTeams })}>Buat soal tie-break</button><input value={rankingReason} onChange={e => setRankingReason(e.target.value)} placeholder="Alasan keputusan juri (wajib bila hasil tetap seri)"/><button className="btn btn-primary" onClick={() => router.post(route('quiz.rankings', session.id), { team_ids: sorted.slice(0,3).map(team => team.id), reason: rankingReason || null })}><Trophy size={17}/> Finalisasi Juara 1–3</button></section>}
        {knockout ? <section className="panel knockout-section"><div className="panel-head"><div><h2><GitBranch size={19}/> Bracket sistem gugur</h2><p>Aktifkan pertandingan, jalankan buzzer, lalu pilih grup pemenang.</p></div></div><Bracket matches={session.matches} sessionId={session.id} interactive/></section> : <section className="score-section">
            <div className="panel scoreboard"><div className="panel-head"><div><h2>Papan skor</h2><p>Peringkat diperbarui langsung</p></div></div><div className="score-grid">{sorted.map((team, index) => <article key={team.id} style={{ '--team-color': team.color }}><span>#{index + 1}</span><i/><strong>{team.name}{team.tiebreak_wins ? <small>{team.tiebreak_wins} menang tie-break</small> : null}</strong><b>{team.score}</b><em>poin</em></article>)}</div></div>
            {session.state !== 'finished' && <form className="panel adjustment" onSubmit={event => { event.preventDefault(); score('adjustment', { team_id: Number(adjust.team_id), delta: Number(adjust.delta), reason: adjust.reason }); setAdjust({ team_id: '', delta: '', reason: '' }); }}><div className="panel-head"><div><h2><Settings2 size={18}/> Koreksi skor</h2><p>Semua perubahan tercatat</p></div></div><select value={adjust.team_id} onChange={event => setAdjust({ ...adjust, team_id: event.target.value })}><option value="">Pilih grup</option>{approved.map(team => <option value={team.id} key={team.id}>{team.name}</option>)}</select><div className="inline-fields"><input type="number" placeholder="± poin" value={adjust.delta} onChange={event => setAdjust({ ...adjust, delta: event.target.value })}/><input placeholder="Alasan koreksi" value={adjust.reason} onChange={event => setAdjust({ ...adjust, reason: event.target.value })}/></div><button className="btn btn-dark full" disabled={!adjust.team_id || !adjust.delta || !adjust.reason}><RotateCcw size={16}/> Terapkan koreksi</button>{session.transactions?.length > 0 && <div className="ledger">{session.transactions.slice(0, 5).map(transaction => <div key={transaction.id}><span><strong>{transaction.team}</strong> {transaction.kind} <b className={transaction.delta >= 0 ? 'positive' : 'negative'}>{transaction.delta > 0 ? '+' : ''}{transaction.delta}</b></span>{transaction.kind !== 'reversal' && !transaction.reversed && <button type="button" onClick={() => router.post(route('quiz.score.reverse', [session.id, transaction.id]), {}, { preserveScroll: true })}>Batalkan</button>}</div>)}</div>}</form>}
        </section>}
    </AppShell>;
}
