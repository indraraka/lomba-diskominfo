<?php

namespace App\Http\Controllers;

use App\Events\SessionStateUpdated;
use App\Models\CompetitionIncident;
use App\Models\CompetitionSession;
use App\Models\QuizTeam;
use App\Models\ScoreTransaction;
use App\Models\SessionPlacement;
use App\Support\QuizTeamStyle;
use App\Support\SessionPayload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class QuizSessionController extends Controller
{
    public function teams(Request $request, CompetitionSession $session)
    {
        abort_unless($session->competitionType() === 'quiz', 422);
        abort_if($session->question_number > 0, 422, 'Grup tidak dapat ditambahkan setelah lomba dimulai.');

        $data = $request->validate([
            'names' => ['nullable', 'array', 'min:1', 'max:6'],
            'names.*' => ['string', 'max:80'],
            'entries' => ['nullable', 'array', 'min:1', 'max:6'],
            'entries.*.name' => ['required_with:entries', 'string', 'max:80'],
            'entries.*.affiliation' => ['nullable', 'string', 'max:100'],
            'entries.*.members' => ['required_with:entries', 'array', 'size:3'],
            'entries.*.members.*' => ['required', 'string', 'max:100'],
        ]);
        abort_if(empty($data['entries']) && empty($data['names']), 422, 'Data regu wajib diisi.');

        DB::transaction(function () use ($session, $data) {
            CompetitionSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();
            $existingNames = $session->teams()->pluck('name')->map(fn ($name) => mb_strtolower(trim($name)))->all();
            $entries = collect($data['entries'] ?? collect($data['names'] ?? [])->map(fn ($name) => ['name' => $name, 'members' => ["{$name} 1", "{$name} 2", "{$name} 3"]])->all())
                ->map(fn ($entry) => [...$entry, 'name' => trim($entry['name'])])
                ->filter(fn ($entry) => $entry['name'] !== '')
                ->unique(fn ($entry) => mb_strtolower($entry['name']));

            abort_if($session->teams()->count() + $entries->count() > 6, 422, 'Maksimal 6 grup dalam satu sesi.');

            foreach ($entries as $entry) {
                $name = $entry['name'];
                abort_if(in_array(mb_strtolower($name), $existingNames, true), 422, "Nama grup {$name} sudah digunakan.");
                $style = QuizTeamStyle::at($session->teams()->count());
                $session->teams()->create([
                    'name' => $name,
                    'affiliation' => trim($entry['affiliation'] ?? '') ?: null,
                    'members' => array_values(array_map('trim', $entry['members'])),
                    'color' => $style['color'],
                    'pitch_hz' => $style['pitch'],
                    'token_hash' => hash('sha256', Str::random(64)),
                    'approved' => true,
                ]);
                $existingNames[] = mb_strtolower($name);
            }
        });

        $this->broadcast($session);

        return back();
    }

    public function mandatory(Request $request, CompetitionSession $session)
    {
        abort_unless($session->competitionType() === 'quiz' && $session->question_number < 10, 422, 'Babak wajib sudah selesai.');
        $data = $request->validate(['answers' => ['required', 'array'], 'answers.*' => ['boolean']]);
        $approved = $session->teams()->where('approved', true)->get();
        abort_if($approved->isEmpty(), 422, 'Belum ada tim yang disetujui.');
        abort_unless($approved->every(fn ($team) => array_key_exists((string) $team->id, $data['answers']) || array_key_exists($team->id, $data['answers'])), 422, 'Catat jawaban semua tim.');
        $correctPoints = (int) (($session->settings ?? [])['correct_points'] ?? $session->competition->correct_points ?? 10);
        $number = $session->question_number + 1;
        DB::transaction(function () use ($session, $approved, $data, $number, $correctPoints) {
            $round = $session->rounds()->create(['number' => $number, 'phase' => 'mandatory', 'state' => 'closed', 'answer_attempt' => 1, 'max_attempts' => 1, 'eligible_team_ids' => $approved->pluck('id')->all(), 'eliminated_team_ids' => []]);
            foreach ($approved as $team) {
                if (! filter_var($data['answers'][$team->id] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                    continue;
                }
                $team->increment('score', $correctPoints);
                $team->refresh();
                $session->scores()->create(['quiz_team_id' => $team->id, 'buzzer_round_id' => $round->id, 'kind' => 'correct', 'delta' => $correctPoints, 'balance_after' => $team->score, 'reason' => 'Soal wajib '.$number]);
            }
            $session->update(['question_number' => $number, 'state' => 'ready']);
        });
        $this->broadcast($session);

        return back();
    }

    public function removeTeam(CompetitionSession $session, QuizTeam $team)
    {
        abort_unless($team->competition_session_id === $session->id && $session->competitionType() === 'quiz', 404);
        abort_if($session->question_number > 0, 422, 'Grup tidak dapat dihapus setelah lomba dimulai.');
        $team->delete();
        $this->broadcast($session);

        return back();
    }

    public function approve(CompetitionSession $session, QuizTeam $team)
    {
        abort_unless($team->competition_session_id === $session->id && $session->competitionType() === 'quiz', 404);
        $team->update(['approved' => ! $team->approved]);
        $this->broadcast($session);

        return back();
    }

    public function nextRound(CompetitionSession $session)
    {
        abort_unless($session->competitionType() === 'quiz', 422);
        abort_if($session->question_number < 10, 422, 'Selesaikan 10 soal wajib terlebih dahulu.');
        abort_if($session->question_number >= 20, 422, 'Sepuluh soal rebutan sudah selesai.');
        abort_if($session->rounds()->whereNot('state', 'closed')->exists(), 422, 'Selesaikan soal aktif sebelum membuat soal berikutnya.');
        $number = $session->question_number + 1;
        $session->rounds()->create(['number' => $number, 'phase' => 'rebuttal', 'state' => 'ready', 'answer_attempt' => 0, 'max_attempts' => 2, 'eligible_team_ids' => $session->teams()->where('approved', true)->pluck('id')->all(), 'eliminated_team_ids' => []]);
        $session->update(['question_number' => $number, 'state' => 'ready']);
        $this->broadcast($session);

        return back();
    }

    public function tiebreak(Request $request, CompetitionSession $session)
    {
        abort_unless($session->competitionType() === 'quiz', 422);
        abort_if($session->state === 'finished', 422, 'Hasil lomba sudah difinalisasi.');
        abort_if($session->question_number < 20, 422, 'Tie-break hanya tersedia setelah 20 soal utama.');
        abort_if($session->rounds()->where('phase', 'tiebreak')->count() >= 3, 422, 'Batas 3 soal tie-break tercapai.');
        abort_if($session->rounds()->whereNot('state', 'closed')->exists(), 422, 'Selesaikan soal aktif sebelum membuat soal tie-break.');
        $data = $request->validate(['team_ids' => ['required', 'array', 'min:2'], 'team_ids.*' => ['integer']]);
        abort_unless($session->teams()->whereIn('id', $data['team_ids'])->where('approved', true)->count() === count(array_unique($data['team_ids'])), 422);
        $selectedTeams = $session->teams()->whereIn('id', $data['team_ids'])->get();
        $tiebreakWins = $session->rounds()->where('phase', 'tiebreak')->where('state', 'closed')->whereNotNull('winner_team_id')
            ->selectRaw('winner_team_id, count(*) as wins')->groupBy('winner_team_id')->pluck('wins', 'winner_team_id');
        abort_unless($selectedTeams->map(fn ($team) => $team->score.'|'.((int) $tiebreakWins->get($team->id, 0)))->unique()->count() === 1, 422, 'Soal tie-break hanya untuk regu yang peringkatnya masih seri.');
        $number = $session->question_number + 1;
        $session->rounds()->create(['number' => $number, 'phase' => 'tiebreak', 'state' => 'ready', 'answer_attempt' => 0, 'max_attempts' => count($data['team_ids']), 'eligible_team_ids' => array_values(array_unique($data['team_ids'])), 'eliminated_team_ids' => []]);
        $session->update(['question_number' => $number, 'state' => 'ready']);
        $this->broadcast($session);

        return back();
    }

    public function open(CompetitionSession $session)
    {
        $round = $session->rounds()->latest('number')->firstOrFail();
        abort_if($round->state === 'closed', 422, 'Buat soal berikutnya.');
        abort_if($round->phase === 'mandatory', 422, 'Soal wajib tidak menggunakan buzzer.');
        $round->update(['state' => 'ready', 'opens_at' => now()->addSeconds(3), 'winner_team_id' => null, 'locked_at' => null]);
        $session->update(['state' => 'running']);
        $this->broadcast($session);

        return back();
    }

    public function score(Request $request, CompetitionSession $session)
    {
        abort_unless($session->competitionType() === 'quiz', 422);
        abort_if($session->state === 'finished', 422, 'Hasil lomba sudah difinalisasi.');
        $data = $request->validate([
            'action' => ['required', 'in:correct,wrong,adjustment'],
            'team_id' => ['nullable', 'required_if:action,adjustment', 'integer'],
            'delta' => ['nullable', 'required_if:action,adjustment', 'integer', 'min:-9999', 'max:9999'],
            'reason' => ['nullable', 'string', 'max:300'],
        ]);
        abort_if($data['action'] === 'adjustment' && blank($data['reason']), 422, 'Alasan koreksi wajib diisi.');

        DB::transaction(function () use ($session, $data) {
            $round = null;
            if ($data['action'] === 'adjustment') {
                $team = $session->teams()->whereKey($data['team_id'])->lockForUpdate()->firstOrFail();
                $delta = (int) $data['delta'];
            } else {
                $round = $session->rounds()->latest('number')->lockForUpdate()->first();
                abort_unless($round && $round->state === 'locked' && $round->winner_team_id, 422, 'Belum ada penjawab yang dapat dinilai.');
                $team = $session->teams()->whereKey($round->winner_team_id)->lockForUpdate()->firstOrFail();
                $settings = $session->settings ?? [];
                $delta = $round->phase === 'tiebreak' ? 0 : ($data['action'] === 'correct'
                    ? (int) ($settings['correct_points'] ?? 10)
                    : (int) ($settings['wrong_points'] ?? -5));
            }

            if ($delta !== 0) {
                $team->increment('score', $delta);
            }
            $team->refresh();
            $session->scores()->create([
                'quiz_team_id' => $team->id,
                'buzzer_round_id' => $round?->id,
                'kind' => $data['action'],
                'delta' => $delta,
                'balance_after' => $team->score,
                'reason' => $data['reason'] ?? null,
            ]);

            if ($data['action'] === 'correct' && $round) {
                $round->update(['state' => 'closed']);
                $session->update(['state' => 'ready']);
            } elseif ($data['action'] === 'wrong' && $round) {
                $eliminated = array_values(array_unique([...($round->eliminated_team_ids ?? []), $team->id]));
                $attempt = $round->answer_attempt + 1;
                $eligibleCount = count($round->eligible_team_ids ?: $session->teams()->where('approved', true)->pluck('id')->all());
                $limitReached = $round->phase === 'rebuttal' ? $attempt >= 2 : $attempt >= min($round->max_attempts, $eligibleCount);
                $round->update([
                    'state' => $limitReached ? 'closed' : 'ready',
                    'answer_attempt' => $attempt,
                    'eliminated_team_ids' => $eliminated,
                    'winner_team_id' => null,
                    'locked_at' => null,
                    'opens_at' => $limitReached ? null : now()->addMilliseconds(1500),
                ]);
                $session->update(['state' => $limitReached ? 'ready' : 'running']);
            }
        });
        $this->broadcast($session);

        return back();
    }

    public function finalizeRankings(Request $request, CompetitionSession $session)
    {
        abort_unless($session->competitionType() === 'quiz', 422);
        abort_if($session->state === 'finished', 422, 'Hasil lomba sudah difinalisasi.');
        abort_if($session->question_number < 20, 422, 'Selesaikan 20 soal utama terlebih dahulu.');
        abort_if($session->rounds()->whereNot('state', 'closed')->exists(), 422, 'Selesaikan soal aktif sebelum finalisasi juara.');
        $data = $request->validate(['team_ids' => ['nullable', 'array', 'min:1', 'max:3'], 'team_ids.*' => ['integer'], 'reason' => ['nullable', 'string', 'max:500']]);
        if (isset($data['team_ids'])) {
            abort_unless($session->teams()->whereIn('id', $data['team_ids'])->where('approved', true)->count() === count(array_unique($data['team_ids'])), 422, 'Urutan regu tidak valid.');
        }
        $tiebreakWins = $session->rounds()->where('phase', 'tiebreak')->where('state', 'closed')->whereNotNull('winner_team_id')
            ->selectRaw('winner_team_id, count(*) as wins')->groupBy('winner_team_id')->pluck('wins', 'winner_team_id');
        $automatic = $session->teams()->where('approved', true)->get()->sort(function ($a, $b) use ($tiebreakWins) {
            $score = $b->score <=> $a->score;

            return $score !== 0 ? $score : ((int) $tiebreakWins->get($b->id, 0) <=> (int) $tiebreakWins->get($a->id, 0));
        })->values();
        $teams = isset($data['team_ids']) && filled($data['reason'] ?? null)
            ? $session->teams()->whereIn('id', $data['team_ids'])->get()->sortBy(fn ($team) => array_search($team->id, $data['team_ids'], true))->values()
            : $automatic;
        $top = $teams->take(3);
        $hasTies = $top->groupBy(fn ($team) => $team->score.'|'.((int) $tiebreakWins->get($team->id, 0)))->contains(fn ($group) => $group->count() > 1);
        abort_if($hasTies && blank($data['reason'] ?? null), 422, 'Urutan seri memerlukan hasil tie-break atau alasan keputusan juri.');
        DB::transaction(function () use ($session, $top, $data, $tiebreakWins) {
            $session->placements()->delete();
            $session->teams()->update(['final_rank' => null]);
            foreach ($top as $index => $team) {
                $rank = $index + 1;
                SessionPlacement::create(['competition_session_id' => $session->id, 'quiz_team_id' => $team->id, 'rank' => $rank, 'result_summary' => ['score' => $team->score, 'tiebreak_wins' => (int) $tiebreakWins->get($team->id, 0)], 'decision_reason' => $data['reason'] ?? null, 'decided_by' => auth()->id()]);
                $team->update(['final_rank' => $rank]);
            }
            $session->update(['state' => 'finished', 'finished_at' => now()]);
        });
        $this->broadcast($session);

        return back();
    }

    public function externalHelp(Request $request, CompetitionSession $session, QuizTeam $team)
    {
        abort_unless($team->competition_session_id === $session->id, 404);
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $count = $session->incidents()->where('quiz_team_id', $team->id)->where('rule_code', 'external_help')->whereNull('reversed_at')->count();
        DB::transaction(function () use ($session, $team, $data, $count) {
            if ($count === 0) {
                $team->decrement('score', 20);
                $team->refresh();
                $session->scores()->create(['quiz_team_id' => $team->id, 'kind' => 'adjustment', 'delta' => -20, 'balance_after' => $team->score, 'reason' => 'Bantuan luar: '.$data['reason']]);
            } else {
                $team->update(['approved' => false]);
            }
            CompetitionIncident::create(['competition_session_id' => $session->id, 'quiz_team_id' => $team->id, 'rule_code' => 'external_help', 'kind' => $count === 0 ? 'point_penalty' : 'disqualification', 'value' => $count === 0 ? -20 : null, 'reason' => $data['reason'], 'recorded_by' => auth()->id()]);
        });
        $this->broadcast($session);

        return back();
    }

    public function finish(CompetitionSession $session)
    {
        $session->rounds()->whereNot('state', 'closed')->update(['state' => 'closed']);
        $session->update(['state' => 'finished', 'finished_at' => now()]);
        $this->broadcast($session);

        return back();
    }

    public function reverse(CompetitionSession $session, ScoreTransaction $transaction)
    {
        abort_unless($transaction->competition_session_id === $session->id, 404);
        abort_if($transaction->kind === 'reversal' || $session->scores()->where('reverses_id', $transaction->id)->exists(), 422, 'Transaksi sudah dibatalkan.');
        DB::transaction(function () use ($session, $transaction) {
            $team = $session->teams()->whereKey($transaction->quiz_team_id)->lockForUpdate()->firstOrFail();
            $delta = -$transaction->delta;
            $team->increment('score', $delta);
            $team->refresh();
            $session->scores()->create([
                'quiz_team_id' => $team->id,
                'buzzer_round_id' => $transaction->buzzer_round_id,
                'kind' => 'reversal',
                'delta' => $delta,
                'balance_after' => $team->score,
                'reason' => 'Membatalkan transaksi #'.$transaction->id,
                'reverses_id' => $transaction->id,
            ]);
        });
        $this->broadcast($session);

        return back();
    }

    private function broadcast(CompetitionSession $session): void
    {
        SessionStateUpdated::dispatch($session->code, SessionPayload::make($session));
    }
}
