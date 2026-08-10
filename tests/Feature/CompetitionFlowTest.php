<?php

namespace Tests\Feature;

use App\Events\SessionStateUpdated;
use App\Models\Competition;
use App\Models\CompetitionSession;
use App\Models\ScoreTransaction;
use App\Models\User;
use App\Support\SessionPayload;
use Database\Seeders\ProductionBootstrapSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class CompetitionFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake([SessionStateUpdated::class]);
    }

    public function test_production_bootstrap_runs_once_and_never_resets_existing_rosters(): void
    {
        $this->seed(ProductionBootstrapSeeder::class);

        $session = CompetitionSession::where('code', 'BDG001')->firstOrFail();
        $teamCount = $session->teams()->count();
        $session->update(['state' => 'running', 'question_number' => 1]);

        try {
            $this->seed(ProductionBootstrapSeeder::class);
            $this->fail('Bootstrap kedua seharusnya ditolak.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('database aplikasi tidak kosong', $exception->getMessage());
        }

        $this->assertSame('running', $session->fresh()->state);
        $this->assertSame(1, $session->fresh()->question_number);
        $this->assertSame($teamCount, $session->teams()->count());
    }

    public function test_admin_can_create_a_general_competition_and_start_timer(): void
    {
        $admin = User::factory()->create();
        $this->actingAs($admin)->post('/competitions', [
            'name' => 'Balap Karung', 'description' => 'Babak final', 'video_url' => null,
            'type' => 'general', 'general_format' => 'standard', 'duration_seconds' => 60, 'timer_mode' => 'individual',
            'result_type' => 'time', 'result_label' => 'Waktu', 'result_unit' => 'detik',
            'ranking_direction' => 'asc', 'correct_points' => 10, 'wrong_points' => -5,
        ])->assertRedirect('/competitions');

        $competition = Competition::firstOrFail();
        $this->actingAs($admin)->post(route('sessions.store', $competition))->assertRedirect();
        $session = $competition->sessions()->firstOrFail();
        $this->post(route('sessions.participants', $session), ['names' => ['Ani', 'Budi']])->assertRedirect();
        $participant = $session->participants()->first();
        $this->post(route('sessions.timer', $session), ['action' => 'start', 'participant_id' => $participant->id])->assertRedirect();

        $session->refresh();
        $this->assertSame('running', $session->state);
        $this->assertSame($participant->id, $session->current_participant_id);
        $this->assertTrue($session->timer_ends_at->gt($session->timer_started_at));
    }

    public function test_quiz_accepts_only_one_winner_and_reopens_after_wrong_answer(): void
    {
        $admin = User::factory()->create();
        $competition = Competition::create([
            'name' => 'Cerdas Cermat', 'slug' => 'cerdas-cermat', 'type' => 'quiz',
            'ranking_direction' => 'desc', 'correct_points' => 10, 'wrong_points' => -5,
        ]);
        $pin = '123456';
        $session = CompetitionSession::create([
            'competition_id' => $competition->id, 'code' => 'ABC123', 'pin_hash' => Hash::make($pin),
            'settings' => ['pin_encrypted' => Crypt::encryptString($pin), 'competition_type' => 'quiz', 'correct_points' => 10, 'wrong_points' => -5],
        ]);

        $first = $this->postJson('/r/ABC123/join', ['name' => 'Merah', 'members' => ['M1', 'M2', 'M3'], 'pin' => $pin])->assertOk()->json();
        $second = $this->postJson('/r/ABC123/join', ['name' => 'Biru', 'members' => ['B1', 'B2', 'B3'], 'pin' => $pin])->assertOk()->json();
        $this->actingAs($admin)->post(route('quiz.approve', [$session, $first['team_id']]))->assertRedirect();
        $this->post(route('quiz.approve', [$session, $second['team_id']]))->assertRedirect();
        foreach (range(1, 10) as $question) {
            $this->post(route('quiz.mandatory', $session), ['answers' => [$first['team_id'] => false, $second['team_id'] => false]])->assertRedirect();
        }
        $this->post(route('quiz.rounds', $session))->assertRedirect();
        $this->post(route('quiz.open', $session))->assertRedirect();
        $this->travel(4)->seconds();

        $this->withToken($first['token'])->postJson('/r/ABC123/buzz', ['idempotency_key' => (string) Str::uuid()])->assertOk()->assertJson(['accepted' => true]);
        $this->withToken($second['token'])->postJson('/r/ABC123/buzz', ['idempotency_key' => (string) Str::uuid()])->assertOk()->assertJson(['accepted' => false, 'reason' => 'locked']);
        $this->post(route('quiz.score', $session), ['action' => 'wrong'])->assertRedirect();
        $this->post(route('quiz.score', $session), ['action' => 'wrong'])->assertStatus(422);
        $this->assertDatabaseHas('quiz_teams', ['id' => $first['team_id'], 'score' => -5]);
        $this->travel(2)->seconds();
        $this->withToken($second['token'])->postJson('/r/ABC123/buzz', ['idempotency_key' => (string) Str::uuid()])->assertOk()->assertJson(['accepted' => true]);
        $this->post(route('quiz.score', $session), ['action' => 'wrong'])->assertRedirect();

        $wrong = ScoreTransaction::where('kind', 'wrong')->firstOrFail();
        $this->post(route('quiz.score.reverse', [$session, $wrong]))->assertRedirect();

        $this->assertDatabaseHas('quiz_teams', ['id' => $first['team_id'], 'score' => 0]);
        $this->assertDatabaseHas('score_transactions', ['kind' => 'reversal', 'delta' => 5, 'reverses_id' => $wrong->id]);
        $this->assertDatabaseHas('buzzer_rounds', ['competition_session_id' => $session->id, 'state' => 'closed', 'answer_attempt' => 2, 'winner_team_id' => null]);
    }

    public function test_admin_can_add_and_remove_quiz_teams_without_scanning(): void
    {
        $admin = User::factory()->create();
        $competition = Competition::create([
            'name' => 'Cerdas Cermat', 'slug' => 'cerdas-cermat-manual', 'type' => 'quiz',
            'ranking_direction' => 'desc', 'correct_points' => 10, 'wrong_points' => -5,
        ]);
        $session = CompetitionSession::create([
            'competition_id' => $competition->id, 'code' => 'MANUAL', 'pin_hash' => Hash::make('123456'),
            'settings' => ['competition_type' => 'quiz'],
        ]);

        $this->actingAs($admin)->post(route('quiz.teams', $session), [
            'names' => ['Merah', 'Biru'],
        ])->assertRedirect();

        $this->assertDatabaseHas('quiz_teams', ['competition_session_id' => $session->id, 'name' => 'Merah', 'approved' => true]);
        $this->assertDatabaseHas('quiz_teams', ['competition_session_id' => $session->id, 'name' => 'Biru', 'approved' => true]);

        $team = $session->teams()->where('name', 'Merah')->firstOrFail();
        $this->delete(route('quiz.teams.destroy', [$session, $team]))->assertRedirect();
        $this->assertDatabaseMissing('quiz_teams', ['id' => $team->id]);
    }

    public function test_seeded_quiz_team_can_claim_a_buzzer_device_with_the_room_pin(): void
    {
        $competition = Competition::create([
            'name' => 'Cerdas Cermat', 'slug' => 'cerdas-cermat-claim', 'type' => 'quiz',
            'ranking_direction' => 'desc', 'correct_points' => 10, 'wrong_points' => -5,
        ]);
        $session = CompetitionSession::create([
            'competition_id' => $competition->id, 'code' => 'CLAIM1', 'pin_hash' => Hash::make('123456'),
            'settings' => ['competition_type' => 'quiz'],
        ]);
        $team = $session->teams()->create([
            'name' => 'TIK', 'affiliation' => 'TIK', 'members' => ['A', 'B', 'C'],
            'color' => '#2563eb', 'pitch_hz' => 440, 'token_hash' => hash('sha256', 'seed-token'), 'approved' => true,
        ]);
        $session->update(['question_number' => 1, 'state' => 'running']);

        $claim = $this->postJson('/r/CLAIM1/join', ['team_id' => $team->id, 'pin' => '123456'])
            ->assertOk()->assertJson(['team_id' => $team->id])->json();

        $this->assertCount(1, $session->teams()->get());
        $this->assertTrue(Hash::check('123456', $session->pin_hash));
        $this->withToken($claim['token'])->getJson('/r/CLAIM1/state')->assertOk()->assertJsonPath('teams.0.name', 'TIK');
    }

    public function test_tiebreak_winner_automatically_breaks_equal_scores_in_final_rankings(): void
    {
        $admin = User::factory()->create();
        $competition = Competition::create([
            'name' => 'Cerdas Cermat', 'slug' => 'cerdas-cermat-tiebreak', 'type' => 'quiz',
            'ranking_direction' => 'desc', 'correct_points' => 10, 'wrong_points' => -5,
        ]);
        $session = CompetitionSession::create([
            'competition_id' => $competition->id, 'code' => 'TIE001', 'pin_hash' => Hash::make('123456'),
            'question_number' => 20, 'settings' => ['competition_type' => 'quiz', 'correct_points' => 10, 'wrong_points' => -5],
        ]);
        $teams = collect([['TIK', 100], ['SANTIK', 100], ['IKP', 80]])->map(fn ($item, $index) => $session->teams()->create([
            'name' => $item[0], 'members' => ['A', 'B', 'C'], 'color' => '#2563eb', 'pitch_hz' => 440 + $index, 'token_hash' => hash('sha256', $item[0]), 'approved' => true, 'score' => $item[1],
        ]));
        $round = $session->rounds()->create([
            'number' => 21, 'phase' => 'tiebreak', 'state' => 'locked', 'answer_attempt' => 0, 'max_attempts' => 2,
            'eligible_team_ids' => $teams->take(2)->pluck('id')->all(), 'eliminated_team_ids' => [], 'winner_team_id' => $teams[0]->id, 'locked_at' => now(),
        ]);

        $this->actingAs($admin)->post(route('quiz.score', $session), ['action' => 'correct'])->assertRedirect();
        $this->assertSame('closed', $round->fresh()->state);
        $this->assertSame(1, SessionPayload::make($session->fresh())['teams'][0]['tiebreak_wins']);

        $this->post(route('quiz.rankings', $session), ['team_ids' => [$teams[1]->id, $teams[0]->id, $teams[2]->id]])->assertRedirect();
        $this->assertDatabaseHas('session_placements', ['competition_session_id' => $session->id, 'quiz_team_id' => $teams[0]->id, 'rank' => 1]);
        $this->assertDatabaseHas('session_placements', ['competition_session_id' => $session->id, 'quiz_team_id' => $teams[1]->id, 'rank' => 2]);
    }

    public function test_general_knockout_bracket_handles_bye_and_advances_winner(): void
    {
        $admin = User::factory()->create();
        $competition = Competition::create([
            'name' => 'Voli Gugur', 'slug' => 'voli-gugur', 'type' => 'general', 'general_format' => 'knockout',
            'duration_seconds' => 60, 'timer_mode' => 'collective', 'result_type' => 'number',
            'ranking_direction' => 'desc', 'correct_points' => 10, 'wrong_points' => -5,
        ]);
        $session = CompetitionSession::create([
            'competition_id' => $competition->id, 'code' => 'KNOCK1', 'pin_hash' => Hash::make('123456'),
            'settings' => ['pin_encrypted' => Crypt::encryptString('123456'), 'competition_type' => 'general', 'general_format' => 'knockout', 'duration_seconds' => 60, 'timer_mode' => 'collective'],
        ]);
        $participants = collect(['Merah', 'Biru', 'Hijau'])->map(fn ($name, $index) => $session->participants()->create(['name' => $name, 'position' => $index + 1]));

        $this->actingAs($admin)->post(route('general.bracket.generate', $session))->assertRedirect();
        $this->assertDatabaseCount('knockout_matches', 2);
        $this->assertSame(1, $session->matches()->where('state', 'completed')->count());
        $this->assertSame(1, $session->matches()->where('state', 'active')->count());

        $this->post(route('sessions.timer', $session), ['action' => 'start'])->assertRedirect();
        $this->assertSame('running', $session->fresh()->state);

        $firstMatch = $session->matches()->where('round_number', 1)->where('state', 'active')->firstOrFail();
        $firstWinner = $firstMatch->participant_a_id;
        $this->post(route('general.matches.complete', [$session, $firstMatch]), ['winner_team_id' => $firstWinner])->assertRedirect();
        $final = $session->matches()->where('round_number', 2)->firstOrFail();
        $this->assertSame('active', $final->state);
        $champion = $final->participant_a_id;
        $this->post(route('general.matches.complete', [$session, $final]), ['winner_team_id' => $champion])->assertRedirect();

        $this->assertSame('finished', $session->fresh()->state);
        $this->assertSame($champion, $final->fresh()->winner_participant_id);
    }
}
