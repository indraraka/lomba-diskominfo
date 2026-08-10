<?php

namespace App\Http\Controllers;

use App\Events\SessionStateUpdated;
use App\Models\CompetitionSession;
use App\Models\CompetitionStage;
use App\Models\Participant;
use App\Support\SessionPayload;
use App\Support\WorkflowService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CompetitionWorkflowController extends Controller
{
    public function __construct(private WorkflowService $workflows) {}

    public function checklist(Request $request, CompetitionSession $session)
    {
        $request->validate(['confirmed' => ['accepted']]);
        $session->update(['checklist_confirmed_at' => now(), 'checklist_confirmed_by' => $request->user()->id]);
        $this->workflows->audit($session, 'checklist.confirmed');

        return $this->done($session);
    }

    public function prepare(Request $request, CompetitionSession $session)
    {
        $data = $request->validate(['heat_capacity' => ['nullable', 'integer', 'min:1', 'max:50'], 'remaining_unit' => ['nullable', 'string', 'max:30'], 'command' => ['nullable', Rule::in(['step_on_color', 'avoid_color'])], 'play_third_place' => ['nullable', 'boolean']]);
        $rules = $session->rules_snapshot ?: [];
        if (($rules['workflow']['remaining_unit_required'] ?? false) && blank($data['remaining_unit'] ?? null)) {
            abort(422, 'Satuan penilaian sisa wajib diisi.');
        }
        $session->update(['settings' => [...($session->settings ?? []), 'workflow_options' => $data]]);
        $this->workflows->prepare($session, $data);

        return $this->done($session);
    }

    public function timer(Request $request, CompetitionSession $session, CompetitionStage $stage)
    {
        abort_unless($stage->competition_session_id === $session->id, 404);
        $action = $request->validate(['action' => ['required', Rule::in(['start', 'pause', 'resume', 'reset', 'complete'])]])['action'];
        $before = $stage->toArray();
        if ($action === 'start') {
            $starts = now()->addSeconds(3);
            $stage->update(['state' => 'running', 'started_at' => $starts, 'ends_at' => $stage->timer_kind === 'countdown' ? $starts->copy()->addSeconds($stage->duration_seconds) : null, 'paused_remaining_ms' => null]);
            $session->update(['state' => 'running']);
        } elseif ($action === 'pause') {
            abort_unless($stage->state === 'running', 422);
            $remaining = $stage->ends_at ? max(0, now()->diffInMilliseconds($stage->ends_at, false)) : max(0, now()->diffInMilliseconds($stage->started_at));
            $stage->update(['state' => 'paused', 'paused_remaining_ms' => $remaining, 'ends_at' => null]);
            $session->update(['state' => 'paused']);
        } elseif ($action === 'resume') {
            abort_unless($stage->state === 'paused', 422);
            $stage->update(['state' => 'running', 'started_at' => now(), 'ends_at' => $stage->timer_kind === 'countdown' ? now()->addMilliseconds($stage->paused_remaining_ms) : null, 'paused_remaining_ms' => null]);
            $session->update(['state' => 'running']);
        } elseif ($action === 'reset') {
            $stage->update(['state' => 'ready', 'started_at' => null, 'ends_at' => null, 'paused_remaining_ms' => null]);
            $session->update(['state' => 'ready']);
        } else {
            $stage->update(['state' => 'completed', 'ends_at' => null]);
            $session->update(['state' => 'ready']);
        }
        $this->workflows->audit($session, 'stage.timer.'.$action, $stage, $before, $stage->fresh()->toArray());

        return $this->done($session);
    }

    public function result(Request $request, CompetitionSession $session, CompetitionStage $stage)
    {
        $data = $request->validate([
            'participant_id' => ['required', 'integer'], 'outcome' => ['required', Rule::in(['passed', 'failed', 'finished', 'timeout', 'eliminated', 'disqualified', 'safety_stop', 'void'])],
            'primary_value' => ['nullable', 'numeric', 'min:0'], 'secondary_value' => ['nullable', 'numeric', 'min:0'], 'metrics' => ['nullable', 'array'], 'notes' => ['nullable', 'string', 'max:500'],
        ]);
        $this->workflows->saveResult($session, $stage, $data);

        return $this->done($session);
    }

    public function incident(Request $request, CompetitionSession $session)
    {
        $data = $request->validate([
            'participant_id' => ['required', 'integer'], 'competition_stage_id' => ['nullable', 'integer'],
            'rule_code' => ['required', 'string', 'max:80'], 'kind' => ['required', Rule::in(['warning', 'time_penalty', 'point_penalty', 'disqualification', 'equipment_failure', 'safety_stop', 'jury_decision'])],
            'value' => ['nullable', 'integer'], 'reason' => ['required', 'string', 'max:500'],
        ]);
        abort_unless($session->participants()->whereKey($data['participant_id'])->exists(), 422);
        $incident = $session->incidents()->create([...$data, 'recorded_by' => $request->user()->id]);
        if ($data['kind'] === 'time_penalty' && $data['competition_stage_id']) {
            $stage = $session->stages()->findOrFail($data['competition_stage_id']);
            $stage->results()->where('participant_id', $data['participant_id'])->increment('penalty_ms', ($data['value'] ?? 0) * 1000);
        }
        if ($data['kind'] === 'disqualification') {
            Participant::whereKey($data['participant_id'])->update(['status' => 'disqualified']);
        }
        $this->workflows->audit($session, 'incident.created', $incident, null, $incident->toArray(), $data['reason']);

        return $this->done($session);
    }

    public function advanceElimination(Request $request, CompetitionSession $session, CompetitionStage $stage)
    {
        $data = $request->validate(['command' => ['required', Rule::in(['step_on_color', 'avoid_color'])]]);
        $this->workflows->advanceElimination($session, $stage, $data['command']);

        return $this->done($session);
    }

    public function finalize(Request $request, CompetitionSession $session)
    {
        $data = $request->validate(['placements' => ['nullable', 'array', 'min:1', 'max:3'], 'placements.*' => ['integer', 'distinct'], 'reason' => ['nullable', 'string', 'max:500']]);
        $this->workflows->finalize($session, $data['placements'] ?? null, $data['reason'] ?? null);

        return $this->done($session);
    }

    public function replay(Request $request, CompetitionSession $session)
    {
        $data = $request->validate(['participant_ids' => ['required', 'array', 'min:1'], 'participant_ids.*' => ['integer'], 'label' => ['nullable', 'string', 'max:100']]);
        $this->workflows->replay($session, $data['participant_ids'], $data['label'] ?? null);

        return $this->done($session);
    }

    private function done(CompetitionSession $session)
    {
        SessionStateUpdated::dispatch($session->code, SessionPayload::make($session));

        return back();
    }
}
