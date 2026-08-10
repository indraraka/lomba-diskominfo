<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\CompetitionSession;
use Database\Seeders\LombaAgustusanSeeder;
use Database\Seeders\PesertaLombaPerBidangSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LombaAgustusanSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_all_official_pdf_profiles_idempotently(): void
    {
        $this->seed(LombaAgustusanSeeder::class);
        $this->seed(LombaAgustusanSeeder::class);

        $this->assertDatabaseCount('competitions', 10);
        $this->assertDatabaseHas('competitions', [
            'slug' => 'lomba-cerdas-cermat',
            'type' => 'quiz',
            'workflow_key' => 'quiz_two_phase',
            'is_official' => true,
            'correct_points' => 10,
            'wrong_points' => -5,
            'video_url' => 'https://www.youtube.com/watch?v=ipsZyGKzqqI',
        ]);
        $this->assertDatabaseHas('competitions', [
            'slug' => 'lomba-joget-kardus',
            'workflow_key' => 'metric_race',
            'duration_seconds' => 90,
            'ranking_direction' => 'asc',
        ]);
        $this->assertDatabaseHas('competitions', [
            'slug' => 'lomba-tumpeng-antar-tim',
            'timer_mode' => 'collective',
            'duration_seconds' => 2700,
        ]);
        $this->assertDatabaseHas('competitions', [
            'slug' => 'lomba-voli-air',
            'type' => 'general',
            'general_format' => 'knockout',
            'workflow_key' => 'knockout_score',
        ]);

        $this->assertStringContainsString(
            'Genangan',
            implode(' ', Competition::where('slug', 'lomba-estafet-air')->firstOrFail()->rules['safety'])
        );

        $estafet = Competition::where('slug', 'lomba-estafet-air')->firstOrFail();
        $this->assertStringContainsString('Spons jatuh', implode(' ', $estafet->rules['contingencies']));
        $this->assertStringContainsString('tanda posisinya', implode(' ', $estafet->rules['setup']));
    }

    public function test_each_pdf_lomba_has_the_expected_workflow_roster_and_play_limit(): void
    {
        $this->seed(LombaAgustusanSeeder::class);
        $expected = [
            'lomba-cerdas-cermat' => ['quiz_two_phase', 3, 3, null],
            'lomba-tumpeng-antar-tim' => ['judged_rubric', 1, 4, 2700],
            'mancing-emosi-makan-kerupuk' => ['metric_race', 2, 2, 180],
            'lomba-joget-kardus' => ['metric_race', 1, 1, 90],
            'masukkan-paku-dengan-bantuan-spion' => ['metric_race', 1, 1, 120],
            'lomba-suap-suapan' => ['metric_race', 2, 2, 180],
            'lomba-susun-kata' => ['multi_round_score', 6, 6, 180],
            'lomba-pindah-warna' => ['elimination', 1, 1, null],
            'lomba-estafet-air' => ['metric_race', 5, 5, null],
            'lomba-voli-air' => ['knockout_score', 6, 6, null],
        ];
        foreach ($expected as $slug => [$workflow, $min, $max, $duration]) {
            $competition = Competition::where('slug', $slug)->firstOrFail();
            $this->assertSame($workflow, $competition->workflow_key, $slug);
            $this->assertSame($min, $competition->rules['entry']['members_min'], $slug);
            $this->assertSame($max, $competition->rules['entry']['members_max'], $slug);
            $this->assertSame($duration, $competition->rules['workflow']['duration_seconds'] ?? null, $slug);
            $this->assertNotEmpty($competition->video_url, $slug.' harus memiliki contoh video');
            $this->assertNotEmpty($competition->rules['setup'], $slug.' harus memiliki petunjuk persiapan');
            $this->assertNotEmpty($competition->rules['scoring'], $slug.' harus memiliki aturan penilaian');
        }
    }

    public function test_mancing_emosi_roster_keeps_only_the_first_pair_per_affiliation(): void
    {
        $this->seed(LombaAgustusanSeeder::class);
        $this->seed(PesertaLombaPerBidangSeeder::class);

        $session = CompetitionSession::where('code', 'BDG003')->firstOrFail();
        $this->assertSame(['TIK', 'SANTIK', 'Sekretariat', 'IKP'], $session->participants()->pluck('name')->all());
        $this->assertSame(['Ridwan', 'Danang'], $session->participants()->where('name', 'TIK')->firstOrFail()->members);
        $this->assertSame(['Cucun', 'Tofan'], $session->participants()->where('name', 'SANTIK')->firstOrFail()->members);
        $this->assertSame(4, $session->participants()->count());
    }
}
