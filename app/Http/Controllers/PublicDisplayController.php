<?php

namespace App\Http\Controllers;

use App\Models\CompetitionSession;
use App\Support\SessionPayload;
use Inertia\Inertia;

class PublicDisplayController extends Controller
{
    public function __invoke(string $code)
    {
        $session = CompetitionSession::where('code', strtoupper($code))->firstOrFail();

        return Inertia::render('Public/Display', ['initialSession' => SessionPayload::make($session)]);
    }

    public function state(string $code)
    {
        $session = CompetitionSession::where('code', strtoupper($code))->firstOrFail();

        return response()->json(SessionPayload::make($session));
    }
}
