<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompetitionAuditLog extends Model
{
    protected $fillable = ['competition_session_id', 'user_id', 'action', 'subject_type', 'subject_id', 'before', 'after', 'reason'];

    protected function casts(): array
    {
        return ['before' => 'array', 'after' => 'array'];
    }
}
