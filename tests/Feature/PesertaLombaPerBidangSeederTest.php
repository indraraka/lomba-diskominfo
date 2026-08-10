<?php

namespace Tests\Feature;

use App\Models\CompetitionSession;
use Database\Seeders\LombaAgustusanSeeder;
use Database\Seeders\PesertaLombaPerBidangSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PesertaLombaPerBidangSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_the_complete_field_roster_once_without_allowing_a_destructive_rerun(): void
    {
        $this->seed(LombaAgustusanSeeder::class);
        $this->seed(PesertaLombaPerBidangSeeder::class);

        try {
            $this->seed(PesertaLombaPerBidangSeeder::class);
            $this->fail('Seeder roster kedua seharusnya ditolak.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Seeder roster dibatalkan', $exception->getMessage());
        }

        $sessions = CompetitionSession::where('code', 'like', 'BDG%')->get();
        $this->assertCount(10, $sessions);
        $this->assertSame(40, $sessions->sum(fn ($session) => $session->participants()->count()));
        $this->assertSame(4, $sessions->sum(fn ($session) => $session->teams()->count()));

        $quiz = CompetitionSession::where('code', 'BDG001')->firstOrFail();
        $this->assertSame(['TIK', 'SANTIK', 'Sekretariat', 'IKP'], $quiz->teams()->pluck('affiliation')->all());
        $this->assertSame(['Indra', 'Wahyu', 'Fauzan'], $quiz->teams()->where('affiliation', 'TIK')->firstOrFail()->members);

        $mancing = CompetitionSession::where('code', 'BDG003')->firstOrFail();
        $this->assertSame(1, $mancing->participants()->where('affiliation', 'TIK')->count());
        $this->assertSame(1, $mancing->participants()->where('affiliation', 'SANTIK')->count());
        $this->assertSame(['TIK', 'SANTIK', 'Sekretariat', 'IKP'], $mancing->participants()->pluck('name')->all());

        $voli = CompetitionSession::where('code', 'BDG010')->firstOrFail();
        $this->assertStringContainsString('Agus Mulyono', $voli->participants()->where('affiliation', 'IKP')->firstOrFail()->notes);
    }
}
