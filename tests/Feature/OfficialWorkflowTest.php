<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\CompetitionSession;
use App\Models\CompetitionStage;
use App\Models\User;
use App\Support\SessionPayload;
use Database\Seeders\LombaAgustusanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfficialWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create();
        $this->seed(LombaAgustusanSeeder::class);
    }

    public function test_tumpeng_rubric_is_validated_and_ranked_by_official_tiebreak(): void
    {
        $session = $this->createCompetitionSession('lomba-tumpeng-antar-tim');
        $this->entries($session, [['Merah', ['A']], ['Biru', ['B']]]);
        $this->confirmAndPrepare($session);
        $stage = $session->stages()->firstOrFail();

        $this->patch(route('workflow.stage.result', [$session, $stage]), ['participant_id' => $session->participants[0]->id, 'outcome' => 'finished', 'metrics' => ['creativity' => 30, 'presentation' => 20, 'taste' => 20, 'hygiene' => 20]])->assertRedirect();
        $this->patch(route('workflow.stage.result', [$session, $stage]), ['participant_id' => $session->participants[1]->id, 'outcome' => 'finished', 'metrics' => ['creativity' => 25, 'presentation' => 25, 'taste' => 20, 'hygiene' => 20]])->assertRedirect();
        $this->post(route('workflow.finalize', $session))->assertRedirect();

        $this->assertDatabaseHas('session_placements', ['competition_session_id' => $session->id, 'participant_id' => $session->participants[0]->id, 'rank' => 1]);
        $payload = SessionPayload::make($session->fresh());
        $this->assertSame('Merah', $payload['placements'][0]['entry']['affiliation']);
        $this->assertSame(['A'], $payload['placements'][0]['entry']['members']);
    }

    public function test_metric_heat_requires_unit_and_applies_time_penalty(): void
    {
        $session = $this->createCompetitionSession('mancing-emosi-makan-kerupuk');
        $this->entries($session, [['Pasangan A', ['A1', 'A2']], ['Pasangan B', ['B1', 'B2']]]);
        $this->actingAs($this->admin)->post(route('workflow.checklist', $session), ['confirmed' => true])->assertRedirect();
        $this->post(route('workflow.prepare', $session), ['heat_capacity' => 2])->assertStatus(422);
        $this->post(route('workflow.prepare', $session), ['heat_capacity' => 2, 'remaining_unit' => 'nilai sisa'])->assertRedirect();
        $stage = $session->stages()->firstOrFail();
        $this->assertSame('Babak 1', $stage->label);
        $this->assertSame('nilai sisa', $stage->config['remaining_unit']);
        $first = $session->participants[0];
        $second = $session->participants[1];
        $this->patch(route('workflow.stage.result', [$session, $stage]), ['participant_id' => $first->id, 'outcome' => 'finished', 'primary_value' => 50000])->assertRedirect();
        $this->patch(route('workflow.stage.result', [$session, $stage]), ['participant_id' => $second->id, 'outcome' => 'finished', 'primary_value' => 55000])->assertRedirect();
        $this->post(route('workflow.incident', $session), ['participant_id' => $first->id, 'competition_stage_id' => $stage->id, 'rule_code' => 'first_violation', 'kind' => 'time_penalty', 'value' => 10, 'reason' => 'Menyentuh tali'])->assertRedirect();
        $this->post(route('workflow.finalize', $session))->assertRedirect();
        $this->assertDatabaseHas('session_placements', ['participant_id' => $second->id, 'rank' => 1]);
    }

    public function test_participant_can_be_added_after_a_stage_has_been_prepared(): void
    {
        $session = $this->createCompetitionSession('mancing-emosi-makan-kerupuk');
        $this->entries($session, [['Pasangan Awal', ['A1', 'A2']]]);
        $this->actingAs($this->admin)->post(route('workflow.checklist', $session), ['confirmed' => true])->assertRedirect();
        $this->post(route('workflow.prepare', $session), ['heat_capacity' => 8, 'remaining_unit' => 'gram'])->assertRedirect();
        $stage = $session->stages()->firstOrFail();
        $stage->update(['state' => 'running']);
        $session->update(['state' => 'running']);

        $this->post(route('sessions.participants', $session), ['entries' => [[
            'name' => 'Pasangan Tambahan', 'affiliation' => 'IKP', 'members' => ['Baru 1', 'Baru 2'],
        ]]])->assertRedirect();

        $participant = $session->participants()->where('name', 'Pasangan Tambahan')->firstOrFail();
        $this->assertDatabaseHas('stage_results', ['competition_stage_id' => $stage->id, 'participant_id' => $participant->id, 'outcome' => 'pending']);
    }

    public function test_participant_added_after_finish_reopens_session_in_a_new_stage(): void
    {
        $session = $this->createCompetitionSession('mancing-emosi-makan-kerupuk');
        $this->entries($session, [['Pasangan Awal', ['A1', 'A2']]]);
        $this->actingAs($this->admin)->post(route('workflow.checklist', $session), ['confirmed' => true])->assertRedirect();
        $this->post(route('workflow.prepare', $session), ['heat_capacity' => 8, 'remaining_unit' => 'gram'])->assertRedirect();
        $session->stages()->update(['state' => 'completed']);
        $session->update(['state' => 'finished', 'finished_at' => now()]);

        $this->post(route('sessions.participants', $session), ['entries' => [[
            'name' => 'Peserta Susulan', 'affiliation' => 'TIK', 'members' => ['Susulan 1', 'Susulan 2'],
        ]]])->assertRedirect();

        $this->assertSame('ready', $session->fresh()->state);
        $lateStage = $session->stages()->where('phase', 'late_entry')->firstOrFail();
        $this->assertSame('Babak peserta tambahan', $lateStage->label);
        $this->assertSame('Peserta Susulan', $lateStage->results()->with('participant')->firstOrFail()->participant->name);
    }

    public function test_admin_can_edit_and_delete_an_entry_after_stages_are_prepared(): void
    {
        $session = $this->createCompetitionSession('mancing-emosi-makan-kerupuk');
        $this->entries($session, [['Pasangan Awal', ['A1', 'A2']], ['Pasangan Kedua', ['B1', 'B2']]]);
        $this->actingAs($this->admin)->post(route('workflow.checklist', $session), ['confirmed' => true])->assertRedirect();
        $this->post(route('workflow.prepare', $session), ['heat_capacity' => 8, 'remaining_unit' => 'gram'])->assertRedirect();
        $participant = $session->participants()->where('name', 'Pasangan Awal')->firstOrFail();
        $stage = $session->stages()->firstOrFail();

        $this->patch(route('sessions.participants.update', [$session, $participant]), [
            'name' => 'Pasangan Revisi',
            'affiliation' => 'TIK',
            'members' => ['Ridwan', 'Danang'],
        ])->assertRedirect();

        $participant->refresh();
        $this->assertSame('Pasangan Revisi', $participant->name);
        $this->assertSame('TIK', $participant->affiliation);
        $this->assertSame(['Ridwan', 'Danang'], $participant->members);
        $this->assertDatabaseHas('stage_results', ['competition_stage_id' => $stage->id, 'participant_id' => $participant->id]);

        $session->update(['state' => 'running']);
        $this->delete(route('sessions.participants.destroy', [$session, $participant]))->assertRedirect();

        $this->assertDatabaseMissing('participants', ['id' => $participant->id]);
        $this->assertDatabaseMissing('stage_results', ['competition_stage_id' => $stage->id, 'participant_id' => $participant->id]);
        $this->assertSame('running', $session->fresh()->state);
    }

    public function test_elimination_repeats_when_everyone_fails(): void
    {
        $session = $this->createCompetitionSession('lomba-pindah-warna');
        $this->entries($session, [['Ani', ['Ani']], ['Budi', ['Budi']]]);
        $this->confirmAndPrepare($session, ['command' => 'step_on_color']);
        $stage = $session->stages()->firstOrFail();
        foreach ($session->participants as $participant) {
            $this->patch(route('workflow.stage.result', [$session, $stage]), ['participant_id' => $participant->id, 'outcome' => 'failed'])->assertRedirect();
        }
        $this->post(route('workflow.elimination.advance', [$session, $stage]), ['command' => 'avoid_color'])->assertRedirect();
        $this->assertSame('void', $stage->fresh()->state);
        $this->assertSame(2, $session->stages()->count());
        $this->assertSame(2, CompetitionStage::where('competition_session_id', $session->id)->latest('id')->first()->attempt_number);
    }

    public function test_voli_match_finishes_exactly_on_seven_points(): void
    {
        $session = $this->createCompetitionSession('lomba-voli-air');
        $members = fn ($prefix) => array_map(fn ($i) => $prefix.$i, range(1, 6));
        $this->entries($session, [['Merah', $members('M')], ['Biru', $members('B')]]);
        $this->actingAs($this->admin)->post(route('workflow.checklist', $session), ['confirmed' => true])->assertRedirect();
        $this->post(route('general.bracket.generate', $session), ['play_third_place' => false])->assertRedirect();
        $match = $session->matches()->where('state', 'active')->firstOrFail();
        foreach (range(1, 7) as $point) {
            $this->post(route('general.matches.score', [$session, $match]), ['participant_id' => $match->participant_a_id])->assertRedirect();
        }
        $this->assertSame(7, $match->fresh()->score_a);
        $this->assertSame('completed', $match->fresh()->state);
        $this->assertSame('finished', $session->fresh()->state);
    }

    public function test_official_rules_are_locked_except_for_admin_countdown_and_can_be_cloned(): void
    {
        $competition = Competition::where('slug', 'lomba-joget-kardus')->firstOrFail();
        $originalRules = $competition->rules;
        $this->actingAs($this->admin)->put(route('competitions.update', $competition), [
            ...$competition->only(['name', 'description', 'schedule_text', 'venue', 'person_in_charge', 'video_url', 'type', 'workflow_key', 'entry_kind', 'quiz_format', 'general_format', 'duration_seconds', 'timer_mode', 'result_type', 'result_label', 'result_unit', 'ranking_direction', 'correct_points', 'wrong_points']),
            'duration_seconds' => 999,
            'rules_json' => json_encode(['workflow' => ['duration_seconds' => 999]]),
        ])->assertRedirect();
        $competition->refresh();
        $this->assertSame($originalRules['equipment'], $competition->rules['equipment']);
        $this->assertSame(999, $competition->rules['workflow']['duration_seconds']);
        $this->assertSame(999, $competition->duration_seconds);

        $this->post(route('competitions.clone', $competition))->assertRedirect();
        $copy = Competition::where('slug', 'lomba-joget-kardus-kustom')->firstOrFail();
        $this->assertFalse($copy->is_official);
        $this->assertSame($competition->rules, $copy->rules);
    }

    public function test_admin_can_change_countdown_for_a_prepared_and_running_stage(): void
    {
        $session = $this->createCompetitionSession('lomba-joget-kardus');
        $this->entries($session, [['Peserta A', ['Peserta A']]]);
        $this->confirmAndPrepare($session);
        $stage = $session->stages()->firstOrFail();
        $startedAt = now()->subSeconds(5);
        $stage->update(['state' => 'running', 'started_at' => $startedAt, 'ends_at' => $startedAt->copy()->addSeconds(90)]);
        $session->update([
            'state' => 'running',
            'timer_started_at' => $startedAt,
            'timer_ends_at' => $startedAt->copy()->addSeconds(90),
        ]);

        $this->patch(route('sessions.duration', $session), ['duration_seconds' => 45])->assertRedirect();

        $session->refresh();
        $stage->refresh();
        $this->assertSame(45, $session->settings['duration_seconds']);
        $this->assertSame(45, $session->rules_snapshot['workflow']['duration_seconds']);
        $this->assertSame($startedAt->copy()->addSeconds(45)->timestamp, $session->timer_ends_at->timestamp);
        $this->assertSame(45, $stage->duration_seconds);
        $this->assertSame($startedAt->copy()->addSeconds(45)->timestamp, $stage->ends_at->timestamp);
    }

    public function test_susun_kata_tie_requires_tiebreak_or_recorded_jury_decision(): void
    {
        $session = $this->createCompetitionSession('lomba-susun-kata');
        $members = fn ($prefix) => array_map(fn ($i) => $prefix.$i, range(1, 6));
        $this->entries($session, [['Merah', $members('M')], ['Biru', $members('B')]]);
        $this->confirmAndPrepare($session);
        foreach ($session->stages as $stage) {
            foreach ($session->participants as $participant) {
                $this->patch(route('workflow.stage.result', [$session, $stage]), ['participant_id' => $participant->id, 'outcome' => 'finished', 'metrics' => ['correct_answers' => 2]])->assertRedirect();
            }
        }
        $this->post(route('workflow.finalize', $session))->assertStatus(422);
        $ids = $session->participants->pluck('id')->all();
        $this->post(route('workflow.replay', $session), ['participant_ids' => $ids])->assertRedirect();
        $this->assertDatabaseHas('competition_stages', ['competition_session_id' => $session->id, 'phase' => 'tiebreak', 'timer_kind' => 'none']);
        $this->post(route('workflow.finalize', $session), ['placements' => $ids, 'reason' => 'Tim Merah menjawab petunjuk tambahan lebih dahulu.'])->assertRedirect();
        $this->assertDatabaseHas('session_placements', ['participant_id' => $ids[0], 'rank' => 1, 'decision_reason' => 'Tim Merah menjawab petunjuk tambahan lebih dahulu.']);
    }

    private function createCompetitionSession(string $slug): CompetitionSession
    {
        $competition = Competition::where('slug', $slug)->firstOrFail();
        $this->actingAs($this->admin)->post(route('sessions.store', $competition))->assertRedirect();

        return $competition->sessions()->latest()->firstOrFail();
    }

    private function entries(CompetitionSession $session, array $entries): void
    {
        $payload = collect($entries)->map(fn ($entry) => ['name' => $entry[0], 'affiliation' => $entry[0], 'members' => $entry[1]])->all();
        $this->actingAs($this->admin)->post(route('sessions.participants', $session), ['entries' => $payload])->assertRedirect();
        $session->refresh()->load('participants');
    }

    private function confirmAndPrepare(CompetitionSession $session, array $options = []): void
    {
        $this->actingAs($this->admin)->post(route('workflow.checklist', $session), ['confirmed' => true])->assertRedirect();
        $this->post(route('workflow.prepare', $session), $options)->assertRedirect();
        $session->refresh()->load(['participants', 'stages']);
    }
}
