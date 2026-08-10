<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StageResult extends Model
{
    protected $fillable = ['competition_stage_id', 'participant_id', 'quiz_team_id', 'outcome', 'primary_value', 'secondary_value', 'penalty_ms', 'score_delta', 'metrics', 'notes'];

    protected function casts(): array
    {
        return ['metrics' => 'array', 'primary_value' => 'decimal:3', 'secondary_value' => 'decimal:3', 'penalty_ms' => 'integer'];
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
