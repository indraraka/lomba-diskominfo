<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SessionPlacement extends Model
{
    protected $fillable = ['competition_session_id', 'participant_id', 'quiz_team_id', 'rank', 'result_summary', 'decision_reason', 'decided_by'];

    protected function casts(): array
    {
        return ['result_summary' => 'array', 'rank' => 'integer'];
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(QuizTeam::class, 'quiz_team_id');
    }
}
