<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Participant extends Model
{
    protected $fillable = ['competition_session_id', 'name', 'affiliation', 'members', 'position', 'status', 'result_value', 'final_rank', 'result_meta', 'notes'];

    protected function casts(): array
    {
        return ['members' => 'array', 'result_meta' => 'array', 'final_rank' => 'integer', 'result_value' => 'decimal:3'];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(CompetitionSession::class, 'competition_session_id');
    }
}
