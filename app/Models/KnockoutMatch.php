<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnockoutMatch extends Model
{
    protected $fillable = ['competition_session_id', 'bracket_type', 'round_number', 'match_number', 'participant_a_id', 'participant_b_id', 'score_a', 'score_b', 'winner_participant_id', 'state'];

    public function session(): BelongsTo
    {
        return $this->belongsTo(CompetitionSession::class, 'competition_session_id');
    }

    public function participantA(): BelongsTo
    {
        return $this->belongsTo(Participant::class, 'participant_a_id');
    }

    public function participantB(): BelongsTo
    {
        return $this->belongsTo(Participant::class, 'participant_b_id');
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(Participant::class, 'winner_participant_id');
    }
}
