import Bracket from '@/Components/Bracket';
import AnswerCountdown from '@/Components/AnswerCountdown';
import Countdown from '@/Components/Countdown';
import Brand from '@/Components/Brand';
import useSessionLive from '@/Hooks/useSessionLive';
import { Head } from '@inertiajs/react';
import { Radio, Speaker, Trophy } from 'lucide-react';
import { QRCodeSVG } from 'qrcode.react';
import { useEffect, useRef, useState } from 'react';

const sound = (frequency, duration = 0.5, volume = 0.38) => {
    const AudioContext = window.AudioContext || window.webkitAudioContext;
    if (! AudioContext) return;
    const context = window.__displayAudio;
    if (!context || context.state !== 'running') return;
    const oscillator = context.createOscillator();
    const gain = context.createGain();
    oscillator.frequency.value = frequency;
    gain.gain.setValueAtTime(0.001, context.currentTime);
    gain.gain.exponentialRampToValueAtTime(volume, context.currentTime + 0.025);
    gain.gain.exponentialRampToValueAtTime(0.001, context.currentTime + duration);
    oscillator.connect(gain); gain.connect(context.destination);
    oscillator.start(); oscillator.stop(context.currentTime + duration + 0.02);
};

const countdownSound = second => {
    if (second === 0) {
        sound(1050, 0.7, 0.5);
        return;
    }
    sound(720, 0.16, 0.42);
};

function PublicEntryDetails({ entry }) {
    if (!entry) return null;
    const members = (entry.members || []).filter(member => member && member.trim().toLowerCase() !== entry.name?.trim().toLowerCase());
    return <div className="public-entry-details">
        {entry.affiliation && <small>{entry.affiliation}</small>}
        {members.length > 0 && <ul>{members.map((member, index) => <li key={`${entry.id}-${index}`}>{member}</li>)}</ul>}
    </div>;
}

export default function Display({ initialSession }) {
    const { session, connected } = useSessionLive(initialSession);
    const [audio, setAudio] = useState(true);
    const winner = useRef(null);
    const joinUrl = `${window.location.origin}/r/${session.code}`;
    const teams = [...session.teams.filter(team => team.approved)].sort((a, b) => (b.score - a.score) || ((b.tiebreak_wins || 0) - (a.tiebreak_wins || 0)));
    const generalKnockout = session.competition.type === 'general' && session.competition.general_format === 'knockout';
    const workflow = session.competition.workflow_key;
    const activeStage = session.active_stage;
    const hasOfficialResults = !generalKnockout && (session.placements?.length || 0) > 0;
    const podium = [2, 1, 3].map(rank => session.placements?.find(place => place.rank === rank)).filter(Boolean);
    const stageTimer = activeStage ? { ...session, state: activeStage.state, timer_started_at: activeStage.started_at, timer_ends_at: activeStage.ends_at, competition: { ...session.competition, duration_seconds: session.stages.find(stage => stage.id === activeStage.id)?.duration_seconds || 0 } } : session;

    useEffect(() => {
        if (audio && session.round?.winner && winner.current !== session.round.winner_team_id) sound(session.round.winner.pitch_hz);
        winner.current = session.round?.winner_team_id || null;
    }, [session.round?.winner_team_id, audio]);

    useEffect(() => {
        const unlockAudio = () => {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            if (! AudioContext) return;
            const context = window.__displayAudio || (window.__displayAudio = new AudioContext());
            if (context.state === 'suspended') context.resume();
        };
        document.addEventListener('pointerdown', unlockAudio, { once: true });
        document.addEventListener('keydown', unlockAudio, { once: true });
        return () => {
            document.removeEventListener('pointerdown', unlockAudio);
            document.removeEventListener('keydown', unlockAudio);
        };
    }, []);

    return <><Head title={`Layar · ${session.competition.name}`}/><div className={`display-page ${session.competition.type}`}>
        <header><Brand className="display-brand"/><div><span className={`connection ${connected ? 'online' : 'offline'}`}><i/>{connected ? 'LIVE' : 'MENGHUBUNGKAN'}</span><button aria-pressed={audio} onClick={() => { const next = !audio; setAudio(next); if (next) sound(440); }}><Speaker size={18}/>{audio ? 'Audio aktif' : 'Audio nonaktif'}</button></div></header>
        {session.competition.type === 'general' ? <main className={`general-display ${generalKnockout ? 'knockout' : ''} ${hasOfficialResults ? 'results' : ''}`}>
            <p className="eyebrow">{hasOfficialResults ? 'HASIL RESMI' : generalKnockout ? 'PERTANDINGAN SISTEM GUGUR' : ({ judged_rubric: 'PENJURIAN RESMI', metric_race: 'BABAK LOMBA', multi_round_score: 'SESI SUSUN KATA', elimination: 'PUTARAN ELIMINASI' })[workflow] || 'LOMBA SERENTAK'}</p>
            <h1>{hasOfficialResults ? session.competition.name : generalKnockout && session.active_match ? `${session.active_match.team_a?.name} vs ${session.active_match.team_b?.name}` : activeStage?.label || session.competition.name}</h1>
            {hasOfficialResults && <p className="results-caption"><Trophy size={18}/> Selamat kepada para pemenang</p>}
            {!hasOfficialResults && activeStage && activeStage.timer_kind !== 'none' && <Countdown session={stageTimer} onUrgentSecond={audio ? countdownSound : undefined}/>}
            {generalKnockout ? <div className="general-bracket-display"><Bracket matches={session.matches} scoreMode/></div> : hasOfficialResults ? <div className="display-podium">{podium.map(place => <article className={`rank-${place.rank}`} key={place.rank}><span>JUARA {place.rank}</span><strong>{place.entry?.name}</strong><PublicEntryDetails entry={place.entry}/></article>)}</div> : <div className="display-participants">{session.participants.slice(0, 20).map(participant => <article className={participant.status} key={participant.id}><strong>{participant.name}</strong><PublicEntryDetails entry={participant}/></article>)}</div>}
        </main> : hasOfficialResults ? <main className="general-display results">
            <p className="eyebrow">HASIL RESMI</p>
            <h1>{session.competition.name}</h1>
            <p className="results-caption"><Trophy size={18}/> Selamat kepada para pemenang</p>
            <div className="display-podium">{podium.map(place => <article className={`rank-${place.rank}`} key={place.rank}><span>JUARA {place.rank}</span><strong>{place.entry?.name}</strong><PublicEntryDetails entry={place.entry}/>{place.result_summary?.score !== undefined && <b>{place.result_summary.score} poin</b>}</article>)}</div>
        </main> : <main className="quiz-display">
            <section className={`display-winner ${session.round?.winner ? 'active' : ''}`} style={session.round?.winner ? { '--team-color': session.round.winner.color } : {}}>
                {session.round?.winner ? <><span>{session.round.phase === 'tiebreak' ? 'PENJAWAB SOAL PENENTU' : 'PENEKAN PERTAMA'}</span><h1>{session.round.winner.name}</h1><p>Silakan menjawab!</p><AnswerCountdown session={session} onUrgentSecond={audio ? countdownSound : undefined}/></> : <><Radio/><span>SOAL {session.question_number || '—'}</span><h1>{session.round?.state === 'open' ? 'BUZZER DIBUKA' : session.round?.opens_at ? 'BERSIAP...' : 'MENUNGGU SOAL'}</h1></>}
            </section>
            <section className="display-scores">{teams.map((team, index) => <article key={team.id} style={{ '--team-color': team.color }}><span>{index + 1}</span><i/><strong>{team.name}<small>{team.affiliation || 'Tanpa afiliasi'} · {(team.members || []).join(', ')}</small>{team.tiebreak_wins ? <small>{team.tiebreak_wins} menang soal penentu</small> : null}</strong><b>{team.score}</b></article>)}</section>
            {session.question_number === 0 && <aside className="display-join"><QRCodeSVG value={joinUrl} size={110}/><div><span>SCAN UNTUK MEMAKAI BUZZER</span><strong>{session.code} · PIN dari panitia</strong></div></aside>}
        </main>}
        <footer><span>{session.competition.name}</span><span><Trophy size={16}/> Semangat, sportif, dan meriah!</span></footer>
    </div></>;
}
