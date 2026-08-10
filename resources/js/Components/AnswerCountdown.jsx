import Countdown from '@/Components/Countdown';

export default function AnswerCountdown({ session, compact = false, onUrgentSecond }) {
    if (session.round?.state !== 'locked' || !session.round?.locked_at || !session.round?.winner_team_id) return null;

    const answerSeconds = Number(session.rules?.workflow?.answer_seconds || 15);
    const startedAt = new Date(session.round.locked_at);
    const timerSession = {
        server_now: session.server_now,
        state: 'running',
        timer_started_at: startedAt.toISOString(),
        timer_ends_at: new Date(startedAt.getTime() + answerSeconds * 1000).toISOString(),
        competition: { duration_seconds: answerSeconds },
    };

    return (
        <div className="answer-countdown">
            <span>Maksimal waktu menjawab</span>
            <Countdown session={timerSession} compact={compact} onUrgentSecond={onUrgentSecond}/>
        </div>
    );
}
