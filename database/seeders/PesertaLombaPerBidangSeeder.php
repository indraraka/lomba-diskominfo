<?php

namespace Database\Seeders;

use App\Models\Competition;
use App\Models\CompetitionSession;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class PesertaLombaPerBidangSeeder extends Seeder
{
    public const SOURCE = 'Daftar peserta per bidang - 10 Agustus 2026';

    public function run(): void
    {
        $rosters = $this->rosters();
        $codes = array_map(
            fn (int $index) => 'BDG'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
            array_keys($rosters),
        );
        $existingCodes = CompetitionSession::query()->whereIn('code', $codes)->pluck('code');

        if ($existingCodes->isNotEmpty()) {
            throw new RuntimeException('Seeder roster dibatalkan karena sesi sudah ada: '.$existingCodes->implode(', ').'. Gunakan migrate:fresh untuk instalasi ulang yang disengaja.');
        }

        $pin = (string) env('LOMBA_SESSION_PIN', app()->isProduction() ? '' : '170826');
        if (! preg_match('/^\d{6}$/', $pin)) {
            throw new RuntimeException('LOMBA_SESSION_PIN wajib berisi tepat 6 digit.');
        }

        foreach ($rosters as $index => $roster) {
            $competition = Competition::where('slug', $roster['competition'])->firstOrFail();
            $code = 'BDG'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT);
            $settings = [
                'seed_source' => self::SOURCE,
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

            $session = CompetitionSession::create([
                'code' => $code,
                'competition_id' => $competition->id,
                'pin_hash' => Hash::make($pin),
                'state' => 'lobby',
                'settings' => $settings,
                'rules_snapshot' => $competition->rules,
            ]);

            foreach ($roster['entries'] as $position => $entry) {
                if ($competition->type === 'quiz') {
                    $colors = ['#dc2626', '#2563eb', '#16a34a', '#ea580c'];
                    $session->teams()->create([
                        'name' => $entry['name'],
                        'affiliation' => $entry['affiliation'],
                        'members' => $entry['members'],
                        'color' => $colors[$position % count($colors)],
                        'pitch_hz' => 440 + ($position * 90),
                        'token_hash' => hash('sha256', self::SOURCE.'|'.$code.'|'.$entry['name']),
                        'approved' => true,
                    ]);

                    continue;
                }

                $session->participants()->create([
                    'name' => $entry['name'],
                    'affiliation' => $entry['affiliation'],
                    'members' => $entry['members'],
                    'position' => $position + 1,
                    'status' => 'waiting',
                    'notes' => $entry['notes'] ?? null,
                ]);
            }
        }
    }

    private function rosters(): array
    {
        return [
            ['competition' => 'lomba-cerdas-cermat', 'entries' => [
                $this->team('TIK', ['Indra', 'Wahyu', 'Fauzan']),
                $this->team('SANTIK', ['Nanang', 'Astri', 'Cucun']),
                $this->team('Sekretariat', ['Bakhrul Ullum', 'Suci Nurjanah', 'Dilla Kusuma Firdayanti Iman']),
                $this->team('IKP', ['H. Dedy Suprayogi', 'Isnaini Nurkholisoh', 'Fikri Aldi Nugraha']),
            ]],
            ['competition' => 'lomba-tumpeng-antar-tim', 'entries' => [
                $this->team('TIK', [], 'Bidang TIK'),
                $this->team('SANTIK', [], 'Bidang SANTIK'),
                $this->team('Sekretariat', [], 'Dokumen Sekretariat tidak mencantumkan nama perwakilan.'),
                $this->team('IKP', [], 'Seluruh staf IKP'),
            ]],
            ['competition' => 'mancing-emosi-makan-kerupuk', 'entries' => [
                $this->team('TIK', ['Ridwan', 'Danang']),
                $this->team('SANTIK', ['Cucun', 'Tofan']),
                $this->team('Sekretariat', ['R. Bambang Sarwo S.', 'Robby']),
                $this->team('IKP', ['Wanto Suwardi', 'Rudedi']),
            ]],
            ['competition' => 'lomba-joget-kardus', 'entries' => [
                $this->person('Ridwan', 'TIK'),
                $this->person('Singgih', 'SANTIK'),
                $this->person('Omo Surumo', 'Sekretariat'),
                $this->person('Tarma', 'IKP'),
            ]],
            ['competition' => 'masukkan-paku-dengan-bantuan-spion', 'entries' => [
                $this->person('Bu Tati', 'TIK'),
                $this->person('Cucun', 'SANTIK'),
                $this->person('Teri Warniah', 'Sekretariat'),
                $this->person('Wira Nulhakim Sudewo', 'IKP'),
            ]],
            ['competition' => 'lomba-suap-suapan', 'entries' => [
                $this->team('TIK', ['Indra', 'Saji']),
                $this->team('SANTIK', ['Tofan', 'Singgih']),
                $this->team('Sekretariat', ['Muhammad Toyib', 'Karyanto']),
                $this->team('IKP', ['Wanto Suwardi', 'Muksinin']),
            ]],
            ['competition' => 'lomba-susun-kata', 'entries' => [
                $this->team('TIK', ['Heru', 'Bu Tati', 'Indra', 'Wahyu', 'Rian', 'Ridwan']),
                $this->team('SANTIK', ['Kiky', 'Nanang', 'Singgih', 'Cucun', 'Tofan', 'Astri']),
                $this->team('Sekretariat', ['Erni Heriningsih', 'Dedi Suwanto', 'Bakhrul Ullum', 'Suci Nurjanah', 'Yudistira Iswanto', 'Dilla Kusuma Firdayanti Iman']),
                $this->team('IKP', ['Isnaini Nurkholisoh', 'Jubaedah', 'Abi Shohib', 'Rudedi', 'Fikri Aldi Nugraha', 'Wira Nulhakim Sudewo'], 'Cadangan: Pak Gusmul'),
            ]],
            ['competition' => 'lomba-pindah-warna', 'entries' => [
                $this->person('Ridwan', 'TIK'), $this->person('Rian', 'TIK'),
                $this->person('Tofan', 'SANTIK'), $this->person('Singgih', 'SANTIK'),
                $this->person('Dilla Kusuma Firdayanti Iman', 'Sekretariat'), $this->person('Bakhrul Ullum', 'Sekretariat'),
                $this->person('Fikri Aldi Nugraha', 'IKP'), $this->person('Wira Nulhakim Sudewo', 'IKP'),
            ]],
            ['competition' => 'lomba-estafet-air', 'entries' => [
                $this->team('TIK', ['Heru', 'Bu Tati', 'Indra', 'Danang', 'Ridwan']),
                $this->team('SANTIK', ['Singgih', 'Astri', 'Cucun', 'Tofan', 'Nanang']),
                $this->team('Sekretariat', ['Erni Heriningsih', 'Teri Warniah', 'Carsih Minarsih', 'Dilla Kusuma Firdayanti Iman', 'Suci Nurjanah']),
                $this->team('IKP', ['H. Dedy Suprayogi', 'Wira Nulhakim Sudewo', 'Tarma', 'Muksinin', 'Abi Shohib']),
            ]],
            ['competition' => 'lomba-voli-air', 'entries' => [
                $this->team('TIK', ['Heru', 'Saji', 'Indra', 'Wahyu', 'Rian', 'Ridwan']),
                $this->team('SANTIK', ['Kiky', 'Nanang', 'Singgih', 'Cucun', 'Tofan', 'Astri']),
                $this->team('Sekretariat', ['Robby', 'Bakhrul Ullum', 'Dedi Suwanto', 'Yudistira Iswanto', 'Suci Nurjanah', 'Carsih Minarsih']),
                $this->team('IKP', ['Isnaini Nurkholisoh', 'Jubaedah', 'Muksinin', 'Tarma', 'Wanto Suwardi', 'Rudedi'], 'Cadangan: Agus Mulyono'),
            ]],
        ];
    }

    private function team(string $name, array $members, ?string $notes = null, ?string $affiliation = null): array
    {
        return ['name' => $name, 'affiliation' => $affiliation ?? preg_replace('/\s+\d+$/', '', $name), 'members' => $members, 'notes' => $notes];
    }

    private function person(string $name, string $affiliation): array
    {
        return ['name' => $name, 'affiliation' => $affiliation, 'members' => [$name]];
    }
}
