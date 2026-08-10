import { router } from '@inertiajs/react';
import { Check, Play, Trophy } from 'lucide-react';

export default function Bracket({ matches = [], sessionId = null, interactive = false, routePrefix = 'general', scoreMode = false }) {
    const rounds = matches.reduce((groups, match) => {
        (groups[match.round_number] ||= []).push(match);
        return groups;
    }, {});
    const roundNumbers = Object.keys(rounds).map(Number).sort((a, b) => a - b);
    const champion = matches.length ? [...matches].reverse().find(match => match.state === 'completed' && roundNumbers.length > 0 && match.round_number === Math.max(...roundNumbers) && rounds[match.round_number].length === 1)?.winner : null;

    if (! matches.length) return <div className="mini-empty">Bracket belum dibuat.</div>;

    return <div className="bracket-wrap">
        <div className="bracket-rounds">
            {roundNumbers.map((roundNumber, index) => <section className="bracket-round" key={roundNumber}>
                <h3>{index === roundNumbers.length - 1 && rounds[roundNumber].length === 1 ? 'Final' : `Babak ${roundNumber}`}</h3>
                <div className="bracket-matches">
                    {rounds[roundNumber].map(match => <article className={`bracket-match ${match.state}`} key={match.id}>
                        <header><span>Match {match.match_number}</span><b>{match.state === 'active' ? 'Sedang bermain' : match.state === 'completed' ? 'Selesai' : 'Menunggu'}</b></header>
                        {[match.team_a, match.team_b].map((team, teamIndex) => <div className={`bracket-team ${team && match.winner_team_id === team.id ? 'winner' : ''}`} key={team?.id || `bye-${teamIndex}`}>
                            <i style={{ background: team?.color || '#d7cec2' }}/><strong>{team?.name || 'BYE'}</strong>
                            {team && scoreMode && <b>{teamIndex === 0 ? match.score_a : match.score_b}</b>}
                            {team && match.winner_team_id === team.id && <Trophy size={15}/>}
                            {interactive && team && match.state === 'active' && (scoreMode ? <button onClick={() => router.post(route(`${routePrefix}.matches.score`, [sessionId, match.id]), { participant_id: team.id }, { preserveScroll: true })}>+1</button> : <button onClick={() => router.post(route(`${routePrefix}.matches.complete`, [sessionId, match.id]), { winner_team_id: team.id }, { preserveScroll: true })}><Check size={14}/> Menang</button>)}
                        </div>)}
                        {interactive && match.state === 'pending' && <button className="activate-match" onClick={() => router.post(route(`${routePrefix}.matches.activate`, [sessionId, match.id]), {}, { preserveScroll: true })}><Play size={14}/> Aktifkan pertandingan</button>}
                    </article>)}
                </div>
            </section>)}
        </div>
        {champion && <div className="champion-banner"><Trophy/><div><span>JUARA SISTEM GUGUR</span><strong>{champion.name}</strong></div></div>}
    </div>;
}
