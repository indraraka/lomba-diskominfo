<?php

namespace App\Http\Controllers;

use App\Models\Competition;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class CompetitionController extends Controller
{
    public function index()
    {
        return Inertia::render('Competitions/Index', [
            'competitions' => Competition::with(['sessions' => fn ($query) => $query->latest()->limit(1)])
                ->latest()->get()->map(fn ($competition) => [
                    ...$competition->toArray(),
                    'latest_session' => $competition->sessions->first(),
                    'sessions_count' => $competition->sessions()->count(),
                ]),
        ]);
    }

    public function create()
    {
        return Inertia::render('Competitions/Form', ['competition' => null]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['slug'] = $this->uniqueSlug($data['name']);
        $competition = Competition::create($data);

        return Redirect::route('competitions.index')->with('success', "Lomba {$competition->name} berhasil dibuat.");
    }

    public function show(Competition $competition)
    {
        return Inertia::render('Competitions/Show', [
            'competition' => $competition,
            'sessions' => $competition->sessions()->latest()->get(),
        ]);
    }

    public function edit(Competition $competition)
    {
        return Inertia::render('Competitions/Form', ['competition' => $competition]);
    }

    public function update(Request $request, Competition $competition)
    {
        $data = $this->validated($request);
        if ($competition->is_official) {
            $data = collect($data)->only(['description', 'video_url', 'schedule_text', 'venue', 'person_in_charge', 'duration_seconds'])->all();
            if (array_key_exists('duration_seconds', $data)) {
                $rules = $competition->rules ?: [];
                $rules['workflow']['duration_seconds'] = $data['duration_seconds'];
                if ($competition->workflow_key === 'multi_round_score') {
                    $rules['workflow']['timer_kind'] = 'countdown';
                }
                $data['rules'] = $rules;
            }
        }
        $competition->update($data);

        return Redirect::route('competitions.index')->with('success', 'Perubahan lomba tersimpan.');
    }

    public function duplicate(Competition $competition)
    {
        $copy = $competition->replicate(['slug', 'is_official', 'source_version']);
        $copy->name = $competition->name.' (Kustom)';
        $copy->slug = $this->uniqueSlug($copy->name);
        $copy->is_official = false;
        $copy->source_version = 'Salinan dari '.$competition->source_version;
        $copy->save();

        return Redirect::route('competitions.edit', $copy)->with('success', 'Salinan kustom dibuat.');
    }

    public function destroy(Competition $competition)
    {
        $competition->update(['status' => $competition->status === 'active' ? 'archived' : 'active']);

        return back()->with('success', $competition->status === 'archived' ? 'Lomba diarsipkan.' : 'Lomba diaktifkan kembali.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'schedule_text' => ['nullable', 'string', 'max:150'],
            'venue' => ['nullable', 'string', 'max:150'],
            'person_in_charge' => ['nullable', 'string', 'max:100'],
            'rules_json' => ['nullable', 'json'],
            'video_url' => ['nullable', 'url:http,https', 'max:500'],
            'type' => ['required', Rule::in(['general', 'quiz'])],
            'workflow_key' => ['nullable', Rule::in(['manual_rank', 'quiz_two_phase', 'judged_rubric', 'metric_race', 'multi_round_score', 'elimination', 'knockout_score'])],
            'entry_kind' => ['nullable', Rule::in(['individual', 'team'])],
            'quiz_format' => ['nullable', Rule::in(['points', 'knockout']), Rule::requiredIf($request->input('type') === 'quiz')],
            'general_format' => ['nullable', Rule::in(['standard', 'knockout']), Rule::requiredIf($request->input('type') === 'general')],
            'duration_seconds' => ['nullable', 'integer', 'min:1', 'max:86400', Rule::requiredIf($request->input('type') === 'general' && $request->input('workflow_key', 'manual_rank') === 'manual_rank')],
            'timer_mode' => ['nullable', Rule::in(['individual', 'collective']), Rule::requiredIf($request->input('type') === 'general' && $request->input('workflow_key', 'manual_rank') === 'manual_rank')],
            'result_type' => ['nullable', Rule::in(['number', 'time'])],
            'result_label' => ['nullable', 'string', 'max:60'],
            'result_unit' => ['nullable', 'string', 'max:20'],
            'ranking_direction' => ['required', Rule::in(['asc', 'desc'])],
            'correct_points' => ['required', 'integer', 'min:-9999', 'max:9999'],
            'wrong_points' => ['required', 'integer', 'min:-9999', 'max:9999'],
        ]);

        if ($data['type'] === 'quiz') {
            $data['duration_seconds'] = null;
            $data['timer_mode'] = null;
            $data['result_type'] = null;
            $data['quiz_format'] = 'points';
            $data['general_format'] = 'standard';
        } else {
            $data['quiz_format'] = 'points';
            if (($data['general_format'] ?? 'standard') === 'knockout') {
                $data['timer_mode'] = 'collective';
            }
        }

        $data['workflow_key'] ??= $data['type'] === 'quiz' ? 'quiz_two_phase' : 'manual_rank';
        $data['entry_kind'] ??= 'individual';
        if (array_key_exists('rules_json', $data)) {
            $data['rules'] = $data['rules_json'] ? json_decode($data['rules_json'], true, flags: JSON_THROW_ON_ERROR) : null;
            unset($data['rules_json']);
        }

        return $data;
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'lomba';
        $slug = $base;
        $counter = 2;
        while (Competition::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$counter++;
        }

        return $slug;
    }
}
