<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompetitionStage extends Model
{
    protected $fillable = ['competition_session_id', 'phase', 'sequence', 'heat_number', 'attempt_number', 'label', 'state', 'timer_kind', 'duration_seconds', 'started_at', 'ends_at', 'paused_remaining_ms', 'config'];

    protected function casts(): array
    {
        return ['config' => 'array', 'started_at' => 'datetime', 'ends_at' => 'datetime', 'duration_seconds' => 'integer'];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(CompetitionSession::class, 'competition_session_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(StageResult::class);
    }
}
