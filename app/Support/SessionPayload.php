<?php

namespace App\Support;

use App\Models\CompetitionSession;
use Illuminate\Support\Facades\Crypt;

class SessionPayload
{
    public static function make(CompetitionSession $session, bool $admin = false): array
    {
        $session->load(['competition', 'participants', 'teams', 'rounds.winner', 'matches.participantA', 'matches.participantB', 'matches.winner', 'stages.results.participant', 'incidents.participant', 'placements.participant', 'placements.team']);
        $round = $session->rounds->first();
        $settings = $session->settings ?? [];
        $tiebreakWins = $session->rounds
            ->where('phase', 'tiebreak')
            ->where('state', 'closed')
            ->pluck('winner_team_id')
            ->filter()
            ->countBy();

        $payload = [
            'id' => $session->id,
            'code' => $session->code,
            'state' => $session->state,
            'server_now' => now()->toIso8601String(),
            'timer_started_at' => $session->timer_started_at?->toIso8601String(),
            'timer_ends_at' => $session->timer_ends_at?->toIso8601String(),
            'paused_remaining_ms' => $session->paused_remaining_ms,
            'current_participant_id' => $session->current_participant_id,
            'question_number' => $session->question_number,
            'checklist_confirmed' => (bool) $session->checklist_confirmed_at,
            'rules' => $session->rules_snapshot ?: $session->competition->rules,
            'competition' => [
                'id' => $session->competition->id,
                'name' => $session->competition->name,
                'description' => $session->competition->description,
                'video_url' => $session->competition->video_url,
                'type' => $session->competitionType(),
                'workflow_key' => $session->competition->workflow_key,
                'entry_kind' => $session->competition->entry_kind,
                'schedule_text' => $session->competition->schedule_text,
                'venue' => $session->competition->venue,
                'person_in_charge' => $session->competition->person_in_charge,
                'is_official' => $session->competition->is_official,
                'quiz_format' => $settings['quiz_format'] ?? $session->competition->quiz_format,
                'general_format' => $settings['general_format'] ?? $session->competition->general_format,
                'duration_seconds' => $settings['duration_seconds'] ?? $session->competition->duration_seconds,
                'timer_mode' => $settings['timer_mode'] ?? $session->competition->timer_mode,
                'result_type' => $settings['result_type'] ?? $session->competition->result_type,
                'result_label' => $settings['result_label'] ?? $session->competition->result_label,
                'result_unit' => $settings['result_unit'] ?? $session->competition->result_unit,
                'ranking_direction' => $settings['ranking_direction'] ?? $session->competition->ranking_direction,
                'correct_points' => $settings['correct_points'] ?? $session->competition->correct_points,
                'wrong_points' => $settings['wrong_points'] ?? $session->competition->wrong_points,
            ],
            'participants' => $session->participants->map(fn ($participant) => [
                'id' => $participant->id,
                'name' => $participant->name,
                'affiliation' => $participant->affiliation,
                'members' => $participant->members ?? [],
                'position' => $participant->position,
                'status' => $participant->status,
                'result_value' => $participant->result_value,
                'final_rank' => $participant->final_rank,
                'result_meta' => $participant->result_meta,
                'notes' => $participant->notes,
            ])->values(),
            'teams' => $session->teams->map(fn ($team) => [
                'id' => $team->id,
                'name' => $team->name,
                'affiliation' => $team->affiliation,
                'members' => $team->members ?? [],
                'color' => $team->color,
                'pitch_hz' => $team->pitch_hz,
                'approved' => $team->approved,
                'score' => $team->score,
                'tiebreak_wins' => $tiebreakWins->get($team->id, 0),
                'final_rank' => $team->final_rank,
                'online' => $team->last_seen_at?->gt(now()->subSeconds(20)) ?? false,
            ])->values(),
            'round' => $round ? [
                'id' => $round->id,
                'number' => $round->number,
                'phase' => $round->phase,
                'answer_attempt' => $round->answer_attempt,
                'max_attempts' => $round->max_attempts,
                'state' => $round->state === 'ready' && $round->opens_at?->isPast() ? 'open' : $round->state,
                'opens_at' => $round->opens_at?->toIso8601String(),
                'locked_at' => $round->locked_at?->toIso8601String(),
                'winner_team_id' => $round->winner_team_id,
                'winner' => $round->winner ? [
                    'id' => $round->winner->id,
                    'name' => $round->winner->name,
                    'color' => $round->winner->color,
                    'pitch_hz' => $round->winner->pitch_hz,
                ] : null,
                'eliminated_team_ids' => $round->eliminated_team_ids ?? [],
                'eligible_team_ids' => $round->eligible_team_ids ?? [],
            ] : null,
            'matches' => $session->matches->map(fn ($match) => [
                'id' => $match->id,
                'round_number' => $match->round_number,
                'match_number' => $match->match_number,
                'state' => $match->state,
                'bracket_type' => $match->bracket_type,
                'score_a' => $match->score_a,
                'score_b' => $match->score_b,
                'team_a' => $match->participantA ? ['id' => $match->participantA->id, 'name' => $match->participantA->name, 'color' => self::participantColor($match->participantA->position)] : null,
                'team_b' => $match->participantB ? ['id' => $match->participantB->id, 'name' => $match->participantB->name, 'color' => self::participantColor($match->participantB->position)] : null,
                'winner_team_id' => $match->winner_participant_id,
                'winner' => $match->winner ? ['id' => $match->winner->id, 'name' => $match->winner->name, 'color' => self::participantColor($match->winner->position)] : null,
            ])->values(),
            'active_match' => ($activeMatch = $session->matches->firstWhere('state', 'active')) ? [
                'id' => $activeMatch->id,
                'round_number' => $activeMatch->round_number,
                'match_number' => $activeMatch->match_number,
                'team_a' => $activeMatch->participantA ? ['id' => $activeMatch->participantA->id, 'name' => $activeMatch->participantA->name, 'color' => self::participantColor($activeMatch->participantA->position)] : null,
                'team_b' => $activeMatch->participantB ? ['id' => $activeMatch->participantB->id, 'name' => $activeMatch->participantB->name, 'color' => self::participantColor($activeMatch->participantB->position)] : null,
            ] : null,
            'stages' => $session->stages->map(fn ($stage) => [
                'id' => $stage->id,
                'phase' => $stage->phase,
                'sequence' => $stage->sequence,
                'heat_number' => $stage->heat_number,
                'attempt_number' => $stage->attempt_number,
                'label' => self::stageLabel($stage->label),
                'state' => $stage->state,
                'timer_kind' => $stage->timer_kind,
                'duration_seconds' => $stage->duration_seconds,
                'started_at' => $stage->started_at?->toIso8601String(),
                'ends_at' => $stage->ends_at?->toIso8601String(),
                'paused_remaining_ms' => $stage->paused_remaining_ms,
                'config' => $stage->config,
                'results' => $stage->results->map(fn ($result) => [
                    'id' => $result->id,
                    'participant_id' => $result->participant_id,
                    'participant' => $result->participant ? ['id' => $result->participant->id, 'name' => $result->participant->name] : null,
                    'outcome' => $result->outcome,
                    'primary_value' => $result->primary_value,
                    'secondary_value' => $result->secondary_value,
                    'penalty_ms' => $result->penalty_ms,
                    'metrics' => $result->metrics,
                    'notes' => $result->notes,
                ])->values(),
            ])->values(),
            'active_stage' => ($activeStage = $session->stages->first(fn ($stage) => in_array($stage->state, ['running', 'paused'], true))) ? [
                'id' => $activeStage->id, 'label' => self::stageLabel($activeStage->label), 'state' => $activeStage->state,
                'timer_kind' => $activeStage->timer_kind, 'started_at' => $activeStage->started_at?->toIso8601String(), 'ends_at' => $activeStage->ends_at?->toIso8601String(),
            ] : null,
            'incidents' => $session->incidents->map(fn ($incident) => [
                'id' => $incident->id, 'participant_id' => $incident->participant_id, 'participant' => $incident->participant?->name,
                'kind' => $incident->kind, 'value' => $incident->value, 'reason' => $incident->reason, 'created_at' => $incident->created_at->toIso8601String(),
            ])->values(),
            'placements' => $session->placements->map(fn ($placement) => [
                'rank' => $placement->rank,
                'entry' => $placement->participant ? [
                    'id' => $placement->participant->id,
                    'name' => $placement->participant->name,
                    'affiliation' => $placement->participant->affiliation,
                    'members' => $placement->participant->members ?? [],
                ] : ($placement->team ? [
                    'id' => $placement->team->id,
                    'name' => $placement->team->name,
                    'affiliation' => $placement->team->affiliation,
                    'members' => $placement->team->members ?? [],
                ] : null),
                'result_summary' => $placement->result_summary,
                'decision_reason' => $placement->decision_reason,
            ])->values(),
        ];

        if ($admin && isset($settings['pin_encrypted'])) {
            $payload['pin'] = Crypt::decryptString($settings['pin_encrypted']);
            $payload['transactions'] = $session->scores()->with('team')->limit(12)->get()->map(fn ($transaction) => [
                'id' => $transaction->id,
                'team' => $transaction->team?->name,
                'kind' => $transaction->kind,
                'delta' => $transaction->delta,
                'reason' => $transaction->reason,
                'reverses_id' => $transaction->reverses_id,
                'created_at' => $transaction->created_at->toIso8601String(),
                'reversed' => $session->scores()->where('reverses_id', $transaction->id)->exists(),
            ])->values();
            $payload['audits'] = $session->audits()->latest()->limit(30)->get()->map(fn ($audit) => [
                'id' => $audit->id, 'action' => $audit->action, 'reason' => $audit->reason, 'created_at' => $audit->created_at->toIso8601String(),
            ])->values();
        }

        return $payload;
    }

    private static function participantColor(int $position): string
    {
        $colors = ['#dc2626', '#2563eb', '#16a34a', '#ea580c', '#9333ea', '#0891b2', '#ca8a04', '#4f46e5'];

        return $colors[max(0, $position - 1) % count($colors)];
    }

    private static function stageLabel(string $label): string
    {
        return preg_replace('/^Heat\b/i', 'Babak', $label) ?: $label;
    }
}
