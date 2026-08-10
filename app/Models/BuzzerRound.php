<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BuzzerRound extends Model
{
    protected $fillable = ['competition_session_id', 'number', 'phase', 'answer_attempt', 'max_attempts', 'state', 'opens_at', 'winner_team_id', 'eliminated_team_ids', 'eligible_team_ids', 'locked_at'];

    protected function casts(): array
    {
        return ['opens_at' => 'datetime', 'locked_at' => 'datetime', 'eliminated_team_ids' => 'array', 'eligible_team_ids' => 'array'];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(CompetitionSession::class, 'competition_session_id');
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(QuizTeam::class, 'winner_team_id');
    }
}
