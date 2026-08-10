<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Competition extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'schedule_text', 'venue', 'person_in_charge', 'rules', 'is_official', 'source_version', 'video_url', 'type', 'workflow_key', 'entry_kind', 'quiz_format', 'general_format', 'duration_seconds', 'timer_mode', 'result_type', 'result_label', 'result_unit', 'ranking_direction', 'correct_points', 'wrong_points', 'status'];

    protected function casts(): array
    {
        return ['duration_seconds' => 'integer', 'correct_points' => 'integer', 'wrong_points' => 'integer', 'rules' => 'array', 'is_official' => 'boolean'];
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(CompetitionSession::class);
    }
}
