<?php

namespace App\Http\Controllers;

use App\Events\SessionStateUpdated;
use App\Models\BuzzAttempt;
use App\Models\CompetitionSession;
use App\Support\QuizTeamStyle;
use App\Support\SessionPayload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;

class GuestQuizController extends Controller
{
    public function show(string $code)
    {
        $session = CompetitionSession::where('code', strtoupper($code))->with('competition')->firstOrFail();
        abort_unless($session->competition->type === 'quiz', 404);

        return Inertia::render('Guest/Quiz', ['initialSession' => SessionPayload::make($session)]);
    }

    public function join(Request $request, string $code)
    {
        $session = CompetitionSession::where('code', strtoupper($code))->with('competition')->firstOrFail();
        $data = $request->validate([
            'team_id' => ['nullable', 'integer'],
            'name' => ['required_without:team_id', 'nullable', 'string', 'max:80'],
            'affiliation' => ['nullable', 'string', 'max:100'],
            'members' => ['required_without:team_id', 'nullable', 'array', 'size:3'],
            'members.*' => ['required', 'string', 'max:100'],
            'pin' => ['required', 'digits:6'],
        ]);
        abort_unless(Hash::check($data['pin'], $session->pin_hash), 422, 'PIN ruang tidak benar.');
        if (! empty($data['team_id'])) {
            $team = $session->teams()->whereKey($data['team_id'])->firstOrFail();
            abort_if($team->last_seen_at?->gt(now()->subSeconds(20)), 422, 'Regu ini masih aktif di perangkat lain.');
            $token = Str::random(64);
            $team->update(['token_hash' => hash('sha256', $token), 'last_seen_at' => now()]);
            $snapshot = SessionPayload::make($session->fresh());
            SessionStateUpdated::dispatch($session->code, $snapshot);

            return response()->json(['token' => $token, 'team_id' => $team->id, 'session' => $snapshot]);
        }

        abort_if($session->question_number > 0, 422, 'Pendaftaran regu baru sudah ditutup. Regu terdaftar tetap dapat masuk.');
        abort_if($session->teams()->count() >= 6, 422, 'Ruang sudah berisi 6 grup.');
        abort_if($session->teams()->whereRaw('lower(name) = ?', [strtolower(trim($data['name']))])->exists(), 422, 'Nama grup sudah digunakan.');

        $token = Str::random(64);
        $style = QuizTeamStyle::at($session->teams()->count());
        $team = $session->teams()->create([
            'name' => trim($data['name']),
            'affiliation' => trim($data['affiliation'] ?? '') ?: null,
            'members' => array_values(array_map('trim', $data['members'])),
            'color' => $style['color'],
            'pitch_hz' => $style['pitch'],
            'token_hash' => hash('sha256', $token),
            'last_seen_at' => now(),
        ]);
        SessionStateUpdated::dispatch($session->code, SessionPayload::make($session));

        return response()->json(['token' => $token, 'team_id' => $team->id, 'session' => SessionPayload::make($session)]);
    }

    public function state(Request $request, string $code)
    {
        $session = CompetitionSession::where('code', strtoupper($code))->firstOrFail();
        $team = $this->team($request, $session);
        $team->update(['last_seen_at' => now()]);

        return response()->json(SessionPayload::make($session));
    }

    public function buzz(Request $request, string $code)
    {
        $session = CompetitionSession::where('code', strtoupper($code))->firstOrFail();
        $team = $this->team($request, $session);
        $data = $request->validate(['idempotency_key' => ['required', 'uuid']]);
        $existing = BuzzAttempt::where('idempotency_key', $data['idempotency_key'])->first();
        if ($existing) {
            return response()->json(['accepted' => $existing->result === 'accepted', 'reason' => $existing->result]);
        }

        $result = Cache::lock("buzz:{$session->id}", 3)->block(2, function () use ($session, $team, $data) {
            $round = $session->rounds()->latest('number')->first();
            $reason = 'locked';
            if (! $team->approved) {
                $reason = 'not_approved';
            } elseif (! $round || ! $round->opens_at || now()->lt($round->opens_at)) {
                $reason = 'too_early';
            } elseif ($round->eligible_team_ids && ! in_array($team->id, $round->eligible_team_ids, true)) {
                $reason = 'eliminated';
            } elseif (in_array($team->id, $round->eliminated_team_ids ?? [])) {
                $reason = 'eliminated';
            } elseif ($round->winner_team_id || $round->state === 'closed') {
                $reason = 'locked';
            } else {
                $reason = 'accepted';
                $round->update(['state' => 'locked', 'winner_team_id' => $team->id, 'locked_at' => now()]);
            }
            BuzzAttempt::create(['buzzer_round_id' => $round?->id, 'quiz_team_id' => $team->id, 'idempotency_key' => $data['idempotency_key'], 'result' => $reason, 'received_at' => now()]);

            return $reason;
        });
        $team->update(['last_seen_at' => now()]);
        $snapshot = SessionPayload::make($session->fresh());
        SessionStateUpdated::dispatch($session->code, $snapshot);

        return response()->json(['accepted' => $result === 'accepted', 'reason' => $result, 'session' => $snapshot]);
    }

    private function team(Request $request, CompetitionSession $session)
    {
        $token = $request->bearerToken() ?: $request->header('X-Team-Token');
        abort_unless($token, 401);

        return $session->teams()->where('token_hash', hash('sha256', $token))->firstOrFail();
    }
}
