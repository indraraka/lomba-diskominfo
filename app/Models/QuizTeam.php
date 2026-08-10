<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizTeam extends Model
{
    protected $fillable = ['competition_session_id', 'name', 'affiliation', 'members', 'color', 'pitch_hz', 'token_hash', 'approved', 'score', 'final_rank', 'last_seen_at'];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return ['approved' => 'boolean', 'last_seen_at' => 'datetime', 'members' => 'array', 'final_rank' => 'integer'];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(CompetitionSession::class, 'competition_session_id');
    }
}
