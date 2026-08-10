<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompetitionIncident extends Model
{
    protected $fillable = ['competition_session_id', 'competition_stage_id', 'participant_id', 'quiz_team_id', 'rule_code', 'kind', 'value', 'reason', 'recorded_by', 'reversed_at'];

    protected function casts(): array
    {
        return ['reversed_at' => 'datetime', 'value' => 'integer'];
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(CompetitionStage::class, 'competition_stage_id');
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
