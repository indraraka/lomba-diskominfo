<?php

namespace App\Support;

use App\Models\CompetitionAuditLog;
use App\Models\CompetitionSession;
use App\Models\CompetitionStage;
use App\Models\Participant;
use App\Models\SessionPlacement;
use App\Models\StageResult;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class WorkflowService
{
    public function attachLateParticipant(CompetitionSession $session, Participant $participant): void
    {
        $session->placements()->delete();
        $session->participants()->update(['final_rank' => null]);
        if ($session->state === 'finished') {
            $session->update(['state' => 'ready', 'finished_at' => null]);
        }

        if (! $session->stages()->exists()) {
            $this->audit($session, 'participant.added', $participant, null, $participant->toArray());

            return;
        }

        $workflow = $session->competition->workflow_key;
        $openStages = $session->stages()
            ->whereIn('state', ['draft', 'ready', 'running', 'paused'])
            ->orderBy('sequence')
            ->orderBy('heat_number')
            ->get();

        if ($workflow === 'multi_round_score' && $openStages->isNotEmpty()) {
            foreach ($openStages as $stage) {
                $stage->results()->firstOrCreate(['participant_id' => $participant->id]);
            }
        } elseif ($openStages->isNotEmpty()) {
            $target = $openStages->first(fn ($stage) => in_array($stage->state, ['running', 'paused'], true)) ?? $openStages->first();
            $target->results()->firstOrCreate(['participant_id' => $participant->id]);
        } else {
            $lastStage = $session->stages()->latest('sequence')->latest('id')->firstOrFail();
            $config = $lastStage->config ?? ($session->rules_snapshot['workflow'] ?? []);
            $sequence = ((int) $session->stages()->max('sequence')) + 1;
            $this->createStage($session, 'late_entry', $sequence, null, 'Babak peserta tambahan', $config, collect([$participant]));
            $session->update(['state' => 'ready']);
        }

        $this->audit($session, 'participant.added', $participant, null, $participant->toArray());
    }

    public function prepare(CompetitionSession $session, array $options): void
    {
        abort_if($session->stages()->exists() || $session->matches()->exists(), 422, 'Babak sudah disiapkan.');
        abort_if(! $session->checklist_confirmed_at, 422, 'Konfirmasi checklist perlengkapan dan keselamatan terlebih dahulu.');
        $rules = $session->rules_snapshot ?: $session->competition->rules;
        $workflow = $session->competition->workflow_key;
        $participants = $session->participants()->get();
        abort_if($participants->isEmpty(), 422, 'Tambahkan peserta atau regu terlebih dahulu.');

        if ($workflow === 'knockout_score') {
            return;
        }

        $config = $rules['workflow'] ?? [];
        if (! blank($options['remaining_unit'] ?? null)) {
            $config['remaining_unit'] = $options['remaining_unit'];
        }
        if ($workflow === 'metric_race') {
            $capacity = max(1, min(50, (int) ($options['heat_capacity'] ?? $participants->count())));
            $shuffled = $participants->shuffle();
            foreach ($shuffled->chunk($capacity)->values() as $index => $chunk) {
                $this->createStage($session, 'heat', $index + 1, $index + 1, 'Babak '.($index + 1), $config, $chunk);
            }
        } elseif ($workflow === 'judged_rubric') {
            $this->createStage($session, 'judging', 1, null, 'Penataan dan penjurian', $config, $participants);
        } elseif ($workflow === 'multi_round_score') {
            for ($round = 1; $round <= (int) $config['rounds']; $round++) {
                $this->createStage($session, 'round', $round, null, 'Sesi '.$round, $config, $participants);
            }
        } elseif ($workflow === 'elimination') {
            $this->createStage($session, 'elimination', 1, null, 'Putaran 1', $config + ['command' => $options['command'] ?? 'step_on_color'], $participants);
        }
        $session->update(['state' => 'ready']);
        $this->audit($session, 'workflow.prepared', null, null, ['options' => $options]);
    }

    public function saveResult(CompetitionSession $session, CompetitionStage $stage, array $data): StageResult
    {
        abort_unless($stage->competition_session_id === $session->id, 404);
        abort_unless($stage->results()->where('participant_id', $data['participant_id'])->exists(), 422, 'Peserta tidak berada pada babak ini.');
        $result = $stage->results()->where('participant_id', $data['participant_id'])->firstOrFail();
        $before = $result->toArray();
        $workflow = $session->competition->workflow_key;
        $metrics = $data['metrics'] ?? [];

        if ($workflow === 'judged_rubric') {
            $limits = ($session->rules_snapshot['workflow']['rubric'] ?? []);
            foreach ($limits as $key => $max) {
                abort_unless(isset($metrics[$key]) && is_numeric($metrics[$key]) && $metrics[$key] >= 0 && $metrics[$key] <= $max, 422, "Nilai {$key} harus 0-{$max}.");
            }
            $data['primary_value'] = array_sum(array_intersect_key($metrics, $limits));
            $data['outcome'] = 'finished';
        } elseif ($workflow === 'multi_round_score') {
            abort_unless(isset($metrics['correct_answers']) && is_numeric($metrics['correct_answers']) && $metrics['correct_answers'] >= 0, 422, 'Jumlah jawaban benar tidak valid.');
            $data['primary_value'] = $metrics['correct_answers'];
            $data['outcome'] = 'finished';
        } elseif ($workflow === 'elimination') {
            abort_unless(in_array($data['outcome'], ['passed', 'failed'], true), 422, 'Hasil eliminasi harus lolos atau gagal.');
        }

        $result->update([
            'outcome' => $data['outcome'],
            'primary_value' => $data['primary_value'] ?? null,
            'secondary_value' => $data['secondary_value'] ?? null,
            'metrics' => $metrics,
            'notes' => $data['notes'] ?? null,
        ]);
        $this->audit($session, 'result.updated', $result, $before, $result->fresh()->toArray());

        return $result->fresh();
    }

    public function advanceElimination(CompetitionSession $session, CompetitionStage $stage, string $command): void
    {
        abort_unless($session->competition->workflow_key === 'elimination' && $stage->competition_session_id === $session->id, 422);
        abort_if($stage->results()->where('outcome', 'pending')->exists(), 422, 'Semua peserta harus dinilai terlebih dahulu.');
        $passed = $stage->results()->where('outcome', 'passed')->with('participant')->get()->pluck('participant');
        $attempt = $stage->attempt_number;
        $sequence = $stage->sequence;
        $stage->update(['state' => $passed->isEmpty() ? 'void' : 'completed']);

        if ($passed->isEmpty()) {
            $participants = $stage->results()->with('participant')->get()->pluck('participant');
            $this->createStage($session, 'elimination', $sequence, null, 'Putaran '.$sequence.' (ulang)', ['command' => $command], $participants, $attempt + 1);
        } elseif ($passed->count() === 1) {
            $session->update(['state' => 'finished', 'finished_at' => now()]);
            $this->replacePlacements($session, collect([['participant' => $passed->first(), 'summary' => ['method' => 'last_survivor']]]));
        } else {
            $this->createStage($session, 'elimination', $sequence + 1, null, 'Putaran '.($sequence + 1), ['command' => $command], $passed);
            $session->update(['state' => 'ready']);
        }
        $this->audit($session, 'elimination.advanced', $stage, null, ['passed' => $passed->pluck('id')->all()]);
    }

    public function finalize(CompetitionSession $session, ?array $manualPlacements = null, ?string $reason = null): void
    {
        if ($manualPlacements) {
            abort_if(blank($reason), 422, 'Alasan keputusan juri wajib diisi.');
            $ranked = collect($manualPlacements)->map(fn ($id) => ['participant' => $session->participants()->findOrFail($id), 'summary' => ['method' => 'jury_decision', 'reason' => $reason]]);
        } else {
            $ranked = $this->rank($session);
            abort_if($this->hasUnresolvedTie($session, $ranked), 422, 'Hasil masih seri. Jalankan babak ulang/tie-break atau tetapkan keputusan juri beralasan.');
        }
        abort_if($ranked->isEmpty(), 422, 'Belum ada hasil yang dapat difinalisasi.');
        $this->replacePlacements($session, $ranked, $reason);
        $session->update(['state' => 'finished', 'finished_at' => now()]);
        $this->audit($session, 'session.finalized', null, null, ['placements' => $ranked->pluck('participant.id')->all()], $reason);
    }

    public function replay(CompetitionSession $session, array $participantIds, ?string $label = null): CompetitionStage
    {
        $participants = $session->participants()->whereIn('id', $participantIds)->get();
        abort_unless($participants->count() === count(array_unique($participantIds)) && $participants->count() >= 1, 422, 'Peserta babak ulang tidak valid.');
        $workflow = $session->competition->workflow_key;
        $rules = $session->rules_snapshot['workflow'] ?? [];
        $sequence = ((int) $session->stages()->max('sequence')) + 1;
        $config = $rules;
        if ($workflow === 'metric_race' && isset($rules['tie_replay_seconds'])) {
            $config['timer_kind'] = 'countdown';
            $config['duration_seconds'] = $rules['tie_replay_seconds'];
        } elseif ($workflow === 'multi_round_score') {
            $config['timer_kind'] = 'none';
            $config['duration_seconds'] = null;
            $config['tiebreak_only'] = true;
        }
        $stage = $this->createStage($session, $workflow === 'multi_round_score' ? 'tiebreak' : 'replay', $sequence, null, $label ?: ($workflow === 'multi_round_score' ? 'Petunjuk tie-break' : 'Babak ulang'), $config, $participants);
        $session->update(['state' => 'ready']);
        $this->audit($session, 'replay.created', $stage, null, ['participants' => $participantIds]);

        return $stage;
    }

    public function rank(CompetitionSession $session): Collection
    {
        $workflow = $session->competition->workflow_key;
        $results = StageResult::query()->whereHas('stage', fn ($query) => $query->where('competition_session_id', $session->id))->with(['participant', 'stage'])->get();

        if ($workflow === 'judged_rubric') {
            $tie = $session->rules_snapshot['workflow']['tie_order'] ?? [];

            return $results->sort(function ($a, $b) use ($tie) {
                $total = (float) $b->primary_value <=> (float) $a->primary_value;
                if ($total !== 0) {
                    return $total;
                }
                foreach ($tie as $key) {
                    $comparison = (float) ($b->metrics[$key] ?? 0) <=> (float) ($a->metrics[$key] ?? 0);
                    if ($comparison !== 0) {
                        return $comparison;
                    }
                }

                return 0;
            })->values()->map(fn ($result) => ['participant' => $result->participant, 'summary' => ['total' => (float) $result->primary_value, 'rubric' => $result->metrics]]);
        }

        if ($workflow === 'multi_round_score') {
            return $results->groupBy('participant_id')->map(function ($items) {
                return ['participant' => $items->first()->participant, 'summary' => ['correct_answers' => (float) $items->sum('primary_value')]];
            })->sortByDesc('summary.correct_answers')->values();
        }

        if ($workflow === 'metric_race') {
            return $results->filter(fn ($r) => ! in_array($r->outcome, ['pending', 'void'], true))->groupBy('participant_id')->map(function ($items) {
                $best = $items->sort(function ($a, $b) {
                    $order = ['finished' => 0, 'timeout' => 1, 'safety_stop' => 2, 'disqualified' => 3];
                    $status = ($order[$a->outcome] ?? 9) <=> ($order[$b->outcome] ?? 9);
                    if ($status !== 0) {
                        return $status;
                    }
                    $aValue = $a->outcome === 'finished' ? ((float) $a->primary_value + $a->penalty_ms) : (float) $a->secondary_value;
                    $bValue = $b->outcome === 'finished' ? ((float) $b->primary_value + $b->penalty_ms) : (float) $b->secondary_value;

                    return $aValue <=> $bValue;
                })->first();

                return ['participant' => $best->participant, 'summary' => ['outcome' => $best->outcome, 'primary' => (float) $best->primary_value, 'secondary' => (float) $best->secondary_value, 'penalty_ms' => $best->penalty_ms]];
            })->sort(function ($a, $b) {
                $order = ['finished' => 0, 'timeout' => 1, 'safety_stop' => 2, 'disqualified' => 3];
                $status = ($order[$a['summary']['outcome']] ?? 9) <=> ($order[$b['summary']['outcome']] ?? 9);
                if ($status !== 0) {
                    return $status;
                }
                $av = $a['summary']['outcome'] === 'finished' ? $a['summary']['primary'] + $a['summary']['penalty_ms'] : $a['summary']['secondary'];
                $bv = $b['summary']['outcome'] === 'finished' ? $b['summary']['primary'] + $b['summary']['penalty_ms'] : $b['summary']['secondary'];

                return $av <=> $bv;
            })->values();
        }

        return collect();
    }

    public function audit(CompetitionSession $session, string $action, mixed $subject = null, ?array $before = null, ?array $after = null, ?string $reason = null): void
    {
        CompetitionAuditLog::create([
            'competition_session_id' => $session->id,
            'user_id' => auth()->id(),
            'action' => $action,
            'subject_type' => is_object($subject) ? $subject::class : null,
            'subject_id' => is_object($subject) ? $subject->getKey() : null,
            'before' => $before,
            'after' => $after,
            'reason' => $reason,
        ]);
    }

    private function hasUnresolvedTie(CompetitionSession $session, Collection $ranked): bool
    {
        $workflow = $session->competition->workflow_key;
        $keys = $ranked->take(3)->map(function ($item) use ($workflow, $session) {
            $summary = $item['summary'];

            return match ($workflow) {
                'judged_rubric' => json_encode([$summary['total'], ...array_map(fn ($key) => $summary['rubric'][$key] ?? 0, $session->rules_snapshot['workflow']['tie_order'] ?? [])]),
                'multi_round_score' => (string) $summary['correct_answers'],
                'metric_race' => json_encode([$summary['outcome'], $summary['outcome'] === 'finished' ? $summary['primary'] + $summary['penalty_ms'] : $summary['secondary']]),
                default => uniqid('', true),
            };
        });

        return $keys->duplicates()->isNotEmpty();
    }

    private function createStage(CompetitionSession $session, string $phase, int $sequence, ?int $heat, string $label, array $config, Collection $participants, int $attempt = 1): CompetitionStage
    {
        $stage = $session->stages()->create([
            'phase' => $phase, 'sequence' => $sequence, 'heat_number' => $heat, 'attempt_number' => $attempt,
            'label' => $label, 'state' => 'ready', 'timer_kind' => $config['timer_kind'] ?? 'none',
            'duration_seconds' => $config['duration_seconds'] ?? null, 'config' => $config,
        ]);
        foreach ($participants as $participant) {
            $stage->results()->create(['participant_id' => $participant->id]);
        }

        return $stage;
    }

    private function replacePlacements(CompetitionSession $session, Collection $ranked, ?string $reason = null): void
    {
        DB::transaction(function () use ($session, $ranked, $reason) {
            $session->placements()->delete();
            $session->participants()->update(['final_rank' => null]);
            foreach ($ranked->take(3)->values() as $index => $item) {
                $rank = $index + 1;
                SessionPlacement::create(['competition_session_id' => $session->id, 'participant_id' => $item['participant']->id, 'rank' => $rank, 'result_summary' => $item['summary'], 'decision_reason' => $reason, 'decided_by' => auth()->id()]);
                $item['participant']->update(['final_rank' => $rank, 'result_meta' => $item['summary']]);
            }
        });
    }
}
