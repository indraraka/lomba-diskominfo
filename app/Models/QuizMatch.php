<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizMatch extends Model
{
    protected $fillable = ['competition_session_id', 'round_number', 'match_number', 'team_a_id', 'team_b_id', 'winner_team_id', 'state'];

    public function session(): BelongsTo
    {
        return $this->belongsTo(CompetitionSession::class, 'competition_session_id');
    }

    public function teamA(): BelongsTo
    {
        return $this->belongsTo(QuizTeam::class, 'team_a_id');
    }

    public function teamB(): BelongsTo
    {
        return $this->belongsTo(QuizTeam::class, 'team_b_id');
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(QuizTeam::class, 'winner_team_id');
    }
}
