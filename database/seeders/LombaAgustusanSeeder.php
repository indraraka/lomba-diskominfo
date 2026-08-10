<?php

namespace Database\Seeders;

use App\Models\Competition;
use App\Support\OfficialCompetitionProfiles;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class LombaAgustusanSeeder extends Seeder
{
    public function run(): void
    {
        foreach (OfficialCompetitionProfiles::all() as $profile) {
            $workflow = $profile['rules']['workflow'];
            Competition::updateOrCreate(['slug' => Str::slug($profile['name'])], [
                'name' => $profile['name'],
                'description' => implode("\n", $profile['rules']['play']),
                'schedule_text' => $profile['schedule'],
                'venue' => $profile['venue'],
                'person_in_charge' => $profile['pic'],
                'video_url' => $profile['video_url'],
                'rules' => $profile['rules'],
                'is_official' => true,
                'source_version' => OfficialCompetitionProfiles::VERSION,
                'type' => $profile['type'],
                'workflow_key' => $profile['workflow'],
                'entry_kind' => $profile['kind'],
                'quiz_format' => 'points',
                'general_format' => $profile['generalFormat'],
                'duration_seconds' => $workflow['duration_seconds'] ?? null,
                'timer_mode' => ($workflow['timer_kind'] ?? null) === 'countdown' ? 'collective' : (($workflow['timer_kind'] ?? null) === 'stopwatch' ? 'collective' : null),
                'result_type' => in_array($profile['workflow'], ['metric_race', 'judged_rubric', 'multi_round_score'], true) ? 'number' : null,
                'result_label' => match ($profile['workflow']) {
                    'judged_rubric' => 'Nilai juri',
                    'multi_round_score' => 'Jawaban benar',
                    'metric_race' => 'Hasil resmi',
                    default => null,
                },
                'result_unit' => match ($profile['workflow']) {
                    'judged_rubric' => 'poin',
                    'multi_round_score' => 'jawaban',
                    default => null,
                },
                'ranking_direction' => $workflow['ranking'] ?? 'desc',
                'correct_points' => $workflow['correct_points'] ?? 10,
                'wrong_points' => $workflow['rebuttal_wrong_points'] ?? -5,
                'status' => 'active',
            ]);
        }
    }
}
