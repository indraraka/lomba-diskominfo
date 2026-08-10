<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScoreTransaction extends Model
{
    protected $fillable = ['competition_session_id', 'quiz_team_id', 'buzzer_round_id', 'kind', 'delta', 'balance_after', 'reason', 'reverses_id'];

    public function team(): BelongsTo
    {
        return $this->belongsTo(QuizTeam::class, 'quiz_team_id');
    }
}
