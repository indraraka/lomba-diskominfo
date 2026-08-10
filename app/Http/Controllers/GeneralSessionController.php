<?php

namespace App\Http\Controllers;

use App\Events\SessionStateUpdated;
use App\Models\Competition;
use App\Models\CompetitionSession;
use App\Models\KnockoutMatch;
use App\Models\Participant;
use App\Support\SessionPayload;
use App\Support\WorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class GeneralSessionController extends Controller
{
    public function store(Competition $competition)
    {
        abort_if($competition->status === 'archived', 422, 'Aktifkan lomba terlebih dahulu.');
        $pin = (string) random_int(100000, 999999);
        $settings = [
            'pin_encrypted' => Crypt::encryptString($pin),
            'competition_type' => $competition->type,
            'quiz_format' => $competition->quiz_format,
            'general_format' => $competition->general_format,
            'duration_seconds' => $competition->duration_seconds,
            'timer_mode' => $competition->timer_mode,
            'result_type' => $competition->result_type,
            'result_label' => $competition->result_label,
            'result_unit' => $competition->result_unit,
            'ranking_direction' => $competition->ranking_direction,
            'correct_points' => $competition->correct_points,
            'wrong_points' => $competition->wrong_points,
        ];
        $session = $competition->sessions()->create([
            'code' => $this->uniqueCode(),
            'pin_hash' => Hash::make($pin),
            'settings' => $settings,
            'rules_snapshot' => $competition->rules,
        ]);

        return Redirect::route('sessions.control', $session);
    }

    public function control(CompetitionSession $session)
    {
        return Inertia::render($session->competitionType() === 'quiz' ? 'Sessions/QuizControl' : ($session->competition->workflow_key === 'manual_rank' ? 'Sessions/GeneralControl' : 'Sessions/WorkflowControl'), [
            'initialSession' => SessionPayload::make($session, true),
        ]);
    }

    public function participants(Request $request, CompetitionSession $session)
    {
        abort_unless($session->competitionType() === 'general', 422);
        $data = $request->validate([
            'entries' => ['nullable', 'array', 'min:1', 'max:200'],
            'entries.*.name' => ['required_with:entries', 'string', 'max:100'],
            'entries.*.affiliation' => ['nullable', 'string', 'max:100'],
            'entries.*.members' => ['nullable', 'array'],
            'entries.*.members.*' => ['string', 'max:100'],
            'names' => ['nullable', 'array', 'min:1', 'max:200'],
            'names.*' => ['string', 'max:100'],
        ]);
        $entries = collect($data['entries'] ?? collect($data['names'] ?? [])->map(fn ($name) => ['name' => $name, 'members' => [$name]])->all());
        abort_if($entries->isEmpty(), 422, 'Tambahkan sedikitnya satu peserta atau regu.');
        DB::transaction(function () use ($session, $entries) {
            CompetitionSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();
            $rules = $session->rules_snapshot ?: [];
            $entryRules = $rules['entry'] ?? [];
            $position = (int) $session->participants()->max('position');
            foreach ($entries as $entry) {
                $name = trim($entry['name']);
                if ($name === '') {
                    continue;
                }

                $members = array_values(array_filter(array_map('trim', $entry['members'] ?? [])));
                $min = (int) ($entryRules['members_min'] ?? 1);
                $max = (int) ($entryRules['members_max'] ?? 200);
                abort_unless(count($members) >= $min && count($members) <= $max, 422, "{$name} membutuhkan {$min} sampai {$max} anggota.");
                $affiliation = trim($entry['affiliation'] ?? '') ?: null;
                $limit = (int) (($rules['workflow']['max_per_affiliation'] ?? null) ?: 0);
                if ($limit && $affiliation) {
                    abort_if($session->participants()->whereRaw('lower(affiliation) = ?', [mb_strtolower($affiliation)])->count() >= $limit, 422, "Afiliasi {$affiliation} sudah mencapai batas {$limit} wakil.");
                }

                $participant = $session->participants()->firstOrCreate(['name' => $name], [
                    'affiliation' => $affiliation,
                    'members' => $members,
                    'position' => ++$position,
                ]);
                if ($participant->wasRecentlyCreated) {
                    app(WorkflowService::class)->attachLateParticipant($session, $participant);
                }
            }
        });
        $this->broadcast($session);

        return back();
    }

    public function removeParticipant(CompetitionSession $session, Participant $participant)
    {
        abort_unless($participant->competition_session_id === $session->id, 404);

        DB::transaction(function () use ($session, $participant) {
            $participant = Participant::query()->whereKey($participant->id)->lockForUpdate()->firstOrFail();
            $before = $participant->toArray();

            $session->placements()->delete();
            $session->participants()->update(['final_rank' => null]);
            $participant->delete();
            $session->participants()->orderBy('position')->get()->values()->each(
                fn ($entry, $index) => $entry->update(['position' => $index + 1])
            );

            $updates = [];
            if ($session->competition->workflow_key === 'knockout_score' && $session->matches()->exists()) {
                $session->matches()->delete();
                $updates = ['state' => 'lobby', 'timer_started_at' => null, 'timer_ends_at' => null, 'paused_remaining_ms' => null, 'current_participant_id' => null, 'finished_at' => null];
            } elseif ($session->participants()->doesntExist()) {
                $updates = ['state' => 'lobby', 'timer_started_at' => null, 'timer_ends_at' => null, 'paused_remaining_ms' => null, 'current_participant_id' => null, 'finished_at' => null];
            } elseif ($session->current_participant_id === $participant->id) {
                $updates = ['state' => 'lobby', 'timer_started_at' => null, 'timer_ends_at' => null, 'paused_remaining_ms' => null, 'current_participant_id' => null, 'finished_at' => null];
            } elseif ($session->state === 'finished') {
                $updates = ['state' => 'ready', 'finished_at' => null];
            }
            if ($updates) {
                $session->update($updates);
            }

            app(WorkflowService::class)->audit($session, 'participant.deleted', $participant, $before, null);
        });

        $this->broadcast($session);

        return back()->with('success', 'Peserta atau regu berhasil dihapus.');
    }

    public function updateParticipant(Request $request, CompetitionSession $session, Participant $participant)
    {
        abort_unless($participant->competition_session_id === $session->id && $session->competitionType() === 'general', 404);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('participants', 'name')->where(fn ($query) => $query->where('competition_session_id', $session->id))->ignore($participant->id)],
            'affiliation' => ['nullable', 'string', 'max:100'],
            'members' => ['required', 'array'],
            'members.*' => ['required', 'string', 'max:100'],
        ]);

        $data['name'] = trim($data['name']);
        $data['affiliation'] = trim($data['affiliation'] ?? '') ?: null;
        $data['members'] = array_values(array_filter(array_map('trim', $data['members'])));
        $entryRules = ($session->rules_snapshot ?: [])['entry'] ?? [];
        $min = (int) ($entryRules['members_min'] ?? 1);
        $max = (int) ($entryRules['members_max'] ?? 200);
        abort_unless(count($data['members']) >= $min && count($data['members']) <= $max, 422, "{$data['name']} membutuhkan {$min} sampai {$max} anggota.");

        $limit = (int) ((($session->rules_snapshot ?: [])['workflow']['max_per_affiliation'] ?? null) ?: 0);
        if ($limit && $data['affiliation']) {
            abort_if($session->participants()->where('id', '!=', $participant->id)->whereRaw('lower(affiliation) = ?', [mb_strtolower($data['affiliation'])])->count() >= $limit, 422, "Afiliasi {$data['affiliation']} sudah mencapai batas {$limit} wakil.");
        }

        DB::transaction(function () use ($session, $participant, $data) {
            $before = $participant->toArray();
            $participant->update($data);
            app(WorkflowService::class)->audit($session, 'participant.updated', $participant, $before, $participant->fresh()->toArray());
        });

        $this->broadcast($session);

        return back()->with('success', 'Data peserta atau regu berhasil diperbarui.');
    }

    public function duration(Request $request, CompetitionSession $session)
    {
        abort_unless($session->competitionType() === 'general', 422);
        $duration = $request->validate(['duration_seconds' => ['required', 'integer', 'min:1', 'max:86400']])['duration_seconds'];

        DB::transaction(function () use ($session, $duration) {
            $settings = $session->settings ?? [];
            $settings['duration_seconds'] = $duration;
            $rules = $session->rules_snapshot ?: $session->competition->rules ?: [];
            $rules['workflow']['duration_seconds'] = $duration;
            if ($session->competition->workflow_key === 'multi_round_score') {
                $rules['workflow']['timer_kind'] = 'countdown';
            }
            $sessionUpdates = ['settings' => $settings, 'rules_snapshot' => $rules];
            if ($session->state === 'running' && $session->timer_started_at) {
                $sessionUpdates['timer_ends_at'] = $session->timer_started_at->copy()->addSeconds($duration);
            } elseif ($session->state === 'paused' && $session->paused_remaining_ms !== null) {
                $oldDurationMs = ((int) ($session->settings['duration_seconds'] ?? $session->competition->duration_seconds)) * 1000;
                $elapsedMs = max(0, $oldDurationMs - ((int) $session->paused_remaining_ms));
                $sessionUpdates['paused_remaining_ms'] = max(0, ($duration * 1000) - $elapsedMs);
            }
            $session->update($sessionUpdates);

            $session->stages()->whereNotIn('state', ['completed', 'void'])->where(function ($query) use ($session) {
                $query->where('timer_kind', 'countdown');
                if ($session->competition->workflow_key === 'multi_round_score') {
                    $query->orWhere('phase', 'round');
                }
            })->get()->each(function ($stage) use ($duration) {
                $updates = ['timer_kind' => 'countdown', 'duration_seconds' => $duration, 'config' => [...($stage->config ?? []), 'timer_kind' => 'countdown', 'duration_seconds' => $duration]];
                if ($stage->state === 'running' && $stage->started_at) {
                    $updates['ends_at'] = $stage->started_at->copy()->addSeconds($duration);
                } elseif ($stage->state === 'paused') {
                    $oldDurationMs = ((int) $stage->duration_seconds) * 1000;
                    $elapsedMs = max(0, $oldDurationMs - ((int) $stage->paused_remaining_ms));
                    $updates['paused_remaining_ms'] = max(0, ($duration * 1000) - $elapsedMs);
                }
                $stage->update($updates);
            });
        });

        $this->broadcast($session);

        return back()->with('success', "Durasi countdown diubah menjadi {$duration} detik.");
    }

    public function timer(Request $request, CompetitionSession $session)
    {
        abort_unless($session->competitionType() === 'general', 422);
        $data = $request->validate([
            'action' => ['required', Rule::in(['start', 'pause', 'resume', 'reset', 'finish'])],
            'participant_id' => ['nullable', 'integer'],
        ]);
        $duration = (int) ($session->settings['duration_seconds'] ?? $session->competition->duration_seconds);
        $mode = $session->settings['timer_mode'] ?? $session->competition->timer_mode;
        $format = $session->settings['general_format'] ?? $session->competition->general_format;

        if ($data['action'] === 'start') {
            abort_if($session->participants()->count() === 0, 422, 'Tambahkan peserta terlebih dahulu.');
            $activeMatch = $format === 'knockout' ? $session->matches()->where('state', 'active')->first() : null;
            abort_if($format === 'knockout' && ! $activeMatch, 422, 'Aktifkan pertandingan terlebih dahulu.');
            $starts = now()->addSeconds(3);
            $session->update(['state' => 'running', 'timer_started_at' => $starts, 'timer_ends_at' => $starts->copy()->addSeconds($duration), 'paused_remaining_ms' => null, 'current_participant_id' => $mode === 'individual' ? $data['participant_id'] : null]);
            if ($mode === 'individual') {
                abort_unless($session->participants()->whereKey($data['participant_id'])->exists(), 422);
                $session->participants()->whereKey($data['participant_id'])->update(['status' => 'running']);
            } elseif ($format === 'knockout') {
                $session->participants()->whereIn('id', [$activeMatch->participant_a_id, $activeMatch->participant_b_id])->update(['status' => 'running']);
            } else {
                $session->participants()->where('status', 'waiting')->update(['status' => 'running']);
            }
        } elseif ($data['action'] === 'pause') {
            abort_unless($session->state === 'running', 422);
            $remaining = max(0, now()->diffInMilliseconds($session->timer_ends_at, false));
            $session->update(['state' => 'paused', 'paused_remaining_ms' => $remaining, 'timer_ends_at' => null]);
        } elseif ($data['action'] === 'resume') {
            abort_unless($session->state === 'paused', 422);
            $session->update(['state' => 'running', 'timer_started_at' => now(), 'timer_ends_at' => now()->addMilliseconds($session->paused_remaining_ms), 'paused_remaining_ms' => null]);
        } elseif ($data['action'] === 'reset') {
            $session->update(['state' => $format === 'knockout' && $session->matches()->exists() ? 'ready' : 'lobby', 'timer_started_at' => null, 'timer_ends_at' => null, 'paused_remaining_ms' => null, 'current_participant_id' => null]);
            $session->participants()->update(['status' => 'waiting']);
        } else {
            $session->update(['state' => 'finished', 'timer_ends_at' => null, 'finished_at' => now()]);
        }
        $this->broadcast($session);

        return back();
    }

    public function result(Request $request, CompetitionSession $session, Participant $participant)
    {
        abort_unless($participant->competition_session_id === $session->id, 404);
        $data = $request->validate([
            'status' => ['required', Rule::in(['finished', 'timeout', 'disqualified'])],
            'result_value' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);
        $participant->update($data);
        if ($session->current_participant_id === $participant->id) {
            $session->update(['current_participant_id' => null, 'state' => 'lobby', 'timer_ends_at' => null]);
        }
        $this->broadcast($session);

        return back();
    }

    public function generateBracket(Request $request, CompetitionSession $session)
    {
        abort_unless($session->competitionType() === 'general' && ($session->settings['general_format'] ?? $session->competition->general_format) === 'knockout', 422);
        abort_if($session->matches()->exists(), 422, 'Bracket sudah dibuat.');
        $participantIds = $session->participants()->pluck('id')->shuffle()->values()->all();
        $max = (int) ($session->rules_snapshot['workflow']['max_entries'] ?? 16);
        abort_unless(count($participantIds) >= 2 && count($participantIds) <= $max, 422, "Sistem gugur membutuhkan 2 sampai {$max} peserta atau regu.");

        $options = $request->validate(['play_third_place' => ['nullable', 'boolean']]);
        $session->update(['settings' => [...($session->settings ?? []), 'workflow_options' => ['play_third_place' => (bool) ($options['play_third_place'] ?? false)]]]);
        DB::transaction(fn () => $this->createInitialMatches($session, $participantIds));
        $session->update(['state' => 'ready']);
        $this->broadcast($session);

        return back();
    }

    public function activateMatch(CompetitionSession $session, KnockoutMatch $match)
    {
        abort_unless($match->competition_session_id === $session->id && $match->state !== 'completed', 404);
        DB::transaction(function () use ($session, $match) {
            $session->matches()->where('state', 'active')->update(['state' => 'pending']);
            $match->update(['state' => 'active']);
            $session->participants()->update(['status' => 'waiting']);
            $session->update(['state' => 'ready', 'timer_started_at' => null, 'timer_ends_at' => null, 'paused_remaining_ms' => null]);
        });
        $this->broadcast($session);

        return back();
    }

    public function completeMatch(Request $request, CompetitionSession $session, KnockoutMatch $match)
    {
        abort_unless($match->competition_session_id === $session->id && $match->state === 'active', 422, 'Aktifkan pertandingan terlebih dahulu.');
        $data = $request->validate(['winner_team_id' => ['required', 'integer']]);
        abort_unless(in_array((int) $data['winner_team_id'], [$match->participant_a_id, $match->participant_b_id], true), 422, 'Pemenang harus berasal dari pertandingan ini.');

        DB::transaction(function () use ($session, $match, $data) {
            $match->update(['winner_participant_id' => $data['winner_team_id'], 'state' => 'completed']);
            $session->participants()->whereIn('id', [$match->participant_a_id, $match->participant_b_id])->update(['status' => 'waiting']);
            $session->update(['timer_started_at' => null, 'timer_ends_at' => null, 'paused_remaining_ms' => null]);
            if ($match->bracket_type === 'third_place') {
                $session->update(['state' => 'finished', 'finished_at' => now()]);
                $this->saveKnockoutPlacements($session);

                return;
            }
            $round = $match->round_number;

            if ($session->matches()->where('round_number', $round)->where('state', '!=', 'completed')->exists()) {
                $session->matches()->where('round_number', $round)->where('state', 'pending')->orderBy('match_number')->first()?->update(['state' => 'active']);
                $session->update(['state' => 'ready']);

                return;
            }

            $winners = $session->matches()->where('round_number', $round)->orderBy('match_number')->pluck('winner_participant_id')->filter()->values()->all();
            if (count($winners) === 1) {
                $third = $session->matches()->where('bracket_type', 'third_place')->where('state', 'pending')->first();
                if ($third) {
                    $third->update(['state' => 'active']);
                    $session->update(['state' => 'ready']);
                } else {
                    $session->update(['state' => 'finished', 'finished_at' => now()]);
                    $this->saveKnockoutPlacements($session);
                }

                return;
            }

            $this->createMatches($session, $round + 1, $winners);
            if (count($winners) === 2 && ($session->settings['workflow_options']['play_third_place'] ?? false)) {
                $losers = $session->matches()->where('round_number', $round)->where('bracket_type', 'main')->get()->map(fn ($played) => $played->winner_participant_id === $played->participant_a_id ? $played->participant_b_id : $played->participant_a_id)->filter()->values();
                if ($losers->count() === 2) {
                    $session->matches()->create(['bracket_type' => 'third_place', 'round_number' => $round + 1, 'match_number' => 2, 'participant_a_id' => $losers[0], 'participant_b_id' => $losers[1], 'state' => 'pending']);
                }
            }
            $session->update(['state' => 'ready']);
        });
        $this->broadcast($session);

        return back();
    }

    public function scoreMatch(Request $request, CompetitionSession $session, KnockoutMatch $match)
    {
        abort_unless($match->competition_session_id === $session->id && $match->state === 'active', 422, 'Pertandingan tidak aktif.');
        $data = $request->validate(['participant_id' => ['required', 'integer']]);
        abort_unless(in_array((int) $data['participant_id'], [$match->participant_a_id, $match->participant_b_id], true), 422);
        $field = (int) $data['participant_id'] === $match->participant_a_id ? 'score_a' : 'score_b';
        $match->increment($field);
        $match->refresh();
        $this->broadcast($session);
        if ($match->{$field} >= (int) ($session->rules_snapshot['workflow']['points_to_win'] ?? 7)) {
            return $this->completeMatch(new Request(['winner_team_id' => $data['participant_id']]), $session, $match);
        }

        return back();
    }

    private function uniqueCode(): string
    {
        do {
            $code = strtoupper(Str::random(6));
        } while (CompetitionSession::where('code', $code)->exists());

        return $code;
    }

    private function broadcast(CompetitionSession $session): void
    {
        SessionStateUpdated::dispatch($session->code, SessionPayload::make($session));
    }

    private function createMatches(CompetitionSession $session, int $round, array $participantIds): void
    {
        $matchNumber = 1;
        foreach (array_chunk($participantIds, 2) as $pair) {
            $bye = count($pair) === 1;
            $session->matches()->create([
                'round_number' => $round,
                'match_number' => $matchNumber++,
                'participant_a_id' => $pair[0],
                'participant_b_id' => $pair[1] ?? null,
                'winner_participant_id' => $bye ? $pair[0] : null,
                'state' => $bye ? 'completed' : 'pending',
            ]);
        }
        $session->matches()->where('round_number', $round)->where('state', 'pending')->orderBy('match_number')->first()?->update(['state' => 'active']);
    }

    private function createInitialMatches(CompetitionSession $session, array $participantIds): void
    {
        $count = count($participantIds);
        $size = 1;
        while ($size < $count) {
            $size *= 2;
        }
        $byeCount = $size - $count;
        $pairs = [];
        for ($i = 0; $i < $byeCount; $i++) {
            $pairs[] = [$participantIds[$i], null];
        }
        foreach (array_chunk(array_slice($participantIds, $byeCount), 2) as $pair) {
            $pairs[] = $pair;
        }
        foreach ($pairs as $index => $pair) {
            $bye = empty($pair[1]);
            $session->matches()->create(['bracket_type' => 'main', 'round_number' => 1, 'match_number' => $index + 1, 'participant_a_id' => $pair[0], 'participant_b_id' => $pair[1] ?? null, 'winner_participant_id' => $bye ? $pair[0] : null, 'state' => $bye ? 'completed' : 'pending']);
        }
        $session->matches()->where('round_number', 1)->where('state', 'pending')->orderBy('match_number')->first()?->update(['state' => 'active']);
    }

    private function saveKnockoutPlacements(CompetitionSession $session): void
    {
        $final = $session->matches()->where('bracket_type', 'main')->orderByDesc('round_number')->orderBy('match_number')->first();
        if (! $final?->winner_participant_id) {
            return;
        }
        $runnerUp = $final->winner_participant_id === $final->participant_a_id ? $final->participant_b_id : $final->participant_a_id;
        $thirdMatch = $session->matches()->where('bracket_type', 'third_place')->first();
        $third = $thirdMatch?->winner_participant_id;
        if (! $third) {
            $semis = $session->matches()->where('bracket_type', 'main')->where('round_number', $final->round_number - 1)->get();
            $third = $semis->map(fn ($semi) => ['id' => $semi->winner_participant_id === $semi->participant_a_id ? $semi->participant_b_id : $semi->participant_a_id, 'score' => $semi->winner_participant_id === $semi->participant_a_id ? $semi->score_b : $semi->score_a])->filter(fn ($item) => $item['id'])->sortByDesc('score')->first()['id'] ?? null;
        }
        $session->placements()->delete();
        foreach (array_filter([$final->winner_participant_id, $runnerUp, $third]) as $index => $participantId) {
            $session->placements()->create(['participant_id' => $participantId, 'rank' => $index + 1, 'result_summary' => ['method' => $index === 2 && ! $thirdMatch ? 'best_semifinal_score' : 'match'], 'decided_by' => auth()->id()]);
            $session->participants()->whereKey($participantId)->update(['final_rank' => $index + 1]);
        }
    }
}
